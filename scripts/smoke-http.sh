#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${1:-${APP_URL:-http://127.0.0.1:8080}}"
LOGIN="${PLAYGROUND_ADMIN_EMAIL:-${SEED_ADMIN_EMAIL:-admin@playground.local}}"
PASSWORD="${PLAYGROUND_ADMIN_PASSWORD:-${SEED_ADMIN_PASSWORD:-}}"

[[ -n "$PASSWORD" ]] || { echo "PLAYGROUND_ADMIN_PASSWORD/SEED_ADMIN_PASSWORD ausente." >&2; exit 1; }
command -v curl >/dev/null 2>&1 || { echo "curl não encontrado." >&2; exit 1; }

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
jar="$tmp/cookies.txt"
login_html="$tmp/login.html"
headers="$tmp/headers.txt"

curl -fsS "$BASE_URL/health/live" > "$tmp/live.json"
curl -fsS "$BASE_URL/health/ready" > "$tmp/ready.json"
curl -fsS -c "$jar" "$BASE_URL/login" > "$login_html"

token="$(grep -oE 'name="_token" value="[^"]+"' "$login_html" | head -n1 | sed -E 's/.*value="([^"]+)"/\1/')"
[[ -n "$token" ]] || { echo "Token CSRF não encontrado na página de login." >&2; exit 1; }

status="$(curl -sS -o /dev/null -D "$headers" -b "$jar" -c "$jar" \
  -w '%{http_code}' \
  -X POST "$BASE_URL/login" \
  --data-urlencode "_token=$token" \
  --data-urlencode "login=$LOGIN" \
  --data-urlencode "password=$PASSWORD")"

[[ "$status" == "302" ]] || { echo "Login retornou HTTP $status." >&2; cat "$headers" >&2; exit 1; }
location="$(awk 'BEGIN{IGNORECASE=1}/^location:/{gsub("\r",""); print $2}' "$headers" | tail -n1)"
[[ -n "$location" ]] || location="$BASE_URL/platform/dashboard"
[[ "$location" == http* ]] || location="${BASE_URL%/}${location}"

curl -fsS -b "$jar" "$location" > "$tmp/dashboard.html"
grep -qi 'RadiusHub' "$tmp/dashboard.html" || { echo "Dashboard autenticado não contém a identidade RadiusHub." >&2; exit 1; }

printf 'SMOKE_HTTP_OK\nURL=%s\nLOGIN=%s\nDESTINO=%s\n' "$BASE_URL" "$LOGIN" "$location"
