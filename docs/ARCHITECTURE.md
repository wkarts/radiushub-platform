# Arquitetura RadiusHub 1.4

## Visão geral

```text
Navegador
   │ HTTPS
Nginx / CloudPanel
   │ FastCGI
Laravel 13 (Blade)
   ├── Multi-tenancy e RBAC
   ├── Clientes, contratos, planos, acessos e vouchers
   ├── Financeiro e Asaas
   ├── Auditoria, filas e Scheduler
   ├── SSH Key → MikroTik
   └── PostgreSQL/MySQL ← FreeRADIUS → MikroTik Hotspot/PPPoE
```

## Laravel

É a fonte oficial dos cadastros e regras empresariais. Aplica contextos de tenant/empresa, policies, permissões, auditoria e filas. O protocolo RADIUS permanece no FreeRADIUS.

## FreeRADIUS

Executa AAA em UDP 1812/1813 e lê o mesmo banco. Queries específicas suportam PostgreSQL e MySQL, usuários pré-cadastrados, vouchers, simultaneidade, limites e accounting.

Ações administrativas de sessão usam SSH Key. CoA/radclient permanece como fallback opcional e desligado por padrão.

## MikroTik SSH

`MikrotikSshService` usa phpseclib, chave privada criptografada e fingerprint do host. Todo comando é criado por `RouterOsCommandBuilder`; não existe terminal arbitrário exposto ao usuário.

O modo playground injeta `MikrotikSimulatorService` apenas em equipamentos com `connection_method=simulator` e com `PLAYGROUND_MODE=true`. A interface de cadastro normal continua criando equipamentos SSH.

## Contextos e isolamento

`TenantContext` e `CompanyContext` são definidos por request/job. Traits de model adicionam escopos e valores obrigatórios. `EnsureBoundModelsBelongToContext` impede IDOR por route model binding.

## Autorização

- Superadministrador: acesso global auditado;
- administrador de tenant: vínculo em `tenant_user`;
- papéis de empresa: `company_user`, `roles`, `permissions`;
- Policies em recursos sensíveis;
- middleware `tenant.permission` para módulos CRUD;
- permissão específica para exportação de credenciais.

## Filas

- `network`: sincronizações e controle MikroTik;
- `webhooks`: eventos Asaas;
- `default`: tarefas gerais.

Workers restauram tenant/empresa antes de consultar models. Scheduler reconcilia vouchers, sessões, faturas e webhooks.

## Deploy

### Docker

`app`, `web`, `worker`, `scheduler` e `freeradius` aguardam dependências saudáveis. O `app` aguarda o banco, migra, faz seed opcional e expõe readiness.

### CloudPanel

Pode executar PHP nativo com Supervisor/Cron/FreeRADIUS local ou servir como reverse proxy para o stack Docker.

## Observabilidade

- `/health/live`: processo web ativo;
- `/health/ready`: banco, cache e storage prontos;
- `radiushub:health --ready`: readiness via CLI;
- `radiushub:doctor`: valida configuração, criptografia, SDKs e segurança;
- logs de conexão/comando MikroTik e auditoria de negócio.

## Playground

O playground semeia um cenário multiempresa e executa smoke tests de:

- login e sessão;
- endpoints de saúde;
- simulador MikroTik;
- RADIUS Access-Accept;
- accounting e persistência no banco.

Ele não afirma substituir homologação com RouterOS e Asaas reais.
