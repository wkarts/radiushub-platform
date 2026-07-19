#!/usr/bin/env sh
set -eu

as_www_data() {
  gosu www-data "$@"
}

require_env() {
  key="$1"
  eval "value=\${$key:-}"
  if [ -z "$value" ]; then
    echo "Variável obrigatória ausente: $key" >&2
    exit 1
  fi
}

wait_for_database() {
  as_www_data php -r '
    $driver = getenv("DB_CONNECTION") ?: "pgsql";
    $host = getenv("DB_HOST") ?: "127.0.0.1";
    $port = getenv("DB_PORT") ?: ($driver === "mysql" ? "3306" : "5432");
    $db = getenv("DB_DATABASE") ?: "radiushub";
    $user = getenv("DB_USERNAME") ?: "radiushub";
    $pass = getenv("DB_PASSWORD") ?: "";
    $dsn = $driver === "mysql"
      ? "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4"
      : "pgsql:host={$host};port={$port};dbname={$db}";
    for ($i = 0; $i < 90; $i++) {
      try { new PDO($dsn, $user, $pass, [PDO::ATTR_TIMEOUT => 3]); exit(0); }
      catch (Throwable $e) { sleep(2); }
    }
    fwrite(STDERR, strtoupper($driver)." indisponível após 180 segundos.\n");
    exit(1);
  '
}

prepare_directories() {
  mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
  chown -R www-data:www-data storage bootstrap/cache
}

require_env APP_KEY
require_env DB_CONNECTION
require_env DB_HOST
require_env DB_DATABASE
require_env DB_USERNAME
prepare_directories
wait_for_database

# Evita configuração empacotada com valores de outro ambiente.
rm -f bootstrap/cache/config.php bootstrap/cache/events.php bootstrap/cache/routes-*.php

if [ "${AUTO_MIGRATE:-false}" = "true" ]; then
  as_www_data php artisan migrate --force
fi

if [ "${AUTO_SEED:-false}" = "true" ]; then
  as_www_data php artisan db:seed --force
fi

as_www_data php artisan storage:link --force >/dev/null 2>&1 || true
as_www_data php artisan config:cache
as_www_data php artisan view:cache

exec gosu www-data "$@"
