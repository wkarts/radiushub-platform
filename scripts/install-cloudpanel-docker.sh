#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

DB_ENGINE=postgres
USE_IMAGES=false
PLAYGROUND=false
SKIP_BUILD=false
APP_URL_VALUE=""
APP_PORT_VALUE="8080"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --mysql) DB_ENGINE=mysql ;;
    --postgres|--postgresql) DB_ENGINE=postgres ;;
    --pull-images) USE_IMAGES=true ;;
    --playground) PLAYGROUND=true ;;
    --skip-build) SKIP_BUILD=true ;;
    --url=*) APP_URL_VALUE="${1#*=}" ;;
    --url) shift; APP_URL_VALUE="${1:-}" ;;
    --port=*) APP_PORT_VALUE="${1#*=}" ;;
    --port) shift; APP_PORT_VALUE="${1:-}" ;;
    --help|-h)
      cat <<'HELP'
Uso: scripts/install-cloudpanel-docker.sh [opções]

Opções:
  --postgres          PostgreSQL 17 (padrão).
  --mysql             MySQL 8.4.
  --pull-images       Usa imagens publicadas no GHCR.
  --playground        Sobe o ambiente descartável de testes.
  --skip-build        No playground, usa imagens locais já construídas pelo CI.
  --url URL           URL pública HTTPS configurada no CloudPanel.
  --port PORTA        Porta local do container web (padrão 8080).

O script mantém a aplicação vinculada a 127.0.0.1 e gera o snippet de
reverse proxy para a área Custom Nginx Configuration do CloudPanel.
HELP
      exit 0 ;;
    *) echo "Opção desconhecida: $1" >&2; exit 1 ;;
  esac
  shift
done

if [[ -z "$APP_URL_VALUE" && -t 0 ]]; then
  read -r -p "URL pública HTTPS do CloudPanel: " APP_URL_VALUE
fi
[[ "$APP_URL_VALUE" =~ ^https?:// ]] || { echo "Informe --url com http:// ou https://." >&2; exit 1; }
[[ "$APP_PORT_VALUE" =~ ^[0-9]+$ ]] && (( APP_PORT_VALUE >= 1 && APP_PORT_VALUE <= 65535 )) \
  || { echo "Porta local inválida: $APP_PORT_VALUE" >&2; exit 1; }

if [[ "$SKIP_BUILD" == true && "$PLAYGROUND" != true ]]; then
  echo "--skip-build é permitido somente com --playground." >&2
  exit 1
fi

if [[ "$PLAYGROUND" == true && "$DB_ENGINE" != postgres ]]; then
  echo "O playground integrado usa PostgreSQL para reproduzir a matriz principal de homologação. Remova --mysql." >&2
  exit 1
fi

if [[ "$PLAYGROUND" == true ]]; then
  export ENV_FILE="${ENV_FILE:-$PROJECT_ROOT/.env.playground}"
  [[ -f "$ENV_FILE" ]] || cp .env.playground.example "$ENV_FILE"
else
  export ENV_FILE="${ENV_FILE:-$PROJECT_ROOT/.env}"
  [[ -f "$ENV_FILE" ]] || cp ".env.docker.${DB_ENGINE}.example" "$ENV_FILE"
fi

source "$PROJECT_ROOT/scripts/lib.sh"
backup_env
set_env APP_URL "$APP_URL_VALUE"
set_env APP_PORT "$APP_PORT_VALUE"
set_env APP_BIND_ADDRESS 127.0.0.1
set_env TRUSTED_PROXIES '*'
if [[ "$APP_URL_VALUE" == https://* ]]; then
  set_env SESSION_SECURE_COOKIE true
else
  set_env SESSION_SECURE_COOKIE false
fi

if [[ "$PLAYGROUND" == true && ! "$APP_URL_VALUE" =~ ^https?://(127\.0\.0\.1|localhost)(:[0-9]+)?/?$ ]]; then
  # Playground remoto não deve expor páginas de exceção detalhadas.
  set_env APP_DEBUG false
  set_env LOG_LEVEL info
fi

mkdir -p storage/app/deploy
sed -e "s|__APP_PORT__|$APP_PORT_VALUE|g" \
  deploy/cloudpanel/nginx-docker-reverse-proxy.conf \
  > storage/app/deploy/nginx-docker-reverse-proxy.conf

args=()
[[ "$USE_IMAGES" == true ]] && args+=(--pull-images)
[[ "$SKIP_BUILD" == true ]] && args+=(--skip-build)

if [[ "$PLAYGROUND" == true ]]; then
  if [[ "$APP_URL_VALUE" =~ ^https?://(127\.0\.0\.1|localhost)(:[0-9]+)?/?$ ]]; then
    args+=(--smoke-url "$APP_URL_VALUE")
  else
    # Em uma instalação nova o proxy ainda precisa ser colado no CloudPanel.
    # Valida-se o stack local e o RADIUS; o login HTTPS é executado depois.
    args+=(--skip-http-smoke)
  fi
  ENV_FILE="$ENV_FILE" "$PROJECT_ROOT/scripts/playground.sh" up "${args[@]}"
else
  args+=("--$DB_ENGINE")
  NON_INTERACTIVE=true ENV_FILE="$ENV_FILE" "$PROJECT_ROOT/scripts/install-docker.sh" "${args[@]}"
fi

cat <<INFO

RadiusHub Docker preparado para CloudPanel.
URL pública: $APP_URL_VALUE
Proxy local: http://127.0.0.1:$APP_PORT_VALUE
Modo: $([[ "$PLAYGROUND" == true ]] && echo playground || echo produção)

No CloudPanel, cole o conteúdo deste arquivo em Custom Nginx Configuration:
  $PROJECT_ROOT/storage/app/deploy/nginx-docker-reverse-proxy.conf

Depois de aplicar o snippet, valide o domínio público:
  ENV_FILE=$ENV_FILE ./scripts/validate-deployment.sh --http$([[ "$PLAYGROUND" == true ]] && echo " --login") --url $APP_URL_VALUE
INFO
