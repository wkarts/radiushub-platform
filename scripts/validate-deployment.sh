#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${ENV_FILE:-$PROJECT_ROOT/.env}"
export ENV_FILE
source "$PROJECT_ROOT/scripts/lib.sh"
cd "$PROJECT_ROOT"

STRICT=false
HTTP=false
LOGIN=false
BASE_URL=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --strict) STRICT=true ;;
    --http) HTTP=true ;;
    --login) HTTP=true; LOGIN=true ;;
    --url=*) BASE_URL="${1#*=}" ;;
    --url) shift; BASE_URL="${1:-}" ;;
    --help|-h)
      cat <<'HELP'
Uso: scripts/validate-deployment.sh [--strict] [--http] [--login] [--url URL]

Valida versão, migrations, ambiente Laravel, banco, cache, storage e Doctor.
--http   consulta /health/live e /health/ready.
--login  também realiza login de smoke com as credenciais do playground.
--strict trata avisos do Doctor como erro.
HELP
      exit 0 ;;
    *) die "Opção desconhecida: $1" ;;
  esac
  shift
done

[[ -f "$ENV_FILE" ]] || die "Arquivo de ambiente não encontrado: $ENV_FILE"
command_exists php || die "PHP CLI não encontrado."
ensure_runtime_dirs

log "Validando inventário e versionamento..."
php scripts/check-version-integrity.php
php scripts/check-migration-integrity.php
php scripts/check-planning-compliance.php

log "Validando readiness da aplicação..."
php artisan radiushub:health --ready

log "Executando RadiusHub Doctor..."
if [[ "$STRICT" == true ]]; then
  php artisan radiushub:doctor --strict
else
  php artisan radiushub:doctor
fi

if [[ "$(read_env PLAYGROUND_MODE false)" == "true" ]]; then
  log "Validando dados e simulador do playground..."
  php artisan radiushub:playground:verify
fi

if [[ "$HTTP" == true ]]; then
  command_exists curl || die "curl não encontrado."
  [[ -n "$BASE_URL" ]] || BASE_URL="$(read_env APP_URL)"
  [[ -n "$BASE_URL" ]] || die "APP_URL ausente; informe --url."
  BASE_URL="${BASE_URL%/}"
  curl -fsS "$BASE_URL/health/live" >/dev/null
  curl -fsS "$BASE_URL/health/ready" >/dev/null
  log "Endpoints HTTP de saúde aprovados em $BASE_URL."
fi

if [[ "$LOGIN" == true ]]; then
  [[ "$(read_env PLAYGROUND_MODE false)" == "true" ]] || die "--login é permitido somente no modo playground."
  PLAYGROUND_ADMIN_EMAIL="$(read_env PLAYGROUND_ADMIN_EMAIL)" \
  PLAYGROUND_ADMIN_PASSWORD="$(read_env PLAYGROUND_ADMIN_PASSWORD)" \
    "$PROJECT_ROOT/scripts/smoke-http.sh" "$BASE_URL"
fi

printf 'DEPLOYMENT_VALIDATION_OK\nMODE=%s\nVERSION=%s\n' "$(read_env DEPLOYMENT_MODE unknown)" "$(cat VERSION)"
