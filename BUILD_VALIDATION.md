# Validação da entrega 1.3.5

## Log analisado

Workflow GitHub Actions `80422437293`.

Resultados:

- preparação da versão concluída;
- tag `v1.3.4` criada e resolvida para o commit aprovado;
- ZIP, TAR.GZ, `SHA256SUMS` e `RELEASE-METADATA.json` gerados;
- download do artefato concluído e digest SHA-256 correspondente;
- checksums do ZIP e TAR.GZ aprovados;
- imagens `radiushub-app`, `radiushub-web` e `radiushub-freeradius` publicadas;
- falha restrita ao comando `gh release create` no job final.

## Causa-raiz

Cada job do GitHub Actions executa em runner independente. O job `publish` baixava os artefatos, mas não executava `actions/checkout`. O GitHub CLI tentou descobrir o repositório por meio de `.git` e encerrou com:

```text
failed to run git: fatal: not a git repository
```

A linha `digest-mismatch: error` exibida no download não era falha; era a política configurada para interromper somente se o digest divergisse. O digest calculado correspondeu ao esperado.

## Correção

- checkout explícito no job `publish`;
- `GH_REPO` definido no workflow;
- `--repo` em `gh release view`, `create` e `upload`;
- verificação do commit local;
- verificação de que a tag aponta para o commit esperado;
- validação dos quatro arquivos antes da publicação;
- retentativa limitada para falhas transitórias;
- validação posterior da URL e dos quatro assets publicados;
- suporte à recuperação da tag `v1.3.4` sem release.

## Validações locais executadas

- sintaxe PHP;
- sintaxe Bash;
- parsing JSON e YAML;
- integridade de versão;
- integridade das migrations;
- simulação do inventário de artefatos;
- manifesto SHA-256;
- integridade do ZIP.

A publicação real da release será executada no GitHub Actions após o merge.

## Simulação do job final

Os três scripts do job `publish` foram extraídos do YAML e executados em um repositório Git temporário com:

- commit e tag anotada reais;
- remote Git local;
- quatro artefatos e checksums;
- GitHub CLI simulado;
- criação da release;
- verificação de URL, nomes e tamanhos dos quatro assets.

Resultado: `SIM_RELEASE_OK`.
