#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="${1:-$(pwd)}"
cd "$ROOT"
source "$ROOT/scripts/lib.sh"
[[ -f "$ENV_FILE" ]] || die ".env não encontrado."

stamp="$(date +%Y%m%d-%H%M%S)"
backup="storage/app/deploy/upgrade-1.3.5-to-1.4.0-${stamp}"

chmod +x scripts/*.sh artisan 2>/dev/null || true
mkdir -p "$backup" bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
cp -a "$ENV_FILE" "$backup/.env" 2>/dev/null || true
cp -a config "$backup/config" 2>/dev/null || true
cp -a .github/workflows "$backup/workflows" 2>/dev/null || true
cp -a VERSION "$backup/VERSION" 2>/dev/null || true

set_env APP_VERSION "$(cat VERSION)"
set_env DEPLOYMENT_MODE "$(read_env DEPLOYMENT_MODE native)"
if [[ "$(read_env APP_URL)" == https://* ]]; then
  set_env SESSION_SECURE_COOKIE true
fi
if [[ "$(read_env DEPLOYMENT_MODE native)" == native && "$(read_env REDIS_HOST)" == redis ]]; then
  set_env REDIS_HOST 127.0.0.1
  set_env CACHE_STORE database
  set_env CACHE_LIMITER database
  set_env QUEUE_CONNECTION database
fi
ensure_secrets
[[ -n "$(read_env PLATFORM_BOOTSTRAP_ENABLED)" ]] || set_env PLATFORM_BOOTSTRAP_ENABLED true
[[ -n "$(read_env SEED_DEFAULT_TENANT)" ]] || set_env SEED_DEFAULT_TENANT true
[[ -n "$(read_env SEED_DEFAULT_COMPANY)" ]] || set_env SEED_DEFAULT_COMPANY true
chmod 600 "$ENV_FILE"

php scripts/check-version-integrity.php
php scripts/check-migration-integrity.php
rm -f bootstrap/cache/config.php bootstrap/cache/events.php bootstrap/cache/routes-*.php
artisan_optimize_clear_safe php || true

composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

php scripts/check-version-integrity.php
php scripts/check-migration-integrity.php
php artisan migrate --force
php artisan db:seed --force
php artisan radiushub:bootstrap-platform
artisan_optimize_clear_safe php
php artisan config:cache
php artisan view:cache
php artisan queue:restart || true
php artisan radiushub:health --ready
php artisan radiushub:doctor

echo "RadiusHub Platform atualizado para 1.4.0 com Superadministrador, tenant e empresa reconciliados. Backup: $backup"
echo "Para criar um playground separado, consulte docs/PLAYGROUND.md."
