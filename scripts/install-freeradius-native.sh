#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/lib.sh"
cd "$PROJECT_ROOT"
[[ "${EUID}" -eq 0 ]] || die "Execute como root: sudo $0"
[[ -f "$ENV_FILE" ]] || die ".env não encontrado."

SITE_USER="${SITE_USER:-$(stat -c '%U' artisan)}"
PHP_BIN="${PHP_BIN:-php}"
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

detect_freeradius_config_root() {
  local explicit="${FREERADIUS_CONFIG_ROOT:-}" probe radiusd_file candidate

  if [[ -n "$explicit" ]]; then
    [[ -f "$explicit/radiusd.conf" ]] || die "FREERADIUS_CONFIG_ROOT não contém radiusd.conf: $explicit"
    printf '%s' "$explicit"
    return 0
  fi

  probe="$(freeradius -XC 2>&1 || true)"
  radiusd_file="$(
    printf '%s\n' "$probe" \
      | sed -n 's|.*from file \([^ ]*/radiusd\.conf\).*|\1|p' \
      | head -n 1
  )"

  if [[ -n "$radiusd_file" && -f "$radiusd_file" ]]; then
    dirname "$radiusd_file"
    return 0
  fi

  for candidate in /etc/freeradius/3.0 /etc/freeradius; do
    [[ -f "$candidate/radiusd.conf" ]] && { printf '%s' "$candidate"; return 0; }
  done

  die "Não foi possível localizar o radiusd.conf ativo do FreeRADIUS."
}

CONFIG_ROOT="$(detect_freeradius_config_root)"
log "Configuração ativa do FreeRADIUS: $CONFIG_ROOT"

runuser -u "$SITE_USER" -- "$PHP_BIN" scripts/check-freeradius-templates.php

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
chown -R freerad:freerad "$CONFIG_ROOT" 2>/dev/null \
  || chown -R radiusd:radiusd "$CONFIG_ROOT" 2>/dev/null \
  || true

validation_output="$(freeradius -XC 2>&1)" || {
  printf '%s\n' "$validation_output" >&2
  die "A configuração gerada do FreeRADIUS é inválida."
}
printf '%s\n' "$validation_output"
grep -Fq 'Ignoring "sql"' <<<"$validation_output" \
  && die "O FreeRADIUS ignorou o módulo SQL em $CONFIG_ROOT."
grep -Eq 'Loaded module rlm_sql|Loading module "sql"|Instantiating module "sql"' <<<"$validation_output" \
  || die "O módulo SQL não foi carregado pelo FreeRADIUS em $CONFIG_ROOT."
systemctl enable --now freeradius
systemctl restart freeradius
systemctl --no-pager --full status freeradius | head -30 || true
log "FreeRADIUS nativo instalado. Backup: $BACKUP"
