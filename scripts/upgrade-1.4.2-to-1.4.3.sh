#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="${1:-$(pwd)}"
cd "$ROOT"
source "$ROOT/scripts/lib.sh"
[[ -f "$ENV_FILE" ]] || die ".env não encontrado."

stamp="$(date +%Y%m%d-%H%M%S)"
backup="storage/app/deploy/upgrade-1.4.2-to-1.4.3-${stamp}"

chmod +x scripts/*.sh artisan docker/app/entrypoint.sh docker/freeradius/entrypoint.sh docker/freeradius/validate-templates.sh 2>/dev/null || true
mkdir -p "$backup" bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
cp -a "$ENV_FILE" "$backup/.env" 2>/dev/null || true
cp -a config "$backup/config" 2>/dev/null || true
cp -a resources/freeradius "$backup/freeradius-templates" 2>/dev/null || true
cp -a docker/freeradius "$backup/freeradius-container" 2>/dev/null || true
cp -a .github/workflows "$backup/workflows" 2>/dev/null || true
cp -a VERSION "$backup/VERSION" 2>/dev/null || true

set_env APP_VERSION "$(cat VERSION)"
chmod 600 "$ENV_FILE"

php scripts/check-version-integrity.php
php scripts/check-migration-integrity.php
php scripts/check-planning-compliance.php
php scripts/check-freeradius-templates.php

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

echo "RadiusHub Platform atualizado para 1.4.3. Nenhuma migration nova foi adicionada. Backup: $backup"
echo "Docker: reconstrua a imagem FreeRADIUS para validar o virtual server com o parser 3.2.x durante o build."
echo "FreeRADIUS nativo: reaplique com sudo SITE_USER=$(stat -c '%U' artisan) ./scripts/install-freeradius-native.sh"
