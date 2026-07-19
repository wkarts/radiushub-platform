# Validação da entrega 1.3.4

## Logs analisados

Workflows:

- `80413355503`: CI da branch de correção;
- `80413540568`: CI após o merge na `main`;
- `80413540577`: publicação das imagens Docker após o merge.

Resultados confirmados nos logs:

- PHP 8.3: 30 testes aprovados, 106 asserções;
- PHP 8.4: 30 testes aprovados, 106 asserções;
- MySQL 8.4: migrations, seeders, doctor e renderização FreeRADIUS aprovados;
- PostgreSQL 17: migrations, seeders, doctor e renderização FreeRADIUS aprovados;
- imagens app, web e FreeRADIUS construídas/publicadas;
- nenhuma execução do workflow de release.

## Causa-raiz

O workflow `.github/workflows/release.yml` era acionado somente por:

```yaml
push:
  tags: ['v*.*.*']
```

O merge gerou um push para `refs/heads/main`, não uma tag. O workflow `docker-publish.yml` executou normalmente, porém nenhum mecanismo criou `v1.3.3`; por isso o workflow de release nunca foi iniciado.

## Correção

- release acionada por `workflow_run` após o `CI` da `main`;
- filtro exige conclusão `success`, evento `push` e branch `main`;
- `VERSION` é a fonte canônica;
- tag é criada no commit aprovado;
- operação é idempotente;
- ZIP, TAR.GZ, checksums e metadados são publicados;
- imagens semânticas são publicadas dentro do mesmo fluxo, pois eventos gerados pelo `GITHUB_TOKEN` não iniciam outros workflows;
- execução por tag e `workflow_dispatch` permanecem disponíveis como contingência;
- `scripts/check-version-integrity.php` impede divergência de versão.

## Validações locais

- sintaxe de 326 arquivos PHP;
- sintaxe de 19 scripts Bash;
- parsing de 3 arquivos JSON e 5 arquivos YAML;
- verificação de versão;
- verificação do inventário de migrations;
- teste positivo e negativo do verificador de versão;
- smoke test dos comandos `git archive` para ZIP e TAR.GZ;
- manifesto SHA-256;
- integridade do ZIP.

A execução real do novo `workflow_run`, criação da tag, publicação no GHCR e GitHub Release ocorrerá após o merge desta versão na `main`.
