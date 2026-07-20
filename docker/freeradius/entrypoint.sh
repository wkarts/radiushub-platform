#!/usr/bin/env bash
set -euo pipefail

detect_config_root() {
  local explicit="${FREERADIUS_CONFIG_ROOT:-}" probe radiusd_file candidate

  if [ -n "$explicit" ]; then
    [ -f "$explicit/radiusd.conf" ] || {
      echo "FREERADIUS_CONFIG_ROOT não contém radiusd.conf: $explicit" >&2
      return 1
    }
    printf '%s' "$explicit"
    return 0
  fi

  # A imagem oficial freeradius/freeradius-server usa /etc/freeradius,
  # enquanto os pacotes Debian/Ubuntu normalmente usam /etc/freeradius/3.0.
  # Descobre primeiro o diretório realmente carregado pelo binário para não
  # renderizar o módulo SQL em uma árvore existente, porém inativa.
  probe="$(freeradius -XC 2>&1 || true)"
  radiusd_file="$(
    printf '%s\n' "$probe" \
      | sed -n 's|.*from file \([^ ]*/radiusd\.conf\).*|\1|p' \
      | head -n 1
  )"

  if [ -n "$radiusd_file" ] && [ -f "$radiusd_file" ]; then
    dirname "$radiusd_file"
    return 0
  fi

  for candidate in /etc/freeradius /etc/freeradius/3.0; do
    if [ -f "$candidate/radiusd.conf" ]; then
      printf '%s' "$candidate"
      return 0
    fi
  done

  echo "Não foi possível localizar o radiusd.conf ativo do FreeRADIUS." >&2
  return 1
}

CONFIG_ROOT="$(detect_config_root)"
echo "Configuração ativa do FreeRADIUS: $CONFIG_ROOT"
if [ "${1:-}" = "--print-config-root" ]; then
  printf '%s\n' "$CONFIG_ROOT"
  exit 0
fi
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

radius_clients_fingerprint() {
  # O status operacional e os timestamps de monitoramento mudam com frequência,
  # mas não alteram o cadastro RADIUS. O fingerprint considera somente os campos
  # que realmente exigem recarga dos clientes NAS.
  if [ "$DRIVER" = pgsql ]; then
    PGPASSWORD="${DB_PASSWORD:-}" psql -h "${DB_HOST:-postgres}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-radiushub}" -d "${DB_DATABASE:-radiushub}" -Atqc "
      SELECT COALESCE(
        md5(string_agg(
          concat_ws('|', id::text, name, radius_source_ip, COALESCE(radius_secret_ciphertext, ''), active::text, COALESCE(deleted_at::text, '')),
          '||' ORDER BY id::text
        )),
        ''
      )
      FROM mikrotik_devices
    " 2>/dev/null || true
  else
    MYSQL_PWD="${DB_PASSWORD:-}" mysql -h "${DB_HOST:-mysql}" -P "${DB_PORT:-3306}" -u "${DB_USERNAME:-radiushub}" "${DB_DATABASE:-radiushub}" -Nse "
      SET SESSION group_concat_max_len = 16777216;
      SELECT COALESCE(
        MD5(GROUP_CONCAT(
          CONCAT_WS('|', id, name, radius_source_ip, COALESCE(radius_secret_ciphertext, ''), active, COALESCE(deleted_at, ''))
          ORDER BY id SEPARATOR '||'
        )),
        ''
      )
      FROM mikrotik_devices
    " 2>/dev/null || true
  fi
}

watch_radius_clients() {
  local interval="$1" previous current

  previous="$(radius_clients_fingerprint)"
  while sleep "$interval"; do
    current="$(radius_clients_fingerprint)"
    if [ -n "$previous" ] && [ "$current" != "$previous" ]; then
      echo "Configuração RADIUS dos NAS alterada; recarregando FreeRADIUS."
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

validation_output="$(freeradius -XC 2>&1)" || {
  printf '%s\n' "$validation_output" >&2
  echo "A configuração gerada do FreeRADIUS é inválida." >&2
  exit 1
}
printf '%s\n' "$validation_output"

if grep -Fq 'Ignoring "sql"' <<<"$validation_output"; then
  echo "O FreeRADIUS ignorou o módulo SQL. Diretório renderizado: $CONFIG_ROOT" >&2
  exit 1
fi

if ! grep -Eq 'Loaded module rlm_sql|Loading module "sql"|Instantiating module "sql"' <<<"$validation_output"; then
  echo "O módulo SQL não foi carregado pelo FreeRADIUS em $CONFIG_ROOT." >&2
  exit 1
fi

reload_interval="${RADIUS_CLIENT_RELOAD_SECONDS:-30}"
[[ "$reload_interval" =~ ^[0-9]+$ ]] || {
  echo "RADIUS_CLIENT_RELOAD_SECONDS inválido: $reload_interval" >&2
  exit 1
}
if [ "$reload_interval" -gt 0 ]; then
  watch_radius_clients "$reload_interval" &
else
  echo "Recarga automática de clientes NAS desabilitada neste ambiente."
fi
exec "$@"
