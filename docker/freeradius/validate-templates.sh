#!/usr/bin/env bash
set -euo pipefail

TEMPLATE_ROOT="${RADIUSHUB_RADIUS_TEMPLATE_ROOT:-/opt/radiushub-radius/templates}"

detect_config_root() {
  local probe radiusd_file candidate

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

  echo "Não foi possível localizar a configuração base do FreeRADIUS." >&2
  return 1
}

render_file() {
  local source="$1" destination="$2" port="$3"

  mkdir -p "$(dirname "$destination")"
  sed \
    -e 's|@@DB_HOST@@|127.0.0.1|g' \
    -e "s|@@DB_PORT@@|${port}|g" \
    -e 's|@@DB_DATABASE@@|radiushub_template_check|g' \
    -e 's|@@DB_USERNAME@@|radiushub_template_check|g' \
    -e 's|@@DB_PASSWORD@@|template-check-password|g' \
    -e 's|@@RADIUS_CREDENTIAL_KEY@@|template-check-radius-credential-key|g' \
    -e 's|@@RADIUS_LOCAL_SECRET@@|template-check-local-secret|g' \
    -e 's|driver = "rlm_sql_${dialect}"|driver = "rlm_sql_null"|g' \
    "$source" > "$destination"
}

validate_dialect() {
  local base_root="$1" dialect="$2" port="$3" config_root validation_output

  config_root="$(mktemp -d "/tmp/radiushub-radius-${dialect}.XXXXXX")"

  cp -a "$base_root/." "$config_root/"
  rm -f "$config_root/mods-enabled/sql" "$config_root/sites-enabled/default"

  render_file "$TEMPLATE_ROOT/$dialect/sql" "$config_root/mods-enabled/sql" "$port"
  render_file "$TEMPLATE_ROOT/$dialect/queries.conf" "$config_root/mods-config/sql/main/$dialect/queries.conf" "$port"
  render_file "$TEMPLATE_ROOT/common/clients.conf" "$config_root/clients.conf" "$port"
  render_file "$TEMPLATE_ROOT/common/default" "$config_root/sites-enabled/default" "$port"
  chmod -R a+rX "$config_root"

  validation_output="$(freeradius -d "$config_root" -XC 2>&1)" || {
    printf '%s\n' "$validation_output" >&2
    rm -rf "$config_root"
    echo "Template FreeRADIUS inválido para ${dialect}." >&2
    return 1
  }

  if grep -Fq 'Ignoring "sql"' <<<"$validation_output"; then
    printf '%s\n' "$validation_output" >&2
    rm -rf "$config_root"
    echo "O FreeRADIUS ignorou o módulo SQL no template ${dialect}." >&2
    return 1
  fi

  if ! grep -Eq 'Loaded module rlm_sql|Loading module "sql"|Instantiating module "sql"' <<<"$validation_output"; then
    printf '%s\n' "$validation_output" >&2
    rm -rf "$config_root"
    echo "O módulo SQL não foi carregado durante a validação ${dialect}." >&2
    return 1
  fi

  printf 'Template FreeRADIUS %s validado pelo parser 3.2.x.\n' "$dialect"
  rm -rf "$config_root"
}

base_root="$(detect_config_root)"
validate_dialect "$base_root" postgresql 5432
validate_dialect "$base_root" mysql 3306
