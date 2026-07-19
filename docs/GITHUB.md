# GitHub, CI, Releases e GHCR

## Publicação automática

A partir da versão 1.3.4, não é necessário criar a tag manualmente depois do merge.

O fluxo é:

1. o Pull Request é mesclado na `main`;
2. o workflow `CI` valida PHP 8.3/8.4, MySQL, PostgreSQL e Docker;
3. somente quando o CI da `main` termina com sucesso, `release.yml` lê o arquivo `VERSION`;
4. se `vX.Y.Z` ainda não existir, o workflow cria a tag no commit validado;
5. gera ZIP, TAR.GZ, `SHA256SUMS` e metadados;
6. publica as imagens `X.Y.Z`, `X.Y`, `latest` e `sha-*` no GHCR;
7. cria a GitHub Release.

Quando a release da versão já existe, o workflow termina com sucesso sem duplicá-la.

## Publicar o repositório

```bash
./scripts/publish-github.sh wkarts/radiushub-platform --public
```

O script envia a `main` e deixa a publicação da release a cargo do CI.

Para contingência, ainda é possível enviar a tag imediatamente:

```bash
./scripts/publish-github.sh wkarts/radiushub-platform --public --tag-now
```

## Workflows incluídos

- `ci.yml`: lint, testes, migrations MySQL/PostgreSQL, doctor, FreeRADIUS e builds Docker;
- `docker-publish.yml`: publica imagens de acompanhamento para pushes da `main`;
- `release.yml`: cria automaticamente tag, release, artefatos e imagens semânticas após o CI aprovado;
- `dependabot.yml`: atualiza Composer, Docker e GitHub Actions.

## Versão canônica

O arquivo `VERSION` é a fonte canônica. O comando abaixo valida todos os pontos de versionamento:

```bash
composer version:check
```

Uma versão nova deve atualizar, no mesmo PR:

- `VERSION`;
- `APP_VERSION` dos exemplos;
- `RADIUSHUB_TAG` dos exemplos Docker;
- defaults de `config/app.php`, Dockerfiles e `docker-compose.yml`;
- changelog e documentação.

## Recuperar uma release

Em `Actions > Release automática > Run workflow`, informe:

- `ref`: tag, branch ou commit;
- `rebuild_existing`: `true` somente para reconstruir os artefatos da tag existente.

O workflow também aceita uma tag `vX.Y.Z` enviada manualmente.

## Imagens

```text
ghcr.io/wkarts/radiushub-app:1.3.4
ghcr.io/wkarts/radiushub-web:1.3.4
ghcr.io/wkarts/radiushub-freeradius:1.3.4
```

Também são publicadas as tags `1.3`, `latest` e `sha-*`.

## Permissões

Os workflows declaram explicitamente:

```yaml
contents: write
packages: write
actions: read
```

O repositório precisa permitir que o `GITHUB_TOKEN` grave conteúdo e pacotes conforme a política definida em `Settings > Actions > General`.
