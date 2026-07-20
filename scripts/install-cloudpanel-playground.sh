#!/usr/bin/env bash
set -euo pipefail
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

if [[ -f .env && "${1:-}" != "--reuse-env" ]]; then
  echo "Já existe um arquivo .env. Para reutilizá-lo conscientemente, execute com --reuse-env." >&2
  exit 1
fi

if [[ ! -f .env ]]; then
  cp .env.cloudpanel.playground.example .env
fi

export ENV_FILE="$PROJECT_ROOT/.env"
source "$PROJECT_ROOT/scripts/lib.sh"
ensure_secrets
set_env PLAYGROUND_MODE true
set_env PLAYGROUND_BANNER true
set_env PLAYGROUND_MIKROTIK_SIMULATOR true
set_env SEED_DEMO true
set_env DEPLOYMENT_MODE playground

value="$(read_env PLAYGROUND_ADMIN_PASSWORD)"
[[ -n "$value" && "$value" != 'ChangeMe@123!' ]] || set_env PLAYGROUND_ADMIN_PASSWORD "$(random_password)"
set_env SEED_ADMIN_PASSWORD "$(read_env PLAYGROUND_ADMIN_PASSWORD)"

value="$(read_env PLAYGROUND_OPERATOR_PASSWORD)"
[[ -n "$value" && "$value" != 'Operador@123!' ]] || set_env PLAYGROUND_OPERATOR_PASSWORD "$(random_password)"
value="$(read_env PLAYGROUND_TECHNICIAN_PASSWORD)"
[[ -n "$value" && "$value" != 'Tecnico@123!' ]] || set_env PLAYGROUND_TECHNICIAN_PASSWORD "$(random_password)"

"$PROJECT_ROOT/scripts/install-cloudpanel.sh"
php artisan radiushub:playground:verify
php artisan radiushub:health --ready

cat <<INFO

CloudPanel Playground concluído.
URL: $(read_env APP_URL)
Administrador: $(read_env PLAYGROUND_ADMIN_EMAIL)
Senha: $(read_env PLAYGROUND_ADMIN_PASSWORD)
Operador: $(read_env PLAYGROUND_OPERATOR_EMAIL)
Senha: $(read_env PLAYGROUND_OPERATOR_PASSWORD)
Técnico: $(read_env PLAYGROUND_TECHNICIAN_EMAIL)
Senha: $(read_env PLAYGROUND_TECHNICIAN_PASSWORD)

As credenciais estão protegidas no arquivo .env (modo 600).
INFO
