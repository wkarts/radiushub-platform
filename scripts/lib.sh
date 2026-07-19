#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${ENV_FILE:-$PROJECT_ROOT/.env}"

log() { printf '\033[1;34m[RadiusHub]\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m[RadiusHub]\033[0m %s\n' "$*" >&2; }
die() { printf '\033[1;31m[RadiusHub]\033[0m %s\n' "$*" >&2; exit 1; }
command_exists() { command -v "$1" >/dev/null 2>&1; }

read_env() {
  local key="$1" default="${2:-}" value
  value="$(grep -E "^${key}=" "$ENV_FILE" 2>/dev/null | tail -n1 | cut -d= -f2- || true)"
  value="${value%\"}"; value="${value#\"}"
  printf '%s' "${value:-$default}"
}

set_env() {
  local key="$1" value="$2" escaped
  touch "$ENV_FILE"
  escaped="$(printf '%s' "$value" | sed -e 's/[&|]/\\&/g')"
  if grep -qE "^${key}=" "$ENV_FILE"; then
    sed -i "s|^${key}=.*|${key}=${escaped}|" "$ENV_FILE"
  else
    printf '%s=%s\n' "$key" "$value" >> "$ENV_FILE"
  fi
}

random_base64() { openssl rand -base64 "${1:-32}" | tr -d '\n'; }
random_hex() { openssl rand -hex "${1:-24}"; }
random_password() {
  local value
  value="$(openssl rand -hex 16)"
  printf '%s' "${value:0:24}Aa1!"
}

ensure_runtime_dirs() {
  mkdir -p "$PROJECT_ROOT/storage/framework/cache/data" \
    "$PROJECT_ROOT/storage/framework/sessions" \
    "$PROJECT_ROOT/storage/framework/views" \
    "$PROJECT_ROOT/storage/logs" \
    "$PROJECT_ROOT/bootstrap/cache"
}

ensure_secrets() {
  local current
  current="$(read_env APP_KEY)"
  [[ -n "$current" ]] || set_env APP_KEY "base64:$(random_base64 32)"

  current="$(read_env RADIUS_CREDENTIAL_KEY)"
  [[ -n "$current" && "$current" != change-this* ]] || set_env RADIUS_CREDENTIAL_KEY "$(random_base64 48)"

  current="$(read_env RADIUS_LOCAL_SECRET)"
  [[ -n "$current" && "$current" != change-this* ]] || set_env RADIUS_LOCAL_SECRET "$(random_base64 32)"

  current="$(read_env SEED_ADMIN_PASSWORD)"
  [[ -n "$current" && "$current" != 'ChangeMe@123!' ]] || set_env SEED_ADMIN_PASSWORD "$(random_password)"
}

backup_env() {
  [[ -f "$ENV_FILE" ]] || return 0
  cp -a "$ENV_FILE" "${ENV_FILE}.backup-$(date +%Y%m%d-%H%M%S)"
}

validate_no_placeholders() {
  local keys=(APP_KEY APP_URL DB_DATABASE DB_USERNAME DB_PASSWORD RADIUS_CREDENTIAL_KEY RADIUS_LOCAL_SECRET SEED_ADMIN_EMAIL SEED_ADMIN_PASSWORD)
  local key value
  for key in "${keys[@]}"; do
    value="$(read_env "$key")"
    [[ -n "$value" ]] || die "Variável obrigatória vazia: $key"
    [[ "$value" != change-this* ]] || die "Substitua o valor provisório de $key no arquivo .env."
  done

  local app_url
  app_url="$(read_env APP_URL)"
  if [[ "$app_url" == *"example.com"* ]]; then
    die "Substitua APP_URL pelo domínio ou endereço real da instalação."
  fi
}
