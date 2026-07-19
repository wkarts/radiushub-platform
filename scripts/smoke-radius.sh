#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${ENV_FILE:-$PROJECT_ROOT/.env.playground}"
export ENV_FILE
source "$PROJECT_ROOT/scripts/lib.sh"
cd "$PROJECT_ROOT"

command_exists docker || die "Docker Engine não encontrado."
docker compose version >/dev/null 2>&1 || die "Docker Compose v2 não encontrado."
[[ -f "$ENV_FILE" ]] || die "Arquivo de ambiente não encontrado: $ENV_FILE"

username="$(read_env PLAYGROUND_NETWORK_USERNAME cliente.demo)"
password="$(read_env PLAYGROUND_NETWORK_PASSWORD ClienteDemo@123)"
nas_ip="$(read_env PLAYGROUND_NAS_IP_ADDRESS 127.0.0.10)"
session_id="${RADIUS_SMOKE_SESSION_ID:-playground-smoke-validation}"

compose=(docker compose --env-file "$ENV_FILE" -p radiushub-playground -f docker-compose.yml -f docker-compose.playground.yml --profile postgres)

run_auth() {
  "${compose[@]}" exec -T freeradius sh -s -- "$username" "$password" "$nas_ip" <<'SH'
set -eu
username="$1"
password="$2"
nas_ip="$3"
printf '%s\n' \
  "User-Name = \"$username\"" \
  "User-Password = \"$password\"" \
  "NAS-IP-Address = $nas_ip" \
  'Calling-Station-Id = "02:00:00:00:00:01"' \
  | radclient -x 127.0.0.1 auth "$RADIUS_LOCAL_SECRET"
SH
}

run_accounting() {
  local status="${1:-Start}"
  "${compose[@]}" exec -T freeradius sh -s -- "$username" "$nas_ip" "$session_id" "$status" <<'SH'
set -eu
username="$1"
nas_ip="$2"
session_id="$3"
status="$4"
printf '%s\n' \
  "Acct-Status-Type = $status" \
  "Acct-Session-Id = \"$session_id\"" \
  "Acct-Unique-Session-Id = \"$session_id-unique\"" \
  "User-Name = \"$username\"" \
  "NAS-IP-Address = $nas_ip" \
  'NAS-Port-Id = "ether-playground"' \
  'NAS-Port-Type = Ethernet' \
  'Calling-Station-Id = "02:00:00:00:00:01"' \
  'Called-Station-Id = "RadiusHub-Playground"' \
  'Service-Type = Framed-User' \
  'Framed-Protocol = PPP' \
  'Framed-IP-Address = 10.10.10.20' \
  'Acct-Session-Time = 5' \
  'Acct-Terminate-Cause = User-Request' \
  | radclient -x 127.0.0.1 acct "$RADIUS_LOCAL_SECRET"
SH
}

log "Aguardando FreeRADIUS aceitar requisições..."
# Fecha uma eventual sessão de smoke deixada por execução interrompida.
run_accounting Stop >/dev/null 2>&1 || true
auth_output=""
for attempt in $(seq 1 45); do
  if auth_output="$(run_auth 2>&1)" && grep -q 'Access-Accept' <<<"$auth_output"; then
    break
  fi
  if [[ "$attempt" -eq 45 ]]; then
    printf '%s\n' "$auth_output" >&2
    "${compose[@]}" logs --tail=200 freeradius >&2 || true
    die "O FreeRADIUS não retornou Access-Accept para o usuário do playground."
  fi
  sleep 2
done

acct_output="$(run_accounting Start 2>&1)" || {
  printf '%s\n' "$acct_output" >&2
  die "Falha ao enviar accounting Start ao FreeRADIUS."
}
grep -q 'Accounting-Response' <<<"$acct_output" || {
  printf '%s\n' "$acct_output" >&2
  die "O FreeRADIUS não retornou Accounting-Response."
}

"${compose[@]}" exec -T app php artisan radiushub:playground:verify --accounting-session="$session_id"
stop_output="$(run_accounting Stop 2>&1)" || true

printf 'SMOKE_RADIUS_OK\nUSUARIO=%s\nNAS_IP=%s\nSESSAO=%s\n' "$username" "$nas_ip" "$session_id"
