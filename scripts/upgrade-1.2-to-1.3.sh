#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/lib.sh"
cd "$PROJECT_ROOT"

[[ -f "$ENV_FILE" ]] || die ".env não encontrado."
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
command_exists "$PHP_BIN" || die "PHP CLI não encontrado."
command_exists "$COMPOSER_BIN" || die "Composer não encontrado."

backup_env
"$PROJECT_ROOT/scripts/backup.sh" --native || die "Backup automático falhou."

# Novas opções seguras. Não substitui valores personalizados existentes.
[[ -n "$(read_env MIKROTIK_SSH_CONNECTION_TIMEOUT)" ]] || set_env MIKROTIK_SSH_CONNECTION_TIMEOUT 10
[[ -n "$(read_env MIKROTIK_SSH_COMMAND_TIMEOUT)" ]] || set_env MIKROTIK_SSH_COMMAND_TIMEOUT 30
[[ -n "$(read_env MIKROTIK_SSH_KEY_BITS)" ]] || set_env MIKROTIK_SSH_KEY_BITS 3072
[[ -n "$(read_env MIKROTIK_SSH_ALLOW_PASSWORD_FALLBACK)" ]] || set_env MIKROTIK_SSH_ALLOW_PASSWORD_FALLBACK false
[[ -n "$(read_env MIKROTIK_SSH_REQUIRE_HOST_FINGERPRINT)" ]] || set_env MIKROTIK_SSH_REQUIRE_HOST_FINGERPRINT false
[[ -n "$(read_env MIKROTIK_AUTO_SYNC_ON_CHANGE)" ]] || set_env MIKROTIK_AUTO_SYNC_ON_CHANGE true
[[ -n "$(read_env MIKROTIK_SYNC_BATCH_SIZE)" ]] || set_env MIKROTIK_SYNC_BATCH_SIZE 100
[[ -n "$(read_env MIKROTIK_SYNC_CONTINUE_ON_ERROR)" ]] || set_env MIKROTIK_SYNC_CONTINUE_ON_ERROR true
[[ -n "$(read_env MIKROTIK_SESSION_CONTROL_DRIVER)" ]] || set_env MIKROTIK_SESSION_CONTROL_DRIVER ssh
[[ -n "$(read_env MIKROTIK_ALLOW_COA_FALLBACK)" ]] || set_env MIKROTIK_ALLOW_COA_FALLBACK false

"$PHP_BIN" artisan down --retry=60 || true
trap '"$PHP_BIN" artisan up >/dev/null 2>&1 || true' EXIT

"$COMPOSER_BIN" install --no-dev --no-interaction --prefer-dist --optimize-autoloader
rm -f bootstrap/cache/config.php bootstrap/cache/events.php bootstrap/cache/routes-*.php
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan db:seed --force
"$PHP_BIN" artisan asaas:webhooks:sync || warn "Alguns webhooks Asaas não puderam ser sincronizados automaticamente; execute php artisan asaas:webhooks:sync após validar as credenciais e a conectividade."
"$PHP_BIN" artisan storage:link --force || true
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan queue:restart || true
"$PHP_BIN" artisan radiushub:doctor || true
"$PHP_BIN" artisan up
trap - EXIT

log "Upgrade RadiusHub 1.2.x -> 1.3.0 concluído sem alterar APP_KEY ou credenciais existentes."
