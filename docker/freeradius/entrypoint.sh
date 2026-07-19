#!/usr/bin/env bash
set -euo pipefail

CONFIG_ROOT="${FREERADIUS_CONFIG_ROOT:-/etc/freeradius/3.0}"
DRIVER="${DB_CONNECTION:-pgsql}"
case "$DRIVER" in
  pgsql|postgres|postgresql) DIALECT=postgresql; DRIVER=pgsql ;;
  mysql|mariadb) DIALECT=mysql; DRIVER=mysql ;;
  *) echo "DB_CONNECTION inválido para FreeRADIUS: $DRIVER" >&2; exit 1 ;;
esac

escape_sed() { printf '%s' "$1" | sed -e 's/[\\&|]/\\&/g'; }
escape_config() { printf '%s' "$1" | sed -e 's/\\/\\\\/g' -e 's/"/\\"/g'; }
render() {
  local src="$1" dst="$2"
  mkdir -p "$(dirname "$dst")"
  cp "$src" "$dst"
  sed -i \
    -e "s|@@DB_HOST@@|$(escape_sed "$(escape_config "${DB_HOST:-127.0.0.1}")")|g" \
    -e "s|@@DB_PORT@@|$(escape_sed "${DB_PORT:-5432}")|g" \
    -e "s|@@DB_DATABASE@@|$(escape_sed "$(escape_config "${DB_DATABASE:-radiushub}")")|g" \
    -e "s|@@DB_USERNAME@@|$(escape_sed "$(escape_config "${DB_USERNAME:-radiushub}")")|g" \
    -e "s|@@DB_PASSWORD@@|$(escape_sed "$(escape_config "${DB_PASSWORD:-}")")|g" \
    -e "s|@@RADIUS_CREDENTIAL_KEY@@|$(escape_sed "${RADIUS_CREDENTIAL_KEY:-}")|g" \
    -e "s|@@RADIUS_LOCAL_SECRET@@|$(escape_sed "$(escape_config "${RADIUS_LOCAL_SECRET:-local-secret}")")|g" \
    "$dst"
}

wait_for_database() {
  local attempts=0
  while true; do
    if [ "$DRIVER" = pgsql ]; then
      PGPASSWORD="${DB_PASSWORD:-}" psql -h "${DB_HOST:-postgres}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-radiushub}" -d "${DB_DATABASE:-radiushub}" -Atqc 'SELECT 1' >/dev/null 2>&1 && break
    else
      MYSQL_PWD="${DB_PASSWORD:-}" mysql -h "${DB_HOST:-mysql}" -P "${DB_PORT:-3306}" -u "${DB_USERNAME:-radiushub}" "${DB_DATABASE:-radiushub}" -Nse 'SELECT 1' >/dev/null 2>&1 && break
    fi
    attempts=$((attempts + 1))
    [ "$attempts" -ge 90 ] && { echo "$DIALECT indisponível para FreeRADIUS." >&2; exit 1; }
    sleep 2
  done
}

latest_nas_update() {
  if [ "$DRIVER" = pgsql ]; then
    PGPASSWORD="${DB_PASSWORD:-}" psql -h "${DB_HOST:-postgres}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-radiushub}" -d "${DB_DATABASE:-radiushub}" -Atqc "SELECT COALESCE(MAX(updated_at)::text, '') FROM mikrotik_devices WHERE deleted_at IS NULL" 2>/dev/null || true
  else
    MYSQL_PWD="${DB_PASSWORD:-}" mysql -h "${DB_HOST:-mysql}" -P "${DB_PORT:-3306}" -u "${DB_USERNAME:-radiushub}" "${DB_DATABASE:-radiushub}" -Nse "SELECT COALESCE(MAX(updated_at), '') FROM mikrotik_devices WHERE deleted_at IS NULL" 2>/dev/null || true
  fi
}

watch_radius_clients() {
  local interval="${RADIUS_CLIENT_RELOAD_SECONDS:-30}" previous="" current=""
  while sleep "$interval"; do
    current="$(latest_nas_update)"
    if [ -n "$previous" ] && [ "$current" != "$previous" ]; then
      echo "Alteração de NAS detectada; recarregando FreeRADIUS."
      kill -HUP 1 >/dev/null 2>&1 || true
    fi
    previous="$current"
  done
}

[ -n "${RADIUS_CREDENTIAL_KEY:-}" ] || { echo "RADIUS_CREDENTIAL_KEY ausente." >&2; exit 1; }
wait_for_database
render "/opt/radiushub-radius/templates/$DIALECT/sql" "$CONFIG_ROOT/mods-enabled/sql"
render "/opt/radiushub-radius/templates/$DIALECT/queries.conf" "$CONFIG_ROOT/mods-config/sql/main/$DIALECT/queries.conf"
render /opt/radiushub-radius/templates/common/clients.conf "$CONFIG_ROOT/clients.conf"
render /opt/radiushub-radius/templates/common/default "$CONFIG_ROOT/sites-enabled/default"

freeradius -XC
watch_radius_clients &
exec "$@"
