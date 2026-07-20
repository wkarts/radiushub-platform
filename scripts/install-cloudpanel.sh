#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/lib.sh"
cd "$PROJECT_ROOT"

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
command_exists "$PHP_BIN" || die "PHP CLI não encontrado."
command_exists "$COMPOSER_BIN" || die "Composer não encontrado."
command_exists openssl || die "OpenSSL não encontrado."

php_version="$($PHP_BIN -r 'echo PHP_VERSION_ID;')"
[[ "$php_version" -ge 80300 ]] || die "PHP 8.3 ou superior é obrigatório."

required_ext=(pdo mbstring openssl json intl curl dom bcmath fileinfo)
for ext in "${required_ext[@]}"; do
  "$PHP_BIN" -m | grep -qi "^${ext}$" || die "Extensão PHP ausente: $ext"
done

if [[ ! -f "$ENV_FILE" ]]; then
  cp .env.cloudpanel.example "$ENV_FILE"
fi
backup_env
chmod +x scripts/*.sh artisan 2>/dev/null || true
ensure_runtime_dirs
ensure_secrets
set_env APP_VERSION "$(cat VERSION)"
set_env DEPLOYMENT_MODE "$(read_env DEPLOYMENT_MODE native)"
set_env CACHE_STORE "$(read_env CACHE_STORE database)"
set_env CACHE_LIMITER "$(read_env CACHE_LIMITER database)"
set_env QUEUE_CONNECTION "$(read_env QUEUE_CONNECTION database)"
set_env REDIS_HOST "$(read_env REDIS_HOST 127.0.0.1)"

if [[ -t 0 ]]; then
  current="$(read_env APP_URL https://radius.example.com)"
  read -r -p "URL pública [$current]: " answer; [[ -n "$answer" ]] && set_env APP_URL "$answer"
  current="$(read_env DB_CONNECTION mysql)"
  read -r -p "Banco mysql ou pgsql [$current]: " answer; [[ -n "$answer" ]] && set_env DB_CONNECTION "$answer"
  driver="$(read_env DB_CONNECTION)"
  if [[ "$driver" == pgsql ]]; then default_port=5432; else default_port=3306; fi
  current="$(read_env DB_HOST 127.0.0.1)"; read -r -p "Host do banco [$current]: " answer; [[ -n "$answer" ]] && set_env DB_HOST "$answer"
  current="$(read_env DB_PORT "$default_port")"; read -r -p "Porta do banco [$current]: " answer; [[ -n "$answer" ]] && set_env DB_PORT "$answer"
  current="$(read_env DB_DATABASE radiushub)"; read -r -p "Nome do banco [$current]: " answer; [[ -n "$answer" ]] && set_env DB_DATABASE "$answer"
  current="$(read_env DB_USERNAME radiushub)"; read -r -p "Usuário do banco [$current]: " answer; [[ -n "$answer" ]] && set_env DB_USERNAME "$answer"
  read -r -s -p "Senha do banco (Enter mantém a atual): " answer; echo; [[ -n "$answer" ]] && set_env DB_PASSWORD "$answer"
  current="$(read_env SEED_ADMIN_EMAIL admin@example.com)"; read -r -p "E-mail do superadministrador [$current]: " answer; [[ -n "$answer" ]] && set_env SEED_ADMIN_EMAIL "$answer"
  current="$(read_env SEED_ADMIN_LOGIN admin)"; read -r -p "Login do superadministrador [$current]: " answer; [[ -n "$answer" ]] && set_env SEED_ADMIN_LOGIN "$answer"
  read -r -s -p "Senha inicial do superadministrador (Enter mantém/gera): " answer; echo; [[ -n "$answer" ]] && set_env SEED_ADMIN_PASSWORD "$answer"
fi

driver="$(read_env DB_CONNECTION)"
case "$driver" in
  mysql) db_extension=pdo_mysql ;;
  pgsql) db_extension=pdo_pgsql ;;
  *) die "DB_CONNECTION deve ser mysql ou pgsql para a instalação nativa." ;;
esac
"$PHP_BIN" -m | grep -qi "^${db_extension}$" || die "Extensão PHP ausente para o banco selecionado: $db_extension"

if [[ "$(read_env CACHE_STORE database)" == "redis" || "$(read_env QUEUE_CONNECTION database)" == "redis" ]]; then
  "$PHP_BIN" -m | grep -qi '^redis$' || die "A extensão PHP redis é obrigatória quando cache ou filas usam Redis."
fi

app_url="$(read_env APP_URL)"
if [[ "$app_url" == https://* ]]; then
  set_env SESSION_SECURE_COOKIE true
else
  set_env SESSION_SECURE_COOKIE false
fi
validate_no_placeholders
chmod 600 "$ENV_FILE"

log "Instalando dependências Composer..."
"$COMPOSER_BIN" install --no-dev --no-interaction --prefer-dist --optimize-autoloader

log "Limpando caches e validando conexão..."
rm -f bootstrap/cache/config.php bootstrap/cache/events.php bootstrap/cache/routes-*.php
artisan_optimize_clear_safe "$PHP_BIN"
"$PHP_BIN" scripts/check-migration-integrity.php
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan db:seed --force
"$PHP_BIN" artisan radiushub:bootstrap-platform
"$PHP_BIN" artisan storage:link --force || true
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan radiushub:health --ready
"$PHP_BIN" artisan radiushub:doctor || true

site_user="$(id -un)"
site_group="$(id -gn)"
chown -R "$site_user:$site_group" storage bootstrap/cache 2>/dev/null || true
find storage bootstrap/cache -type d -exec chmod 775 {} \;
find storage bootstrap/cache -type f -exec chmod 664 {} \;

mkdir -p storage/app/deploy
sed \
  -e "s|__PROJECT_ROOT__|$PROJECT_ROOT|g" \
  -e "s|__SITE_USER__|$site_user|g" \
  -e "s|__PHP_BIN__|$(command -v "$PHP_BIN")|g" \
  deploy/cloudpanel/supervisor-radiushub.conf > storage/app/deploy/supervisor-radiushub.conf
sed \
  -e "s|__PROJECT_ROOT__|$PROJECT_ROOT|g" \
  -e "s|__PHP_BIN__|$(command -v "$PHP_BIN")|g" \
  deploy/cloudpanel/cron.txt > storage/app/deploy/cron.txt
sed \
  -e "s|__PROJECT_ROOT__|$PROJECT_ROOT|g" \
  deploy/cloudpanel/nginx-vhost.conf > storage/app/deploy/nginx-native.conf
sed \
  -e "s|__APP_PORT__|$(read_env APP_PORT 8080)|g" \
  deploy/cloudpanel/nginx-docker-reverse-proxy.conf > storage/app/deploy/nginx-docker-reverse-proxy.conf

cat <<INFO

Instalação nativa concluída.
URL: $(read_env APP_URL)
E-mail inicial: $(read_env SEED_ADMIN_EMAIL)
Login inicial: $(read_env SEED_ADMIN_LOGIN admin)
Senha inicial: $(read_env SEED_ADMIN_PASSWORD)

Próximos comandos administrativos:
  sudo scripts/install-freeradius-native.sh
  sudo cp storage/app/deploy/supervisor-radiushub.conf /etc/supervisor/conf.d/radiushub.conf
  sudo supervisorctl reread && sudo supervisorctl update

Configure no CloudPanel o document root para:
  $PROJECT_ROOT/public

Snippets gerados:
  storage/app/deploy/nginx-native.conf
  storage/app/deploy/nginx-docker-reverse-proxy.conf
  storage/app/deploy/supervisor-radiushub.conf
  storage/app/deploy/cron.txt

Validação pública:
  $(read_env APP_URL)/health/live
  $(read_env APP_URL)/health/ready
INFO
