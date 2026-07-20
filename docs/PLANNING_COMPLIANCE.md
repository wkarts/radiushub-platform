# Conformidade do planejamento — RadiusHub Platform 1.4.1

Esta matriz relaciona o planejamento funcional com a implementação preservada e evoluída na versão 1.4.1.

## Legenda

- **Implementado**: fluxo disponível na aplicação e protegido pelo backend.
- **Validado em CI**: existe teste automatizado ou smoke test executável no GitHub Actions.
- **Playground**: pode ser exercitado sem equipamento ou conta externa.
- **Homologação externa**: depende de MikroTik RouterOS, rede, e-mail ou credenciais Asaas reais.

## 1. MikroTik por SSH Key

| Requisito | Situação | Implementação |
|---|---|---|
| Vários equipamentos por empresa | Implementado | `MikrotikDevice`, escopos tenant/empresa e CRUD `mikrotiks` |
| IP/host, porta, usuário e chave privada | Implementado | `MikrotikDeviceRequest`, `MikrotikDeviceController` e `SshKeyVault` |
| Chave como autenticação principal | Implementado | `connection_method=ssh`; senha apenas como fallback global e local |
| Validação da chave | Implementado | `MikrotikSshService::validatePrivateKey()` |
| Teste de conexão | Implementado | rota `mikrotiks.test` e histórico de conexão |
| Fingerprint do host | Implementado | verificação antes da autenticação e TOFU controlado |
| Comandos controlados | Implementado | `RouterOsCommandBuilder` com allowlist |
| Histórico de conexões e comandos | Implementado | `mikrotik_connection_logs` e `mikrotik_command_logs` |
| Modelo, identidade e RouterOS | Implementado | inventário gravado no equipamento |
| Sincronização de perfis, acessos e vouchers | Implementado | `MikrotikSyncService` e fila `network` |
| Teste sem hardware | Playground e CI | `MikrotikSimulatorService`, disponível somente com `PLAYGROUND_MODE=true` |
| Teste com RouterOS real | Homologação externa | exige RouterOS acessível por SSH e chave pública instalada |

O simulador não substitui o transporte de produção. Cadastros criados pela interface continuam sendo salvos com `connection_method=ssh`.

## 2. Usuários pré-cadastrados e vouchers

| Requisito | Situação | Implementação |
|---|---|---|
| Usuário Hotspot/PPPoE pré-cadastrado | Implementado | `NetworkAccess`, `Subscriber`, plano, perfil, MikroTik e datas |
| Senha protegida | Implementado | `RadiusCredentialVault`; formato compatível com as queries FreeRADIUS |
| Limite de conexão e dispositivos | Implementado | `simultaneous_use`, `connection_limit` e perfil de rede |
| Geração individual/em lote | Implementado | `VoucherGeneratorService` e `VoucherBatchRequest` |
| Prefixo, sufixo, tamanho e alfabeto | Implementado | parâmetros persistidos no lote/voucher |
| Velocidade, dados, tempo e sessão | Implementado | campos próprios e atributos RADIUS |
| Validade fixa ou no primeiro acesso | Implementado | `validity_mode` e reconciliação agendada |
| Estados do voucher | Implementado | disponível, ativo, usado, expirado, bloqueado e cancelado |
| Bloquear, cancelar, renovar e reativar | Implementado | ações dedicadas no controller e auditoria |
| Impressão, CSV e PDF | Implementado | views de impressão, CSV e DomPDF |
| Primeiro/último acesso e dispositivo | Implementado | reconciliação com accounting |
| Sincronização MikroTik | Implementado | fila e sincronização SSH controlada |
| Autenticação e accounting reais no playground Docker | Validado em CI | `scripts/smoke-radius.sh` exige `Access-Accept` e `Accounting-Response` |

## 3. Multi-tenant e multiempresa

| Requisito | Situação | Implementação |
|---|---|---|
| Tenant com várias empresas | Implementado | `Tenant`, `Company` e vínculos obrigatórios |
| Superadministrador global | Implementado | `is_super_admin`, rotas `/platform` e `Gate::before` |
| Administrador de tenant | Implementado | vínculo `tenant_user.role=tenant_admin` |
| Associação usuário/empresa/papel | Implementado | `company_user` e `Role` |
| Isolamento tenant/empresa | Implementado e testado | `TenantContext`, `CompanyContext`, global scopes e `EnsureBoundModelsBelongToContext` |
| Limites por tenant e empresa | Implementado | `UsageLimitService` e campos `usage_limits` |
| Dashboard conforme escopo | Implementado | `PlatformDashboardController` e `DashboardController` |

## 4. Empresa com administrador opcional

O provisionamento é transacional e contempla:

- empresa vinculada ao tenant;
- administrador opcional;
- login, e-mail e senha inicial;
- papel de administrador da empresa;
- associação tenant/empresa;
- troca obrigatória de senha e recuperação de senha;
- auditoria da operação;
- opção de criar a empresa sem usuário.

## 5. Papéis e permissões

Papéis de sistema:

- Superadministrador;
- Administrador do tenant;
- Administrador da empresa;
- Operador;
- Atendente;
- Técnico;
- Financeiro;
- Consulta.

A autorização usa uma combinação de Policies para recursos sensíveis, middleware de permissões por rota, escopos Eloquent e validação dos models vinculados à rota. A exportação de credenciais de vouchers exige a permissão específica `vouchers.export`.

## 6. CRUDs e interface

Os módulos de tenants, empresas, usuários, papéis, clientes, contratos, planos, perfis, MikroTiks, acessos, vouchers, sessões, faturas, gateways, auditoria e saúde utilizam o mesmo conjunto de componentes Blade:

- cabeçalho e ações;
- filtros e pesquisa;
- paginação;
- tabelas desktop e cards mobile;
- formulários em modal;
- confirmação de ações críticas;
- validação backend por Form Requests;
- mensagens e estados vazios;
- controle de permissões e auditoria.

## 7. Menu e responsividade

Implementado em Blade/CSS/JavaScript sem SPA:

- menu expandido/recolhido;
- persistência local do estado;
- ícones e tooltips;
- submenus;
- drawer mobile;
- fechamento após navegação em mobile;
- rolagem interna;
- tabelas adaptadas para cards;
- modais e formulários responsivos;
- ausência de rolagem horizontal estrutural.

A validação visual final em dispositivos físicos continua sendo parte da homologação de interface.

## 8. Auditoria e segurança

Implementado:

- login, logout e falhas de autenticação;
- alterações de cadastros e permissões;
- comandos e conexões SSH;
- geração e ciclo de vida de vouchers;
- alterações de configuração e falhas de sincronização;
- usuário, tenant, empresa, IP, request ID, resultado, valores anteriores e posteriores;
- sanitização de senhas, tokens e blocos de chave privada;
- chaves e segredos criptografados;
- CSRF, queries parametrizadas/Eloquent, escaping Blade e rate limiting;
- recuperação de senha, sessão criptografada e TOTP/2FA;
- bloqueio do simulador fora do modo playground;
- bloqueio de playground em `APP_ENV=production`, salvo autorização explícita.

## 9. Deploy e operação

| Ambiente | Situação |
|---|---|
| Docker com PostgreSQL | Implementado |
| Docker com MySQL | Implementado |
| Docker Playground completo | Implementado; build e smoke completo obrigatórios em todo Pull Request |
| Docker atrás do CloudPanel | Implementado; proxy gerado e testado no próprio Pull Request |
| CloudPanel PHP nativo | Implementado |
| CloudPanel Playground | Implementado; build e smoke completo obrigatórios em todo Pull Request |
| Nginx nativo e reverse proxy | Templates separados |
| Worker e Scheduler | Docker e arquivos Supervisor/Cron |
| Liveness/readiness | `/health/live`, `/health/ready` e comando CLI |
| Verificação pós-deploy | `scripts/validate-deployment.sh` |
| Verificação estática do planejamento | `scripts/check-planning-compliance.php` |
| Release/GHCR | tag, artefatos, checksums e imagens após CI |

## 10. Limites honestos da validação

O CI pode validar aplicação, bancos, containers, FreeRADIUS, login e o simulador. Os seguintes itens só podem ser certificados em homologação externa:

1. autenticação SSH em um RouterOS real e política de chaves do modelo/versão instalada;
2. comportamento de Hotspot/PPPoE em uma rede MikroTik real;
3. firewall, NAT, VPN e latência do ambiente do cliente;
4. chamadas e webhooks de uma conta Asaas Sandbox real;
5. entrega de e-mail pelo provedor SMTP escolhido;
6. comportamento visual em todos os aparelhos físicos usados pela operação.

Esses itens não são simulados como se fossem produção; estão documentados e separados do playground.
