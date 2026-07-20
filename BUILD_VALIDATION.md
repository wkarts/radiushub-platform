# Validação da correção RadiusHub Platform 1.4.1

## Evidências novas — workflows 80449231682 e 80449518778

O workflow `80449231682` aprovou apenas PHP 8.3/8.4, MySQL, PostgreSQL e contratos estáticos de Docker. Ele não executou o Playground Docker nem o CloudPanel porque a revisão anterior os condicionava a um label. Essa alteração foi revertida.

O workflow `80449518778` executou a homologação completa e encontrou a falha real: a imagem oficial FreeRADIUS carregava `/etc/freeradius/radiusd.conf`, enquanto o entrypoint escrevia o módulo SQL em `/etc/freeradius/3.0`. O log registrou `Ignoring "sql"`, portanto o servidor iniciou sem consultar o banco e nunca retornou `Access-Accept`.

A versão 1.4.1:

- executa todas as validações em todo Pull Request, sem label e sem depender de merge;
- constrói app, web e FreeRADIUS no job do Playground e valida o stack integral;
- mantém CloudPanel nativo obrigatório no PR;
- não cria release, não publica imagens e não gera artefatos de release durante o CI do PR;
- detecta o `radiusd.conf` realmente usado pelo binário;
- falha antes de iniciar se o SQL for ignorado ou `rlm_sql` não for carregado;
- preserva timeout controlado do `radclient` e fingerprint estável dos NAS.

Detalhes: `docs/LOG_ANALYSIS_80449231682_80449518778.md`.


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
- instaladores Docker/CloudPanel, upgrade 1.3.5 → 1.4.0 e upgrade 1.4.0 → 1.4.1 executam o bootstrap;
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
- sintaxe de 346 arquivos PHP com `php -l`;
- sintaxe de 29 scripts Bash/entrypoints Docker com `bash -n`;
- parsing dos arquivos JSON e YAML;
- verificação de que os exemplos de playground não são ignorados pelo Git;
- geração e conferência do manifesto SHA-256;
- aplicação do patch sobre uma cópia limpa da versão 1.4.0 revisão 5;
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

## Revisão 4 — workflow 80445390597

Correções verificadas estaticamente:

- a assinatura de `radiushub:health` não redefine a opção global `--quiet`;
- o comando usa o nível de verbosidade do output do Symfony;
- o entrypoint inicia apenas o master `php-fpm` sem `gosu`;
- worker, scheduler e CLI permanecem executados como `www-data`;
- a imagem de runtime declara `USER root` para bootstrap e redução controlada de privilégios.

A validação integral dos containers permanece no GitHub Actions, onde o erro original foi observado.
## Revisão 5 — alteração posteriormente revertida pela 1.4.1

A revisão 5 tentou reduzir o tempo do PR condicionando smokes completos. Essa decisão foi considerada incorreta e foi revertida integralmente na 1.4.1. Historicamente, ela havia feito:

- `push` do CI limitado à `main`;
- PR executa somente qualidade, MySQL, PostgreSQL e contratos Docker/Compose;
- smokes completos condicionados à `main`, execução manual ou rótulo `full-validation`;
- removida a construção redundante das imagens `app`, `web` e `freeradius`;
- `docker-publish.yml` não reage mais a push/tag automaticamente.

A falha `Access-Accept` foi tratada com:

- desativação da recarga NAS no playground;
- fingerprint estável dos campos RADIUS no ambiente produtivo;
- preflight da credencial criptografada e do NAS;
- `radclient` com timeout e repetição controlados;
- diagnóstico ampliado em caso de falha.
