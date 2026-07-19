# Validação da entrega 1.4.0

## Escopo analisado

A versão 1.4.0 foi construída sobre a 1.3.5, preservando Laravel/Blade, multi-tenancy, RBAC, FreeRADIUS, MikroTik SSH Key, vouchers, financeiro, Asaas, Docker, CloudPanel e automação de release.

## Validação executada no ambiente de geração

- lint de **339 arquivos PHP** com `php -l`;
- lint de **27 scripts Bash** com `bash -n`/`sh -n`;
- parsing de **3 arquivos JSON** e **11 arquivos YAML**;
- verificação de versão por `scripts/check-version-integrity.php`;
- verificação de sequências de migrations por `scripts/check-migration-integrity.php`;
- inspeção de rotas, controllers, requests, policies, middleware, services e views;
- verificação estática dos serviços e dependências do Docker Compose;
- verificação dos exemplos `.env` de produção e playground;
- verificação de cookies seguros nos exemplos de produção;
- teste de regressão para impedir promoção indevida de membros no backfill RBAC;
- contrato de conformidade para componentes, rotas, sidebar e smoke tests;
- execução positiva de `scripts/check-planning-compliance.php`;
- verificação do instalador Docker + reverse proxy CloudPanel e do adiamento seguro do login HTTPS até o proxy ser aplicado;
- verificação do bloqueio do simulador fora do playground;
- geração de árvore com **536 arquivos**, manifesto SHA-256, patch incremental e teste de integridade do ZIP.

## Validação configurada no GitHub Actions

O workflow executa, depois do push:

- Composer/PHPUnit em PHP 8.3 e 8.4;
- migrations, seed, Doctor e renderização FreeRADIUS no PostgreSQL 17;
- migrations, seed, Doctor e renderização FreeRADIUS no MySQL 8.4;
- build das imagens app, web e FreeRADIUS;
- playground Docker completo;
- liveness/readiness;
- login autenticado;
- simulador MikroTik;
- autenticação FreeRADIUS com `Access-Accept`;
- accounting com `Accounting-Response` e confirmação no banco;
- instalação nativa equivalente ao fluxo CloudPanel e geração de Nginx/Supervisor/Cron.

## Não executado localmente

Este ambiente não possui Composer, Docker Engine, MySQL ou PostgreSQL. Portanto, os testes dinâmicos acima não foram simulados como se tivessem sido executados localmente; eles permanecem configurados para execução real no CI.

## Homologação externa ainda necessária

- SSH Key e fingerprint em MikroTik RouterOS real;
- Hotspot/PPPoE em rede real;
- conta e webhook Asaas Sandbox;
- SMTP real;
- firewall e acesso RADIUS dos NAS;
- revisão visual nos dispositivos físicos da operação.
