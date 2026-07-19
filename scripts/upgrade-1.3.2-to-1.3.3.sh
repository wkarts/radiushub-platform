#!/usr/bin/env bash
set -Eeuo pipefail

ROOT="${1:-$(pwd)}"
cd "$ROOT"

stamp="$(date +%Y%m%d-%H%M%S)"
backup="storage/app/deploy/upgrade-1.3.2-to-1.3.3-${stamp}"
obsolete="database/migrations/2026_07_19_000800_secure_asaas_webhooks_by_gateway.php"

mkdir -p "$backup" bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
cp -a .env "$backup/.env" 2>/dev/null || true
cp -a database/migrations "$backup/migrations" 2>/dev/null || true

# A versão 1.3.2 continha acidentalmente duas migrations 000800 para o mesmo schema.
# A migration singular abaixo é a versão canônica, retomável e compatível com bancos já atualizados.
if [[ -f "$obsolete" ]]; then
    cp -a "$obsolete" "$backup/"
    rm -f "$obsolete"
fi

php scripts/check-migration-integrity.php
rm -f bootstrap/cache/config.php bootstrap/cache/events.php bootstrap/cache/routes-*.php
php artisan optimize:clear || true
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev
php scripts/check-migration-integrity.php
php artisan migrate --force
php artisan db:seed --force
php artisan optimize:clear
php artisan config:cache
php artisan view:cache
php artisan queue:restart || true
php artisan radiushub:doctor

echo "RadiusHub Platform atualizado para 1.3.3. Backup: $backup"
