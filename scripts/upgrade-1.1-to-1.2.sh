#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/lib.sh"
cd "$PROJECT_ROOT"
[[ -f "$ENV_FILE" ]] || die ".env não encontrado."
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
backup_env
"$PROJECT_ROOT/scripts/backup.sh" --native || die "Backup automático falhou. Corrija antes de prosseguir."

# Preserva APP_KEY e credenciais já criptografadas. Corrige somente drivers inadequados ao CloudPanel nativo.
if [[ "$(read_env DEPLOYMENT_MODE native)" != docker ]]; then
  set_env DEPLOYMENT_MODE native
  [[ "$(read_env REDIS_HOST)" != redis ]] || set_env REDIS_HOST 127.0.0.1
  if [[ "$(read_env CACHE_STORE)" == redis ]]; then set_env CACHE_STORE database; fi
  if [[ "$(read_env CACHE_LIMITER)" == redis || -z "$(read_env CACHE_LIMITER)" ]]; then set_env CACHE_LIMITER database; fi
  if [[ "$(read_env QUEUE_CONNECTION)" == redis && "$(read_env REDIS_HOST)" == 127.0.0.1 ]]; then
    if ! command -v redis-cli >/dev/null 2>&1 || ! redis-cli -h 127.0.0.1 ping >/dev/null 2>&1; then
      set_env QUEUE_CONNECTION database
    fi
  fi
fi

"$PHP_BIN" artisan down --retry=60 || true
trap '"$PHP_BIN" artisan up >/dev/null 2>&1 || true' EXIT
"$COMPOSER_BIN" install --no-dev --no-interaction --prefer-dist --optimize-autoloader
rm -f bootstrap/cache/config.php bootstrap/cache/events.php bootstrap/cache/routes-*.php
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan radiushub:credentials:reencrypt --force || warn "Não havia credenciais ou alguma credencial legada precisa de revisão manual."
"$PHP_BIN" artisan storage:link --force || true
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan queue:restart || true
"$PHP_BIN" artisan radiushub:doctor
"$PHP_BIN" artisan up
trap - EXIT
log "Upgrade 1.1.0 -> 1.2.0 concluído sem alterar APP_KEY."
