# Análise técnica e evolução 1.3.0

## Estratégia de evolução

A versão 1.3.0 preserva o monólito Laravel/Blade, a camada financeira, a integração Asaas SDK ARGWS, os modelos RADIUS, os deploys Docker/CloudPanel e os fluxos existentes. A evolução foi feita por migrations aditivas, campos legados preservados e serviços novos isolados por responsabilidade.

## Decisões principais

- **AAA permanece no FreeRADIUS:** autenticação, autorização e accounting continuam nos protocolos próprios.
- **Administração do MikroTik por SSH Key:** testes, inventário, sincronizações, bloqueios e comandos controlados passam pelo serviço SSH com allowlist.
- **Compatibilidade:** campos antigos da API RouterOS permanecem no banco para evitar quebra de dados, mas não são usados nos fluxos administrativos ativos.
- **Multiempresa:** `tenant_id` e `company_id` são aplicados por contextos, global scopes, policies e validações backend.
- **RBAC:** papéis de sistema são imutáveis nos pontos críticos e permissões são verificadas nos controllers, policies, menus e exports.
- **Vouchers:** geração transacional, senhas criptografadas, validade fixa/primeiro uso, estados de ciclo de vida, PDF/CSV/impressão e sincronização assíncrona.
- **Operações remotas:** comandos são construídos por chaves conhecidas, parâmetros são validados/escapados e toda execução gera histórico sanitizado.
- **Webhooks Asaas:** cada gateway/empresa possui endpoint público secreto próprio, resolvido por hash e validado também pelo cabeçalho `asaas-access-token`; nenhuma URL expõe tenant, slug ou UUID interno.

## Correções de regressão incluídas

- preservação dos campos `subscription_plan`, `usage_limits` e `status` dos tenants durante a migration;
- empresa padrão de tenant inativo com status válido `suspended`;
- fingerprint do host conferida antes da autenticação SSH;
- redirecionamento correto do Superadministrador ao dashboard global;
- limpeza de contextos tenant/empresa em jobs longos;
- teste de schema para impedir regressão nas tabelas centrais;
- validação explícita dos models de rota após resolução dos contextos, evitando IDOR entre empresas/tenants.

## Segurança

Chaves privadas, passphrases, senhas de contingência e credenciais financeiras usam criptografia da aplicação. Logs passam por sanitização e nunca devem conter material de chave, senha, token ou segredo. A allowlist impede execução de comandos arbitrários. O fallback por senha e o fallback CoA permanecem desativados por padrão.

## Compatibilidade de banco e implantação

A aplicação suporta MySQL e PostgreSQL. O pacote contém Docker Compose, imagens separadas para aplicação/web/FreeRADIUS, exemplos de ambiente, scripts de instalação/upgrade/backup/doctor e workflows para CI, GHCR e release. A instalação nativa no CloudPanel usa cache/fila em banco por padrão, evitando dependência do hostname Docker `redis`.

## Validação obrigatória em homologação

Antes de produção, execute migrations e testes em ambos os bancos, `freeradius -XC`, autenticação Hotspot/PPPoE, accounting Start/Interim/Stop, SSH com fingerprint fixa, sincronização de perfis/acessos/vouchers, CoA fallback quando habilitado, webhooks Asaas em Sandbox, worker `network` e Scheduler.
