#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/lib.sh"
cd "$PROJECT_ROOT"
MODE="$(read_env DEPLOYMENT_MODE native)"
if [[ "$MODE" == docker ]]; then
  PROFILE="$(read_env COMPOSE_PROFILES)"; [[ -n "$PROFILE" ]] || PROFILE="$([[ "$(read_env DB_CONNECTION)" == mysql ]] && echo mysql || echo postgres)"
  docker compose --profile "$PROFILE" ps
  docker compose --profile "$PROFILE" exec -T app php artisan radiushub:doctor --strict
  docker compose --profile "$PROFILE" exec -T freeradius freeradius -XC
else
  php artisan radiushub:doctor --strict
  if command_exists freeradius; then freeradius -XC; fi
  systemctl --no-pager --full status freeradius 2>/dev/null | head -30 || true
fi
