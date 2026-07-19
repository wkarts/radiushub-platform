#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/lib.sh"
cd "$PROJECT_ROOT"
[[ "${EUID}" -eq 0 ]] || die "Execute como root: sudo $0"
[[ -f "$ENV_FILE" ]] || die ".env não encontrado."

SITE_USER="${SITE_USER:-$(stat -c '%U' artisan)}"
PHP_BIN="${PHP_BIN:-php}"
CONFIG_ROOT="${FREERADIUS_CONFIG_ROOT:-/etc/freeradius/3.0}"
DRIVER="$(read_env DB_CONNECTION)"
case "$DRIVER" in
  mysql) radius_driver=freeradius-mysql ;;
  pgsql) radius_driver=freeradius-postgresql ;;
  *) die "DB_CONNECTION deve ser mysql ou pgsql." ;;
esac

if command_exists apt-get && [[ "${SKIP_PACKAGES:-false}" != true ]]; then
  apt-get update
  DEBIAN_FRONTEND=noninteractive apt-get install -y freeradius freeradius-utils "$radius_driver"
fi
command_exists freeradius || die "FreeRADIUS não encontrado."

rm -f bootstrap/cache/config.php
runuser -u "$SITE_USER" -- "$PHP_BIN" artisan config:clear
runuser -u "$SITE_USER" -- "$PHP_BIN" artisan radiushub:radius:render --output=storage/app/freeradius-generated --force

GENERATED="$PROJECT_ROOT/storage/app/freeradius-generated"
BACKUP="${CONFIG_ROOT}.backup-$(date +%Y%m%d-%H%M%S)"
[[ -d "$CONFIG_ROOT" ]] && cp -a "$CONFIG_ROOT" "$BACKUP"
install -d -m 0750 "$CONFIG_ROOT/mods-enabled" "$CONFIG_ROOT/mods-config/sql/main/$([[ "$DRIVER" == mysql ]] && echo mysql || echo postgresql)" "$CONFIG_ROOT/sites-enabled"
install -m 0600 "$GENERATED/mods-enabled/sql" "$CONFIG_ROOT/mods-enabled/sql"
install -m 0600 "$GENERATED/mods-config/sql/main/$([[ "$DRIVER" == mysql ]] && echo mysql || echo postgresql)/queries.conf" "$CONFIG_ROOT/mods-config/sql/main/$([[ "$DRIVER" == mysql ]] && echo mysql || echo postgresql)/queries.conf"
install -m 0600 "$GENERATED/clients.conf" "$CONFIG_ROOT/clients.conf"
install -m 0640 "$GENERATED/sites-enabled/default" "$CONFIG_ROOT/sites-enabled/default"
chown -R freerad:freerad "$CONFIG_ROOT" 2>/dev/null || chown -R freerad:freerad "$CONFIG_ROOT" 2>/dev/null || true

freeradius -XC
systemctl enable --now freeradius
systemctl restart freeradius
systemctl --no-pager --full status freeradius | head -30 || true
log "FreeRADIUS nativo instalado. Backup: $BACKUP"
