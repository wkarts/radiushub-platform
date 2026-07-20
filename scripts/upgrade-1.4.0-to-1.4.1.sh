#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="${1:-$(pwd)}"
cd "$ROOT"
source "$ROOT/scripts/lib.sh"
[[ -f "$ENV_FILE" ]] || die ".env não encontrado."

stamp="$(date +%Y%m%d-%H%M%S)"
backup="storage/app/deploy/upgrade-1.4.0-to-1.4.1-${stamp}"

chmod +x scripts/*.sh artisan docker/app/entrypoint.sh docker/freeradius/entrypoint.sh 2>/dev/null || true
mkdir -p "$backup" bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
cp -a "$ENV_FILE" "$backup/.env" 2>/dev/null || true
cp -a config "$backup/config" 2>/dev/null || true
cp -a docker/freeradius "$backup/freeradius" 2>/dev/null || true
cp -a .github/workflows "$backup/workflows" 2>/dev/null || true
cp -a VERSION "$backup/VERSION" 2>/dev/null || true

set_env APP_VERSION "$(cat VERSION)"
chmod 600 "$ENV_FILE"

php scripts/check-version-integrity.php
php scripts/check-migration-integrity.php
php scripts/check-planning-compliance.php

rm -f bootstrap/cache/config.php bootstrap/cache/events.php bootstrap/cache/routes-*.php
artisan_optimize_clear_safe php || true

composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

php artisan migrate --force
php artisan radiushub:bootstrap-platform
artisan_optimize_clear_safe php
php artisan config:cache
php artisan view:cache
php artisan queue:restart || true
php artisan radiushub:health --ready
php artisan radiushub:doctor

echo "RadiusHub Platform atualizado para 1.4.1. Nenhuma migration nova foi adicionada. Backup: $backup"
echo "O FreeRADIUS nativo deve ser reaplicado quando utilizado: sudo SITE_USER=$(stat -c '%U' artisan) ./scripts/install-freeradius-native.sh"
