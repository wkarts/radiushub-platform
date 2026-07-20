# Validação da correção RadiusHub Platform 1.4.3

## Workflow analisado

`80457568511`

## Resultado

PHP 8.3, PHP 8.4, MySQL 8.4, PostgreSQL 17, contratos Docker/Compose e CloudPanel nativo foram aprovados. A única falha ocorreu no parser real da imagem FreeRADIUS.

## Causa confirmada

```text
sites-enabled/default[29]: Parse error after "pap": unexpected token "}"
Template FreeRADIUS inválido para postgresql.
```

O virtual server compactava `Auth-Type PAP`, `Auth-Type CHAP` e `Auth-Type MS-CHAP` em uma linha.

## Correção

- blocos `Auth-Type` multilinha;
- chaves do virtual server verificadas;
- blocos nomeados compactados proibidos;
- PAP, CHAP e MS-CHAP validados estruturalmente;
- teste unitário dedicado;
- parser `freeradius -XC` preservado para PostgreSQL e MySQL no build;
- versão 1.4.3;
- validação integral da PR preservada.

## Validações locais

```bash
php scripts/check-freeradius-templates.php
php scripts/check-version-integrity.php
php scripts/check-migration-integrity.php
php scripts/check-planning-compliance.php
bash -n docker/freeradius/entrypoint.sh
bash -n docker/freeradius/validate-templates.sh
bash -n scripts/upgrade-1.4.2-to-1.4.3.sh
```

O parser binário 3.2.10 continua sendo executado pelo Dockerfile no GitHub Actions antes de iniciar o Playground.
