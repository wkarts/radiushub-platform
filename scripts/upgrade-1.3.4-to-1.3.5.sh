#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="${1:-$(pwd)}"
cd "$ROOT"

stamp="$(date +%Y%m%d-%H%M%S)"
backup="storage/app/deploy/upgrade-1.3.4-to-1.3.5-${stamp}"

mkdir -p "$backup" bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
cp -a .env "$backup/.env" 2>/dev/null || true
cp -a .github/workflows "$backup/workflows" 2>/dev/null || true
cp -a VERSION "$backup/VERSION" 2>/dev/null || true

php scripts/check-version-integrity.php
php scripts/check-migration-integrity.php
rm -f bootstrap/cache/config.php bootstrap/cache/events.php bootstrap/cache/routes-*.php
php artisan optimize:clear || true
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
php artisan migrate --force
php artisan db:seed --force
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
php artisan queue:restart || true
php artisan radiushub:health --ready
php artisan radiushub:doctor

echo "RadiusHub Platform atualizado para 1.3.5. Backup: $backup"
