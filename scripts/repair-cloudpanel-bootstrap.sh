#!/usr/bin/env bash
set -Eeuo pipefail

source "$(dirname "$0")/lib.sh"
cd "$PROJECT_ROOT"

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

[[ -f "$ENV_FILE" ]] || die ".env não encontrado em $ENV_FILE."
command_exists "$PHP_BIN" || die "PHP CLI não encontrado."
command_exists "$COMPOSER_BIN" || die "Composer não encontrado."

backup_dir="storage/app/deploy/repair-bootstrap-$(date +%Y%m%d-%H%M%S)"
mkdir -p "$backup_dir"
cp -a "$ENV_FILE" "$backup_dir/.env"
cp -a database/migrations "$backup_dir/migrations"

chmod +x scripts/*.sh artisan 2>/dev/null || true
ensure_runtime_dirs
ensure_secrets
set_env APP_VERSION "$(cat VERSION)"
set_env DEPLOYMENT_MODE native

app_url="$(read_env APP_URL)"
if [[ "$app_url" == https://* ]]; then
  set_env SESSION_SECURE_COOKIE true
fi

# Corrige configurações Docker herdadas por instalações PHP nativas.
if [[ "$(read_env REDIS_HOST)" == redis ]]; then
  set_env REDIS_HOST 127.0.0.1
  set_env CACHE_STORE database
  set_env CACHE_LIMITER database
  set_env QUEUE_CONNECTION database
fi

# Instalações antigas não possuíam login explícito nem as flags de contexto inicial.
[[ -n "$(read_env SEED_ADMIN_LOGIN)" ]] || set_env SEED_ADMIN_LOGIN admin
[[ -n "$(read_env PLATFORM_BOOTSTRAP_ENABLED)" ]] || set_env PLATFORM_BOOTSTRAP_ENABLED true
[[ -n "$(read_env SEED_DEFAULT_TENANT)" ]] || set_env SEED_DEFAULT_TENANT true
[[ -n "$(read_env SEED_DEFAULT_COMPANY)" ]] || set_env SEED_DEFAULT_COMPANY true
chmod 600 "$ENV_FILE"

log "Instalando dependências e limpando caches antigos..."
"$COMPOSER_BIN" install --no-dev --no-interaction --prefer-dist --optimize-autoloader
rm -f bootstrap/cache/config.php bootstrap/cache/events.php bootstrap/cache/routes-*.php
artisan_optimize_clear_safe "$PHP_BIN" || true

log "Validando e reconciliando o banco..."
"$PHP_BIN" scripts/check-version-integrity.php
"$PHP_BIN" scripts/check-migration-integrity.php
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan radiushub:bootstrap-platform
"$PHP_BIN" artisan storage:link --force || true

log "Recriando caches e reiniciando filas..."
artisan_optimize_clear_safe "$PHP_BIN"
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan queue:restart || true
"$PHP_BIN" artisan radiushub:health --ready
"$PHP_BIN" artisan radiushub:doctor || true

cat <<INFO

Reparo concluído.
Superadministrador: $(read_env SEED_ADMIN_EMAIL)
Login: $(read_env SEED_ADMIN_LOGIN admin)
Tenant padrão: $(read_env SEED_TENANT_NAME 'RadiusHub Principal')
Empresa padrão: $(read_env SEED_COMPANY_TRADE_NAME 'Empresa Principal')
Backup: $backup_dir

A senha existente do usuário foi preservada. Para redefini-la de forma explícita,
altere SEED_ADMIN_PASSWORD no .env, defina SEED_ADMIN_FORCE_PASSWORD=true,
execute novamente php artisan radiushub:bootstrap-platform e volte a flag para false.
INFO
