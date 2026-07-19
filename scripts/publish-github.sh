#!/usr/bin/env bash
set -euo pipefail
source "$(dirname "$0")/lib.sh"
cd "$PROJECT_ROOT"

REPOSITORY="${1:-wkarts/radiushub-platform}"
VISIBILITY="${GITHUB_VISIBILITY:-private}"
CREATE_TAG="${CREATE_TAG:-false}"
RELEASE_TAG="${RELEASE_TAG:-v$(cat VERSION)}"

for argument in "${@:2}"; do
  case "$argument" in
    --public) VISIBILITY=public ;;
    --private) VISIBILITY=private ;;
    --tag-now) CREATE_TAG=true ;;
    *) die "Argumento desconhecido: $argument" ;;
  esac
done

command_exists git || die "Git não encontrado."
command_exists gh || die "GitHub CLI (gh) não encontrado."
gh auth status >/dev/null 2>&1 || die "Autentique primeiro com: gh auth login"
[[ ! -f .env ]] || warn "O arquivo .env existe, mas está protegido pelo .gitignore. Confirme com git status antes do push."

php scripts/check-version-integrity.php
php scripts/check-migration-integrity.php

if [[ ! -d .git ]]; then
  git init
  git branch -M main
fi

git config user.name >/dev/null 2>&1 || git config user.name "Wallace Kleiton"
git config user.email >/dev/null 2>&1 || git config user.email "wkarts@users.noreply.github.com"
git add .
if ! git diff --cached --quiet; then
  git commit -m "feat: publicar RadiusHub Platform $(cat VERSION)"
fi

if gh repo view "$REPOSITORY" >/dev/null 2>&1; then
  remote_url="git@github.com:${REPOSITORY}.git"
  if git remote get-url origin >/dev/null 2>&1; then git remote set-url origin "$remote_url"; else git remote add origin "$remote_url"; fi
  git push -u origin main
else
  gh repo create "$REPOSITORY" "--$VISIBILITY" --source=. --remote=origin --push \
    --description "Plataforma Laravel multi-tenant para MikroTik, FreeRADIUS e cobrança Asaas"
fi

if [[ "$CREATE_TAG" == "true" ]]; then
  if ! git rev-parse "$RELEASE_TAG" >/dev/null 2>&1; then
    git tag -a "$RELEASE_TAG" -m "RadiusHub Platform $(cat VERSION)"
  fi
  git push origin "$RELEASE_TAG"
  log "Tag $RELEASE_TAG enviada. O workflow de release fará publicação idempotente."
else
  log "Push concluído. Após o CI da branch main, o workflow criará automaticamente a tag v$(cat VERSION), a GitHub Release e as imagens semânticas."
fi

log "Repositório publicado: https://github.com/$REPOSITORY"
