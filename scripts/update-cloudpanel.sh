#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/lib.sh"
cd "$PROJECT_ROOT"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
[[ -f "$ENV_FILE" ]] || die ".env não encontrado."
"$PROJECT_ROOT/scripts/backup.sh" --native
"$PHP_BIN" artisan down --retry=60 || true
trap '"$PHP_BIN" artisan up >/dev/null 2>&1 || true' EXIT
"$COMPOSER_BIN" install --no-dev --no-interaction --prefer-dist --optimize-autoloader
rm -f bootstrap/cache/config.php bootstrap/cache/events.php bootstrap/cache/routes-*.php
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan storage:link --force || true
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan queue:restart || true
"$PHP_BIN" artisan radiushub:doctor || true
"$PHP_BIN" artisan up
trap - EXIT
log "Atualização nativa concluída."
