#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${ENV_FILE:-$PROJECT_ROOT/.env.playground}"
export ENV_FILE
source "$PROJECT_ROOT/scripts/lib.sh"
cd "$PROJECT_ROOT"

if [[ "${1:-}" == "--help" || "${1:-}" == "-h" || "${1:-}" == "help" ]]; then
  set -- up --help
fi

ACTION="${1:-up}"
shift || true
USE_IMAGES=false
FOLLOW=false
SKIP_HTTP_SMOKE=false
SKIP_BUILD=false
SMOKE_URL=""
while [[ $# -gt 0 ]]; do
  case "$1" in
    --pull-images) USE_IMAGES=true ;;
    --follow|-f) FOLLOW=true ;;
    --skip-http-smoke) SKIP_HTTP_SMOKE=true ;;
    --skip-build) SKIP_BUILD=true ;;
    --smoke-url=*) SMOKE_URL="${1#*=}" ;;
    --smoke-url) shift; SMOKE_URL="${1:-}" ;;
    --help|-h)
      cat <<'HELP'
Uso: scripts/playground.sh [up|down|reset|status|logs|verify|credentials] [opções]

Comandos:
  up            Gera configuração, inicia e valida o playground Docker.
  down          Encerra os serviços preservando os volumes.
  reset         Remove volumes e recria todos os dados demonstrativos.
  status        Mostra serviços e endpoints de saúde.
  logs          Mostra logs dos serviços principais.
  verify        Executa healthcheck, login HTTP e autenticação/accounting RADIUS.
  credentials   Exibe as credenciais geradas no arquivo local.

Opções:
  --pull-images  Usa imagens publicadas no GHCR em vez de compilar localmente.
  --follow           Mantém acompanhamento dos logs.
  --smoke-url URL    URL usada no teste de login; padrão: APP_URL.
  --skip-http-smoke  Adia o login HTTP, útil antes de aplicar o proxy CloudPanel.
  --skip-build       Usa imagens já construídas localmente; destinado ao CI após build explícito.
HELP
      exit 0 ;;
    *) die "Opção desconhecida: $1" ;;
  esac
  shift
done

compose=(docker compose --env-file "$ENV_FILE" -p radiushub-playground -f docker-compose.yml -f docker-compose.playground.yml --profile postgres)

prepare_env() {
  command_exists docker || die "Docker Engine não encontrado."
  docker compose version >/dev/null 2>&1 || die "Docker Compose v2 não encontrado."
  command_exists openssl || die "OpenSSL não encontrado."

  if [[ ! -f "$ENV_FILE" ]]; then
    cp .env.playground.example "$ENV_FILE"
  fi

  ensure_runtime_dirs
  ensure_secrets
  set_env PLAYGROUND_MODE true
  set_env PLAYGROUND_BANNER true
  set_env PLAYGROUND_MIKROTIK_SIMULATOR true
  set_env SEED_DEMO true
  set_env AUTO_MIGRATE true
  set_env AUTO_SEED true
  set_env DEPLOYMENT_MODE playground
  set_env DB_CONNECTION pgsql
  set_env DB_HOST postgres
  set_env DB_PORT 5432
  set_env COMPOSE_PROFILES postgres
  if [[ "$ENV_FILE" == "$PROJECT_ROOT/"* ]]; then
    set_env RADIUSHUB_ENV_FILE "${ENV_FILE#"$PROJECT_ROOT/"}"
  else
    set_env RADIUSHUB_ENV_FILE "$ENV_FILE"
  fi
  set_env APP_BIND_ADDRESS 127.0.0.1
  set_env RADIUS_BIND_ADDRESS 127.0.0.1

  local value
  value="$(read_env DB_PASSWORD)"
  [[ -n "$value" && "$value" != change-this* ]] || set_env DB_PASSWORD "$(random_hex 24)"

  value="$(read_env PLAYGROUND_ADMIN_PASSWORD)"
  [[ -n "$value" && "$value" != 'ChangeMe@123!' ]] || set_env PLAYGROUND_ADMIN_PASSWORD "$(random_password)"
  set_env SEED_ADMIN_PASSWORD "$(read_env PLAYGROUND_ADMIN_PASSWORD)"

  value="$(read_env PLAYGROUND_OPERATOR_PASSWORD)"
  [[ -n "$value" && "$value" != 'Operador@123!' ]] || set_env PLAYGROUND_OPERATOR_PASSWORD "$(random_password)"

  value="$(read_env PLAYGROUND_TECHNICIAN_PASSWORD)"
  [[ -n "$value" && "$value" != 'Tecnico@123!' ]] || set_env PLAYGROUND_TECHNICIAN_PASSWORD "$(random_password)"

  validate_no_placeholders
  chmod 600 "$ENV_FILE"
  "${compose[@]}" config --quiet
}

local_base_url() {
  printf 'http://127.0.0.1:%s' "$(read_env APP_PORT 8080)"
}

smoke_url() {
  if [[ -n "$SMOKE_URL" ]]; then
    printf '%s' "${SMOKE_URL%/}"
  else
    printf '%s' "$(read_env APP_URL http://127.0.0.1:8080)"
  fi
}

run_http_smoke() {
  if [[ "$SKIP_HTTP_SMOKE" == true ]]; then
    warn "Smoke de login HTTP adiado. Aplique o reverse proxy e execute scripts/validate-deployment.sh --http --login."
    return 0
  fi

  PLAYGROUND_ADMIN_EMAIL="$(read_env PLAYGROUND_ADMIN_EMAIL)" \
    PLAYGROUND_ADMIN_PASSWORD="$(read_env PLAYGROUND_ADMIN_PASSWORD)" \
    "$PROJECT_ROOT/scripts/smoke-http.sh" "$(smoke_url)"
}

wait_http() {
  local url="$(local_base_url)/health/ready"
  local attempt
  for attempt in $(seq 1 90); do
    if curl -fsS "$url" >/dev/null 2>&1; then
      return 0
    fi
    sleep 2
  done
  "${compose[@]}" ps || true
  "${compose[@]}" logs --tail=200 app web postgres redis || true
  die "O playground não ficou pronto em até 180 segundos."
}

show_credentials() {
  cat <<INFO

Playground RadiusHub
URL:        $(read_env APP_URL http://127.0.0.1:8080)
Administrador: $(read_env PLAYGROUND_ADMIN_EMAIL)
Senha:         $(read_env PLAYGROUND_ADMIN_PASSWORD)
Operador:      $(read_env PLAYGROUND_OPERATOR_EMAIL)
Senha:         $(read_env PLAYGROUND_OPERATOR_PASSWORD)
Técnico:       $(read_env PLAYGROUND_TECHNICIAN_EMAIL)
Senha:         $(read_env PLAYGROUND_TECHNICIAN_PASSWORD)
Acesso RADIUS: $(read_env PLAYGROUND_NETWORK_USERNAME cliente.demo)
Senha RADIUS:  $(read_env PLAYGROUND_NETWORK_PASSWORD ClienteDemo@123)

Arquivo local protegido: $ENV_FILE
INFO
}

case "$ACTION" in
  up)
    prepare_env
    if [[ "$USE_IMAGES" == true ]]; then
      log "Baixando imagens RadiusHub publicadas..."
      "${compose[@]}" pull app web worker scheduler freeradius redis postgres
    elif [[ "$SKIP_BUILD" == true ]]; then
      log "Usando imagens locais previamente construídas e validadas."
    else
      log "Compilando imagens do playground..."
      "${compose[@]}" build --pull app web freeradius
    fi
    log "Iniciando playground completo..."
    "${compose[@]}" up -d --remove-orphans
    wait_http
    "${compose[@]}" exec -T app php artisan radiushub:playground:verify
    run_http_smoke
    ENV_FILE="$ENV_FILE" "$PROJECT_ROOT/scripts/smoke-radius.sh"
    show_credentials
    ;;
  down)
    prepare_env
    "${compose[@]}" down --remove-orphans
    ;;
  reset)
    prepare_env
    warn "Todos os dados e volumes do playground serão removidos."
    "${compose[@]}" down -v --remove-orphans
    if [[ "$USE_IMAGES" == true ]]; then
      "${compose[@]}" pull app web worker scheduler freeradius redis postgres
    elif [[ "$SKIP_BUILD" == true ]]; then
      log "Usando imagens locais previamente construídas e validadas."
    else
      "${compose[@]}" build --pull app web freeradius
    fi
    "${compose[@]}" up -d --remove-orphans
    wait_http
    "${compose[@]}" exec -T app php artisan radiushub:playground:verify
    run_http_smoke
    ENV_FILE="$ENV_FILE" "$PROJECT_ROOT/scripts/smoke-radius.sh"
    show_credentials
    ;;
  status)
    prepare_env
    "${compose[@]}" ps
    curl -fsS "$(local_base_url)/health/live" || true
    printf '\n'
    curl -fsS "$(local_base_url)/health/ready" || true
    printf '\n'
    ;;
  logs)
    prepare_env
    if [[ "$FOLLOW" == true ]]; then
      "${compose[@]}" logs -f app web worker scheduler freeradius postgres redis
    else
      "${compose[@]}" logs --tail=300 app web worker scheduler freeradius postgres redis
    fi
    ;;
  verify)
    prepare_env
    wait_http
    "${compose[@]}" exec -T app php artisan radiushub:health --ready
    "${compose[@]}" exec -T app php artisan radiushub:playground:verify
    run_http_smoke
    ENV_FILE="$ENV_FILE" "$PROJECT_ROOT/scripts/smoke-radius.sh"
    ;;
  credentials)
    prepare_env
    show_credentials
    ;;
  *) die "Comando inválido: $ACTION" ;;
esac
