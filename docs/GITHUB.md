# GitHub, CI, Releases e GHCR

## Publicação automática

A partir da versão 1.4.3, não é necessário criar a tag manualmente depois do merge.

O fluxo é:

1. o Pull Request é mesclado na `main`;
2. o workflow `CI` executa no próprio PR a matriz completa, incluindo Docker Playground, RADIUS, accounting e CloudPanel;
3. somente quando o CI da `main` termina com sucesso, `release.yml` lê o arquivo `VERSION`;
4. se `vX.Y.Z` ainda não existir, o workflow cria a tag no commit validado;
5. gera ZIP, TAR.GZ, `SHA256SUMS` e metadados;
6. publica as imagens `X.Y.Z`, `X.Y`, `latest` e `sha-*` no GHCR;
7. cria a GitHub Release.

Quando a release da versão já existe, o workflow termina com sucesso sem duplicá-la.

## Validação de Pull Request

Todo PR para `main` executa, antes do merge:

- testes em PHP 8.3 e 8.4;
- migrations, seed, bootstrap e diagnóstico em PostgreSQL 17;
- migrations, seed, bootstrap e diagnóstico em MySQL 8.4;
- validação dos Dockerfiles, entrypoints e arquivos Compose;
- build das imagens app, web e FreeRADIUS;
- Docker Playground completo;
- login HTTP autenticado;
- RADIUS `Access-Accept`;
- accounting `Accounting-Response` e persistência da sessão;
- instalação nativa equivalente ao CloudPanel.

Nenhuma dessas etapas depende de label, execução manual ou merge. O CI do PR não publica imagens, não cria tags, não cria releases e não envia artefatos de release. A publicação continua exclusiva do workflow `release.yml`, depois do CI aprovado na `main`.

O evento `push` do CI permanece limitado à `main`. Assim, commits da branch do PR não disparam simultaneamente `push` e `pull_request`.

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

- `ci.yml`: executa a validação integral de PHP, bancos, imagens, Playground, RADIUS/accounting e CloudPanel em todo PR;
- `docker-publish.yml`: publicação manual de contingência, sem disparo automático em pushes ou tags;
- `release.yml`: cria automaticamente tag, release, artefatos e imagens semânticas após o CI completo da `main`;
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
ghcr.io/wkarts/radiushub-app:1.4.3
ghcr.io/wkarts/radiushub-web:1.4.3
ghcr.io/wkarts/radiushub-freeradius:1.4.3
```

Também são publicadas as tags `1.4`, `latest` e `sha-*`.

## Permissões

Os workflows declaram explicitamente:

```yaml
contents: write
packages: write
actions: read
```

O repositório precisa permitir que o `GITHUB_TOKEN` grave conteúdo e pacotes conforme a política definida em `Settings > Actions > General`.

## Contexto explícito do GitHub CLI

Cada job do GitHub Actions usa um runner independente. O job `publish` realiza seu próprio checkout do commit aprovado e também define `GH_REPO=${{ github.repository }}`. Todos os comandos `gh release` recebem `--repo`, evitando dependência implícita de um diretório `.git`.

Antes da publicação, o workflow confirma que:

- o diretório atual é um repositório Git válido;
- o commit local corresponde ao commit aprovado;
- a tag remota resolve para o mesmo commit;
- os checksums locais são válidos.

Depois da publicação, confirma a existência da release e dos quatro artefatos esperados.

### Recuperar a release 1.3.4 que ficou apenas com tag

Depois de incorporar a versão 1.4.3, execute manualmente `Release automática` com:

- `ref`: `f3cccaf5b3910d366d30599b2037baf7be3d732c`;
- `rebuild_existing`: `false`.

O workflow lerá `VERSION=1.3.4` naquele commit, reutilizará a tag `v1.3.4` existente e criará a release ausente.

