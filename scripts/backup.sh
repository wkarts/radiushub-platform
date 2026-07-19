#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/lib.sh"
cd "$PROJECT_ROOT"
MODE=auto
DEST="${BACKUP_DIR:-$PROJECT_ROOT/backups}"
while [[ $# -gt 0 ]]; do
  case "$1" in
    --docker) MODE=docker ;;
    --native) MODE=native ;;
    --dest) shift; DEST="$1" ;;
    *) DEST="$1" ;;
  esac
  shift
done
[[ -f "$ENV_FILE" ]] || die ".env não encontrado."
[[ "$MODE" != auto ]] || MODE="$([[ "$(read_env DEPLOYMENT_MODE native)" == docker ]] && echo docker || echo native)"
mkdir -p "$DEST"
chmod 700 "$DEST"
STAMP="$(date +%Y%m%d-%H%M%S)"
DRIVER="$(read_env DB_CONNECTION)"
DB_NAME="$(read_env DB_DATABASE)"; DB_USER="$(read_env DB_USERNAME)"; DB_PASS="$(read_env DB_PASSWORD)"; DB_HOST="$(read_env DB_HOST)"; DB_PORT="$(read_env DB_PORT)"

if [[ "$MODE" == docker ]]; then
  PROFILE="$(read_env COMPOSE_PROFILES)"; [[ -n "$PROFILE" ]] || PROFILE="$([[ "$DRIVER" == mysql ]] && echo mysql || echo postgres)"
  if [[ "$DRIVER" == mysql ]]; then
    docker compose --profile "$PROFILE" exec -T -e MYSQL_PWD="$DB_PASS" mysql mysqldump -u"$DB_USER" --single-transaction --routines --triggers "$DB_NAME" | gzip -9 > "$DEST/database-$STAMP.sql.gz"
  else
    docker compose --profile "$PROFILE" exec -T -e PGPASSWORD="$DB_PASS" postgres pg_dump -U "$DB_USER" -d "$DB_NAME" -Fc > "$DEST/database-$STAMP.dump"
  fi
  docker compose --profile "$PROFILE" exec -T app tar -C /var/www/html -czf - storage/app storage/logs 2>/dev/null > "$DEST/storage-$STAMP.tar.gz" || true
else
  if [[ "$DRIVER" == mysql ]]; then
    MYSQL_PWD="$DB_PASS" mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" --single-transaction --routines --triggers "$DB_NAME" | gzip -9 > "$DEST/database-$STAMP.sql.gz"
  else
    PGPASSWORD="$DB_PASS" pg_dump -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d "$DB_NAME" -Fc > "$DEST/database-$STAMP.dump"
  fi
  tar -czf "$DEST/storage-$STAMP.tar.gz" storage/app storage/logs .env 2>/dev/null || true
fi
sha256sum "$DEST"/*"$STAMP"* > "$DEST/SHA256SUMS-$STAMP.txt"
log "Backup concluído em $DEST"
