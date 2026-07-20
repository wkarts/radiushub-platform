#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/lib.sh"
cd "$PROJECT_ROOT"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
[[ -f "$ENV_FILE" ]] || die ".env não encontrado."
bash "$PROJECT_ROOT/scripts/backup.sh" --native
chmod +x scripts/*.sh artisan 2>/dev/null || true
ensure_runtime_dirs
ensure_secrets
set_env APP_VERSION "$(cat VERSION)"
"$PHP_BIN" artisan down --retry=60 || true
trap '"$PHP_BIN" artisan up >/dev/null 2>&1 || true' EXIT
"$COMPOSER_BIN" install --no-dev --no-interaction --prefer-dist --optimize-autoloader
rm -f bootstrap/cache/config.php bootstrap/cache/events.php bootstrap/cache/routes-*.php
artisan_optimize_clear_safe "$PHP_BIN"
"$PHP_BIN" scripts/check-migration-integrity.php
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan radiushub:bootstrap-platform
"$PHP_BIN" artisan asaas:webhooks:sync || warn "Sincronização remota dos webhooks Asaas pendente."
"$PHP_BIN" artisan storage:link --force || true
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan queue:restart || true
"$PHP_BIN" artisan radiushub:doctor || true
"$PHP_BIN" artisan up
trap - EXIT
log "Atualização nativa concluída."
