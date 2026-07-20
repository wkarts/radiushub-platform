#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/lib.sh"
cd "$PROJECT_ROOT"

DB_ENGINE="postgres"
USE_IMAGES=false
while [[ $# -gt 0 ]]; do
  case "$1" in
    --mysql) DB_ENGINE=mysql ;;
    --postgres|--postgresql) DB_ENGINE=postgres ;;
    --pull-images) USE_IMAGES=true ;;
    --help)
      cat <<HELP
Uso: $0 [--postgres|--mysql] [--pull-images]
  --postgres     PostgreSQL 17 (padrão)
  --mysql        MySQL 8.4
  --pull-images  Usa imagens do GHCR; sem essa opção, compila localmente
HELP
      exit 0 ;;
    *) die "Opção desconhecida: $1" ;;
  esac
  shift
done

command_exists docker || die "Docker não encontrado."
docker compose version >/dev/null 2>&1 || die "Docker Compose v2 não encontrado."
command_exists openssl || die "OpenSSL não encontrado."

if [[ ! -f "$ENV_FILE" ]]; then
  cp ".env.docker.${DB_ENGINE}.example" "$ENV_FILE"
fi
backup_env
ensure_runtime_dirs
ensure_secrets

set_env APP_VERSION "$(cat VERSION)"
set_env RADIUSHUB_TAG "$(cat VERSION)"
set_env COMPOSE_PROFILES "$DB_ENGINE"
set_env DEPLOYMENT_MODE docker
if [[ "$ENV_FILE" == "$PROJECT_ROOT/"* ]]; then
  set_env RADIUSHUB_ENV_FILE "${ENV_FILE#"$PROJECT_ROOT/"}"
else
  set_env RADIUSHUB_ENV_FILE "$ENV_FILE"
fi
if [[ "$DB_ENGINE" == mysql ]]; then
  set_env DB_CONNECTION mysql
  set_env DB_HOST mysql
  set_env DB_PORT 3306
  root_password="$(read_env MYSQL_ROOT_PASSWORD)"
  [[ -n "$root_password" && "$root_password" != change-this* ]] || set_env MYSQL_ROOT_PASSWORD "$(random_hex 24)"
else
  set_env DB_CONNECTION pgsql
  set_env DB_HOST postgres
  set_env DB_PORT 5432
fi

password="$(read_env DB_PASSWORD)"
[[ -n "$password" && "$password" != change-this* ]] || set_env DB_PASSWORD "$(random_hex 24)"

if [[ -t 0 && "${NON_INTERACTIVE:-false}" != true ]]; then
  current_url="$(read_env APP_URL http://localhost:8080)"
  read -r -p "URL pública da aplicação [$current_url]: " answer
  [[ -n "$answer" ]] && set_env APP_URL "$answer"
  current_email="$(read_env SEED_ADMIN_EMAIL admin@localhost)"
  read -r -p "E-mail do superadministrador [$current_email]: " answer
  [[ -n "$answer" ]] && set_env SEED_ADMIN_EMAIL "$answer"
  current_login="$(read_env SEED_ADMIN_LOGIN admin)"
  read -r -p "Login do superadministrador [$current_login]: " answer
  [[ -n "$answer" ]] && set_env SEED_ADMIN_LOGIN "$answer"
fi

app_url="$(read_env APP_URL)"
if [[ "$app_url" == https://* ]]; then
  set_env SESSION_SECURE_COOKIE true
else
  set_env SESSION_SECURE_COOKIE false
fi
validate_no_placeholders
chmod 600 "$ENV_FILE"

compose=(docker compose --env-file "$ENV_FILE" --profile "$DB_ENGINE")
"${compose[@]}" config --quiet
if [[ "$USE_IMAGES" == true ]]; then
  log "Baixando imagens publicadas no GHCR..."
  "${compose[@]}" pull app web worker scheduler freeradius redis "$DB_ENGINE"
else
  log "Compilando as imagens localmente..."
  "${compose[@]}" build --pull app web freeradius
fi

log "Iniciando banco e Redis..."
"${compose[@]}" up -d "$DB_ENGINE" redis

log "Executando migrations e seed inicial..."
"${compose[@]}" run --rm -e AUTO_MIGRATE=false -e AUTO_SEED=false app php scripts/check-migration-integrity.php
"${compose[@]}" run --rm -e AUTO_MIGRATE=false -e AUTO_SEED=false app php artisan migrate --force
"${compose[@]}" run --rm -e AUTO_MIGRATE=false -e AUTO_SEED=false app php artisan db:seed --force
"${compose[@]}" run --rm -e AUTO_MIGRATE=false -e AUTO_SEED=false app php artisan radiushub:bootstrap-platform
"${compose[@]}" run --rm -e AUTO_MIGRATE=false -e AUTO_SEED=false app php artisan radiushub:doctor || true

log "Iniciando todos os serviços..."
"${compose[@]}" up -d --remove-orphans

log "Aguardando readiness HTTP..."
ready_url="http://127.0.0.1:$(read_env APP_PORT 8080)/health/ready"
for attempt in $(seq 1 90); do
  if curl -fsS "$ready_url" >/dev/null 2>&1; then break; fi
  if [[ "$attempt" -eq 90 ]]; then
    "${compose[@]}" ps
    "${compose[@]}" logs --tail=200 app web worker scheduler freeradius
    die "A aplicação não ficou pronta em até 180 segundos."
  fi
  sleep 2
done
"${compose[@]}" exec -T app php artisan radiushub:health --ready
"${compose[@]}" exec -T app php artisan radiushub:doctor || true

cat <<INFO

Instalação Docker concluída.
URL: $(read_env APP_URL)
Usuário inicial: $(read_env SEED_ADMIN_EMAIL)
Senha inicial: $(read_env SEED_ADMIN_PASSWORD)

Troque a senha no primeiro acesso.
Verifique: docker compose --profile $DB_ENGINE ps
Logs:     docker compose --profile $DB_ENGINE logs -f app worker freeradius
INFO
