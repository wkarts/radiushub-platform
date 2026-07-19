# Changelog

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
