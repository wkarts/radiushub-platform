# GitHub, CI e GHCR

## Publicação automática

Com GitHub CLI autenticada, crie o repositório, envie a branch `main` e publique a tag em um único comando:

```bash
./scripts/publish-github.sh wkarts/radiushub-platform --public
```

Sem `--public`, o repositório será privado. O script não envia `.env`, porque o arquivo está protegido pelo `.gitignore`.

## Publicação manual

Crie um repositório vazio, por exemplo `wkarts/radiushub-platform`, e envie o conteúdo:

```bash
git init
git add .
git commit -m "feat: publicar RadiusHub Platform 1.3.1"
git branch -M main
git remote add origin git@github.com:wkarts/radiushub-platform.git
git push -u origin main
```

## Workflows incluídos

- `ci.yml`: sintaxe PHP, testes, migrations MySQL/PostgreSQL e renderização FreeRADIUS;
- `docker-publish.yml`: publica as três imagens no GitHub Container Registry;
- `release.yml`: cria pacote e checksum quando uma tag `vX.Y.Z` é enviada;
- `dependabot.yml`: atualiza Composer, Docker e GitHub Actions.

## Publicar 1.3.1

```bash
git tag -a v1.3.1 -m "RadiusHub Platform 1.3.1"
git push origin v1.3.1
```

O `GITHUB_TOKEN` do workflow publica automaticamente no GHCR. Em `Settings > Actions > General`, mantenha `Read and write permissions` para workflows ou conceda `packages: write` conforme a política do repositório.

## Imagens

```text
ghcr.io/wkarts/radiushub-app:1.3.1
ghcr.io/wkarts/radiushub-web:1.3.1
ghcr.io/wkarts/radiushub-freeradius:1.3.1
```

Se o repositório pertencer a outro usuário/organização, altere `RADIUSHUB_REGISTRY` no `.env`.
