# Changelog

## 1.4.3 — 2026-07-20

### Corrigido

- Sintaxe do bloco `authenticate` no virtual server FreeRADIUS.
- Falha `Parse error after "pap": unexpected token "}"` no parser 3.2.10.
- Declarações compactadas de PAP, CHAP e MS-CHAP.

### Proteções

- Verificação de chaves balanceadas no `sites-enabled/default`.
- Rejeição de blocos nomeados compactados em uma linha.
- Teste de regressão específico para os três `Auth-Type`.
- Parser real PostgreSQL/MySQL e validação integral da Pull Request preservados.

## 1.4.2 — 2026-07-20

### Corrigido

- Sintaxe inválida do bloco `pool` nos módulos SQL MySQL e PostgreSQL do FreeRADIUS.
- Falha `Expected comma after '5'` durante `freeradius -XC`.
- Container FreeRADIUS entrando em estado `unhealthy` antes do smoke.

### Proteções

- Verificador estrutural independente para templates FreeRADIUS.
- Parser real do FreeRADIUS executado para MySQL e PostgreSQL durante o build da imagem.
- Diagnóstico do SQL renderizado com linhas numeradas e credenciais mascaradas.
- Testes de regressão para impedir retorno do bloco `pool` em linha única.
- Validação integral do Pull Request preservada.

## 1.4.1 — 2026-07-20

### Corrigido

- restaura Docker Playground e CloudPanel nativo como validações obrigatórias de todo Pull Request;
- remove dependência do label `full-validation` e da execução posterior ao merge;
- mantém `push` do CI restrito à `main`, evitando duplicação com `pull_request`;
- constrói app, web e FreeRADIUS no próprio job integral antes do smoke;
- corrige o diretório de configuração da imagem oficial FreeRADIUS 3.2.10;
- detecta o `radiusd.conf` efetivamente carregado pelo binário;
- impede inicialização quando o módulo SQL for ignorado;
- exige carregamento de `rlm_sql` antes de aceitar o container como válido;
- aplica a mesma descoberta e validação ao instalador FreeRADIUS nativo;
- preserva timeout curto do `radclient`, preflight RADIUS e fingerprint estável de NAS.

### Compatibilidade

- nenhuma migration nova;
- nenhuma funcionalidade, tabela, rota ou contrato público removido;
- atualização incremental sobre a versão 1.4.0.

## 1.4.0 revisão 5 — estratégia de CI posteriormente revertida

- Restringe o evento `push` do CI à branch `main`, eliminando a execução duplicada `push` + `pull_request` para cada commit do PR.
- Remove a matriz redundante de build das três imagens Docker do CI de PR.
- Mantém validação leve de Docker e Compose em todo PR.
- Executa os smokes completos de Docker Playground e CloudPanel somente na `main`, manualmente ou em PR com o rótulo `full-validation`.
- Torna `docker-publish.yml` exclusivamente manual; a publicação automática permanece centralizada em `release.yml`.
- Desabilita recarga automática de clientes NAS no playground para impedir HUP durante o smoke.
- Troca o monitoramento de `updated_at` por fingerprint apenas dos campos RADIUS relevantes em produção.
- Reduz o timeout do `radclient` e adiciona preflight da credencial/NAS antes do `Access-Accept`.
- Adiciona testes de regressão para o pipeline rápido e para a estabilidade do FreeRADIUS.

## 1.4.0 revisão 4 — correção do workflow 80445390597

- Corrige a inicialização do PHP-FPM no Docker Playground mantendo o master com os privilégios necessários e os workers como `www-data`.
- Mantém worker, scheduler e comandos CLI executados por `gosu www-data`.
- Remove a declaração duplicada da opção global `--quiet` no comando `radiushub:health`.
- Preserva o healthcheck `radiushub:health --ready --quiet`.
- Adiciona testes de regressão para o Symfony Console e para o entrypoint Docker.
- Documenta as duas causas-raiz do workflow 80445390597.

## 1.4.0 revisão 3 — 2026-07-19

- Corrige a resolução do simulador MikroTik com binding explícito no container, preservando compatibilidade do construtor.
- Corrige o teste idempotente do playground para não depender do e-mail de seed do `.env.testing`.
- Corrige a instalação CloudPanel nativa quando o cache usa banco antes da criação da tabela `cache`.
- Padroniza a limpeza segura de caches nos instaladores e scripts de atualização.
- Adiciona testes de regressão para o simulador e para a ordem de bootstrap do CloudPanel.

## 1.4.0 — 2026-07-19

### Adicionado

- playground Docker completo com PostgreSQL, Redis, Laravel, Nginx, worker, Scheduler e FreeRADIUS;
- playground nativo para CloudPanel com domínio/banco isolados;
- instalador integrado Docker + reverse proxy CloudPanel para produção ou playground;
- simulador MikroTik limitado ao modo playground;
- dados demonstrativos multiempresa, acessos, vouchers, financeiro, accounting e auditoria;
- endpoints `/health/live` e `/health/ready` e comando `radiushub:health`;
- smoke de login HTTP, RADIUS `Access-Accept` e accounting `Accounting-Response`;
- verificação pós-deploy por `scripts/validate-deployment.sh`;
- dependências de saúde entre app, worker, Scheduler, Nginx e FreeRADIUS no Compose;
- CI de playground Docker e instalação nativa semelhante ao CloudPanel;
- contrato automatizado de conformidade para rotas, domínio, RBAC, interface e deploy;
- comando independente `scripts/check-planning-compliance.php` para auditoria estática antes do deploy;
- matriz de conformidade do planejamento e documentação operacional do playground.

### Corrigido

- arquivos `.env.playground.example` e `.env.cloudpanel.playground.example` passam a ser efetivamente versionados e distribuídos;
- CI valida a presença desses arquivos imediatamente após o checkout;
- scripts recebem permissão executável no CI e os fluxos críticos são chamados explicitamente por `bash`;
- instalação e atualização reconciliam o Superadministrador, tenant e empresa padrão;
- conta principal recebe login explícito por `SEED_ADMIN_LOGIN`;
- senha de Superadministrador existente é preservada por padrão;
- login e 2FA de Superadministrador ignoram URL de tenant armazenada anteriormente na sessão;
- Superadministrador sem tenant é enviado ao painel global em vez de receber 403;
- administrador de tenant sem empresa é direcionado ao cadastro da empresa;
- páginas 403/404 evitam ciclos de navegação para uma rota que exige contexto inexistente;
- adicionado reparo idempotente `scripts/repair-cloudpanel-bootstrap.sh` para instalações CloudPanel anteriores;
- upgrade/reparo atualizam `APP_VERSION` e removem hostname Docker `redis` de instalações PHP nativas, usando cache/filas em banco como fallback seguro.

### Segurança

- simulador recusado fora de `PLAYGROUND_MODE=true`;
- playground bloqueado em `APP_ENV=production` sem autorização explícita;
- portas do playground vinculadas a `127.0.0.1` por padrão;
- instalação Docker no CloudPanel valida o stack local antes de exigir a aplicação do proxy HTTPS;
- playground publicado por domínio desativa `APP_DEBUG` automaticamente;
- exemplos de produção mantêm todos os recursos de playground desabilitados;
- exemplos Docker de produção usam URL HTTPS e cookie de sessão seguro por padrão;
- backfill legado de RBAC promove somente administradores de tenant, sem elevar operadores ou técnicos;
- instalador CloudPanel valida a extensão PDO específica do banco e a extensão Redis quando ativada;
- senhas e segredos de playground são gerados localmente e o `.env` recebe modo `600`.

### Compatibilidade

- nenhuma migration nova e nenhuma tabela removida;
- arquitetura Laravel/Blade, MySQL, PostgreSQL, Docker, CloudPanel, FreeRADIUS, SSH Key, vouchers e Asaas preservados;
- atualização incremental a partir da versão 1.3.5.

## 1.3.5 — 2026-07-19

### Corrigido

- job final da release realiza checkout próprio do commit versionado;
- GitHub CLI recebe `GH_REPO` e `--repo`, eliminando dependência implícita de `.git`;
- publicação valida que a tag aponta para o commit aprovado;
- artefatos são verificados antes e depois da criação da release;
- comandos de criação e upload possuem retentativa limitada para falhas transitórias;
- execução manual recupera a tag `v1.3.4` que ficou sem GitHub Release.

### Compatibilidade

- aplicação, banco, Docker, CloudPanel, FreeRADIUS, MikroTik, vouchers e Asaas permanecem inalterados.

## 1.3.4 — 2026-07-19

### Corrigido

- release deixa de depender exclusivamente de tag manual;
- merge na `main` gera a release automaticamente somente depois do CI aprovado;
- workflow cria de forma idempotente a tag correspondente ao arquivo `VERSION`;
- releases existentes não são duplicadas;
- tags existentes sem release são recuperadas automaticamente;
- artefatos ZIP e TAR.GZ passam a ser gerados diretamente do commit versionado;
- checksums SHA-256 e metadados da release são anexados;
- imagens GHCR recebem tags `X.Y.Z`, `X.Y`, `latest` e `sha-*`;
- validação de consistência impede publicar versão divergente entre código, Docker e exemplos de ambiente;
- execução manual permite reconstruir artefatos de uma release existente.

### Compatibilidade

- nenhuma funcionalidade de aplicação ou banco foi removida;
- CI, Docker, CloudPanel, FreeRADIUS, MikroTik, vouchers e Asaas permanecem preservados.

## 1.3.3 — 2026-07-19

### Corrigido

- removida a migration duplicada `2026_07_19_000800_secure_asaas_webhooks_by_gateway.php`;
- mantida como canônica a migration retomável `2026_07_19_000800_secure_asaas_webhook_per_gateway.php`;
- instalações novas não tentam mais adicionar duas vezes `webhook_public_token`, `webhook_public_token_hash`, `company_id` e `payment_gateway_config_id`;
- migrations voltam a executar em SQLite, MySQL 8.4 e PostgreSQL 17;
- adicionado verificador de inventário que bloqueia sequências de migration duplicadas antes de qualquer alteração de banco;
- CI, instaladores CloudPanel/Docker, entrypoint e atualizadores executam a verificação preventiva;
- upgrade 1.3.2 → 1.3.3 remove com backup o arquivo obsoleto que possa permanecer após atualização por sobreposição.

### Compatibilidade

- nenhuma tabela ou funcionalidade foi removida;
- registros já existentes na tabela `migrations` não precisam ser apagados;
- Laravel/Blade, MySQL, PostgreSQL, Docker, CloudPanel, FreeRADIUS, MikroTik SSH Key, vouchers e Asaas foram preservados.

## 1.3.2 — 2026-07-19

### Corrigido

- controller base do Laravel restaura `AuthorizesRequests` e `ValidatesRequests`;
- criação de empresa volta a executar policies sem erro de método inexistente;
- migration do webhook Asaas cria índice substituto de `tenant_id` antes de remover o índice único legado no MySQL;
- migrations multiempresa e webhook passam a tolerar retomada após DDL parcialmente confirmado pelo MySQL;
- workflow prepara `.env.testing` e exibe avisos completos do PHPUnit;
- GitHub Actions foram atualizadas para runtimes Node.js 24.

### Compatibilidade

- nenhuma funcionalidade existente foi removida;
- arquitetura Laravel/Blade, MySQL, PostgreSQL, Docker, CloudPanel, FreeRADIUS, MikroTik SSH e Asaas foram preservados.

## 1.3.1 — 2026-07-19

### Corrigido

- migrations MySQL agora criam os índices multiempresa antes de remover os índices antigos usados pelas chaves estrangeiras;
- sanitização remove blocos `PRIVATE KEY`, `RSA PRIVATE KEY`, `OPENSSH PRIVATE KEY`, `EC PRIVATE KEY` e equivalentes;
- diretórios graváveis do Laravel são preparados no CI e permanecem presentes no pacote/repositório;
- testes não falham mais com `Please provide a valid cache path`;
- consultas da relação usuário → tenants qualificam `tenants.active`, evitando coluna ambígua;
- fluxo de criação de empresa e isolamento de empresa volta a executar sem erro no middleware de tenant.

### Compatibilidade

- nenhuma funcionalidade da versão 1.3.0 foi removida;
- banco, arquitetura Laravel/Blade, FreeRADIUS, MikroTik SSH Key, vouchers e Asaas foram preservados;
- correção incremental compatível com CloudPanel, Docker, MySQL e PostgreSQL.

## 1.3.0 — 2026-07-19

### Adicionado

- estrutura completa tenant → empresas;
- RBAC por empresa e papéis de sistema;
- criação opcional do administrador da empresa;
- login por e-mail/login, troca obrigatória e TOTP/2FA;
- integração MikroTik por SSH Key com validação, fingerprint, inventário, allowlist e logs;
- fallback por senha desativado por padrão em nível global e por equipamento;
- sincronização manual e automática de perfis, acessos e vouchers;
- vouchers em lote, validade fixa/primeiro acesso, impressão, CSV e PDF;
- dashboard global e dashboard por empresa;
- auditoria ampliada e sanitização de dados sensíveis;
- menu lateral colapsável, submenus flyout e interface mobile;
- queries FreeRADIUS multiempresa para MySQL/PostgreSQL;
- fila `network`, jobs com retry/backoff e reconciliação de vouchers;
- documentação de SSH, vouchers, RBAC, CRUD, segurança e upgrade;
- controle administrativo de sessões por SSH (disconnect e rate-limit), mantendo CoA apenas como fallback opcional;
- limites de uso por tenant/empresa para empresas, usuários, MikroTiks, clientes, planos, acessos e vouchers;
- permissão específica para exportação de vouchers e modelos de impressão personalizáveis;
- teste de regressão do schema multiempresa, SSH, vouchers e RBAC;
- middleware de validação de models vinculados à rota para bloquear acesso cruzado entre tenants/empresas.
- endpoint Asaas secreto e exclusivo por empresa/gateway, sem expor tenant, slug ou UUID interno;
- rotação de URL e comando `asaas:webhooks:sync` para atualizar os webhooks remotos.

### Corrigido

- login quebrado por hostname Redis de Docker em instalação CloudPanel;
- validações `exists`/`unique` agora respeitam empresa;
- pesquisas incompatíveis com MySQL;
- workers incluem fila de sincronização de rede;
- health check usa SSH em vez da API RouterOS legada;
- upgrade 1.2 → 1.3 preserva `APP_KEY` e credenciais;
- migration preserva os novos campos de assinatura/status/limites do tenant durante o `up()`;
- status da empresa padrão é compatível com o domínio (`active`/`suspended`);
- fingerprint SSH é validada antes do envio das credenciais e pode ser fixada por TOFU controlado;
- Superadministrador é direcionado ao dashboard global após login/2FA;
- Scheduler de vouchers limpa os contextos tenant/empresa entre iterações.
- idempotência dos webhooks Asaas passou a considerar o gateway e a empresa de origem.
- processamento de pagamentos restringe a fatura ao mesmo tenant, empresa e gateway.

### Compatibilidade

- Laravel/PHP full-stack preservado;
- Asaas SDK ARGWS 0.2.62 preservado;
- MySQL e PostgreSQL;
- Docker e CloudPanel nativo;
- campos legados da API RouterOS preservados no banco, sem uso ativo.

## 1.2.0

- Docker/CloudPanel, MySQL/PostgreSQL, FreeRADIUS e correções de Redis.

## 1.1.0

- integração Asaas SDK ARGWS.

## 1.0.0

- versão inicial da plataforma.
