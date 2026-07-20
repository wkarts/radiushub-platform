# Validação da correção RadiusHub Platform 1.4.0

## Evidências analisadas

### Pull Request / workflow 80438529865

Falhas confirmadas nos logs:

- `.env.playground.example` ausente após `actions/checkout`;
- `.env.cloudpanel.playground.example` ausente após `actions/checkout`;
- `scripts/playground.sh: Permission denied` no runner;
- `composer version:check` interrompido pela ausência dos exemplos;
- jobs PHP 8.3, PHP 8.4, MySQL 8.4, PostgreSQL 17, Docker Playground e CloudPanel nativo afetados pela mesma causa de distribuição.

Causa-raiz: `.gitignore` ignorava `/.env.*` e não possuía exceções para os dois exemplos novos. Além disso, arquivos criados no commit não conservaram o bit executável esperado pelos jobs que os chamavam diretamente.

### CloudPanel 1.3.5

A tela pós-login exibiu `403 · Acesso negado — O usuário não possui tenant ativo vinculado`.

A inspeção do dump fornecido confirmou usuários Superadministradores ativos, mas ausência de registros iniciais de tenant/empresa e vínculos correspondentes. O login do Superadministrador também respeitava uma URL `intended` antiga para `/`, enviando a conta global ao middleware de tenant.

## Correções verificadas estaticamente

- exemplos `.env.playground.example` e `.env.cloudpanel.playground.example` liberados no `.gitignore` e `.dockerignore`;
- CI confirma a existência dos arquivos após cada checkout;
- CI normaliza `scripts/*.sh` e `artisan` e chama os fluxos críticos por `bash`;
- serviço idempotente `PlatformBootstrapService` cria/repara Superadministrador, tenant, empresa e vínculos;
- comando `radiushub:bootstrap-platform` disponível para instalação, atualização e reparo;
- senha existente preservada quando a redefinição não é explicitamente solicitada;
- instaladores Docker/CloudPanel e upgrade 1.3.5 → 1.4.0 executam o bootstrap;
- reparo dedicado `scripts/repair-cloudpanel-bootstrap.sh` incluído;
- upgrade/reparo atualizam `APP_VERSION` e corrigem `REDIS_HOST=redis` herdado indevidamente em CloudPanel nativo;
- login e 2FA do Superadministrador direcionam diretamente ao dashboard global;
- middleware sem tenant não retorna 403 para Superadministrador;
- middleware sem empresa direciona administradores ao cadastro de empresas;
- páginas 403/404 não retornam para uma rota dependente de contexto ausente;
- testes de regressão adicionados para bootstrap, senha preservada, login e ausência de tenant.

## Validações executadas neste ambiente

- `php scripts/check-version-integrity.php`;
- `php scripts/check-migration-integrity.php`;
- `php scripts/check-planning-compliance.php`;
- sintaxe de 345 arquivos PHP com `php -l`;
- sintaxe de 28 scripts Bash/entrypoints Docker com `bash -n`;
- parsing dos arquivos JSON e YAML;
- verificação de que os exemplos de playground não são ignorados pelo Git;
- geração e conferência do manifesto SHA-256;
- aplicação do patch sobre uma cópia limpa da versão 1.4.0;
- teste de integridade do ZIP final.

## Validações configuradas para o GitHub Actions

Após o envio do commit corretivo, o workflow executará:

- PHPUnit em PHP 8.3 e 8.4;
- migrations/seed/bootstrap em MySQL 8.4;
- migrations/seed/bootstrap em PostgreSQL 17;
- builds Docker app, web e FreeRADIUS;
- Docker Playground completo;
- smoke de login;
- RADIUS `Access-Accept` e accounting `Accounting-Response`;
- instalação nativa equivalente ao CloudPanel.

## Limitações deste ambiente

Composer, vendor Laravel, Docker Engine, MySQL e PostgreSQL não estão disponíveis localmente. Portanto, PHPUnit e os containers não foram executados aqui. A correção foi validada estaticamente e está preparada para a matriz real do GitHub Actions.

## Revisão 3 — correções baseadas no workflow 80442689140

- `PlaygroundSeederTest`: credenciais de demonstração tornadas determinísticas no próprio teste.
- `MikrotikSshService`: container passou a injetar explicitamente o simulador, evitando resolução como `null` sem quebrar construções manuais existentes.
- `install-cloudpanel.sh`: limpeza de cache usa stores efêmeros antes das migrations.
- Scripts de atualização usam a mesma rotina segura de limpeza.
- Adicionado `DeploymentRegressionTest`.
- A rotina `artisan_optimize_clear_safe` foi executada com um PHP simulado, confirmando `CACHE_STORE=array`, `CACHE_LIMITER=array`, `SESSION_DRIVER=array` e `QUEUE_CONNECTION=sync`.
- O binding do container foi protegido por teste de regressão, mantendo o construtor opcional para compatibilidade.
