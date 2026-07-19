#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/lib.sh"
cd "$PROJECT_ROOT"
[[ -f "$ENV_FILE" ]] || die ".env não encontrado."
DB_ENGINE="$(read_env COMPOSE_PROFILES)"
[[ "$DB_ENGINE" == mysql || "$DB_ENGINE" == postgres ]] || DB_ENGINE="$([[ "$(read_env DB_CONNECTION)" == mysql ]] && echo mysql || echo postgres)"
compose=(docker compose --profile "$DB_ENGINE")

"$PROJECT_ROOT/scripts/backup.sh" --docker
if [[ "${1:-}" == "--build" ]]; then
  "${compose[@]}" build --pull app web freeradius
else
  "${compose[@]}" pull app web worker scheduler freeradius || {
    warn "Imagens remotas indisponíveis; compilando localmente."
    "${compose[@]}" build --pull app web freeradius
  }
fi
"${compose[@]}" up -d "$DB_ENGINE" redis
"${compose[@]}" run --rm -e AUTO_MIGRATE=false -e AUTO_SEED=false app php artisan migrate --force
"${compose[@]}" run --rm -e AUTO_MIGRATE=false -e AUTO_SEED=false app php artisan asaas:webhooks:sync || warn "Sincronização remota dos webhooks Asaas pendente."
"${compose[@]}" run --rm -e AUTO_MIGRATE=false -e AUTO_SEED=false app php artisan optimize:clear
"${compose[@]}" up -d --remove-orphans
"${compose[@]}" exec -T app php artisan radiushub:doctor || true
log "Atualização Docker concluída."
