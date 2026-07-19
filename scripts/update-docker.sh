#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/lib.sh"
cd "$PROJECT_ROOT"
[[ -f "$ENV_FILE" ]] || die ".env não encontrado."
DB_ENGINE="$(read_env COMPOSE_PROFILES)"
[[ "$DB_ENGINE" == mysql || "$DB_ENGINE" == postgres ]] || DB_ENGINE="$([[ "$(read_env DB_CONNECTION)" == mysql ]] && echo mysql || echo postgres)"
compose=(docker compose --env-file "$ENV_FILE" --profile "$DB_ENGINE")
"${compose[@]}" config --quiet

bash "$PROJECT_ROOT/scripts/backup.sh" --docker
set_env APP_VERSION "$(cat VERSION)"
set_env RADIUSHUB_TAG "$(cat VERSION)"
if [[ "${1:-}" == "--build" ]]; then
  "${compose[@]}" build --pull app web freeradius
else
  "${compose[@]}" pull app web worker scheduler freeradius || {
    warn "Imagens remotas indisponíveis; compilando localmente."
    "${compose[@]}" build --pull app web freeradius
  }
fi
"${compose[@]}" up -d "$DB_ENGINE" redis
"${compose[@]}" run --rm -e AUTO_MIGRATE=false -e AUTO_SEED=false app php scripts/check-migration-integrity.php
"${compose[@]}" run --rm -e AUTO_MIGRATE=false -e AUTO_SEED=false app php artisan migrate --force
"${compose[@]}" run --rm -e AUTO_MIGRATE=false -e AUTO_SEED=false app php artisan radiushub:bootstrap-platform
"${compose[@]}" run --rm -e AUTO_MIGRATE=false -e AUTO_SEED=false app php artisan asaas:webhooks:sync || warn "Sincronização remota dos webhooks Asaas pendente."
"${compose[@]}" run --rm -e AUTO_MIGRATE=false -e AUTO_SEED=false app php artisan optimize:clear
"${compose[@]}" up -d --remove-orphans
ready_url="http://127.0.0.1:$(read_env APP_PORT 8080)/health/ready"
for attempt in $(seq 1 90); do
  if curl -fsS "$ready_url" >/dev/null 2>&1; then break; fi
  if [[ "$attempt" -eq 90 ]]; then
    "${compose[@]}" ps
    "${compose[@]}" logs --tail=200 app web worker scheduler freeradius
    die "A aplicação não ficou pronta após a atualização."
  fi
  sleep 2
done
"${compose[@]}" exec -T app php artisan radiushub:health --ready
"${compose[@]}" exec -T app php artisan radiushub:doctor || true
log "Atualização Docker concluída."
