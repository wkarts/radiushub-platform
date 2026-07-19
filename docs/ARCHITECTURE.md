# Arquitetura RadiusHub 1.3

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

## Responsabilidades

### Laravel

Fonte oficial dos cadastros e regras empresariais. Não implementa o protocolo RADIUS diretamente.

### FreeRADIUS

Executa AAA em UDP 1812/1813 e consulta o mesmo banco. O fluxo de autenticação permanece RADIUS; as ações administrativas de desconexão e limite temporário usam SSH Key.

### SSH

Canal de administração, sincronização e controle de sessões. phpseclib abre a sessão usando chave privada criptografada. Todos os comandos passam por allowlist e têm log separado. O fallback CoA legado permanece desligado por padrão.

### Banco

Suporta PostgreSQL e MySQL. As credenciais RADIUS são criptografadas em formato legível pelas queries específicas de cada dialeto.

### Redis

Opcional no CloudPanel nativo; recomendado no Docker para cache e filas. Login usa cache limiter configurável, por padrão banco, evitando indisponibilidade quando Redis falha.

## Contextos

`TenantContext` e `CompanyContext` existem por request/job. Traits de model aplicam scopes e impedem criação sem contexto.

## Filas

- `network`: sincronização SSH;
- `webhooks`: eventos Asaas;
- `default`: tarefas gerais.

Jobs de rede usam retry/backoff e restauram tenant/empresa antes de acessar models.

## Compatibilidade

Campos da antiga API RouterOS permanecem no banco para upgrades sem perda, mas nenhum fluxo ativo os utiliza. A comunicação administrativa oficial é SSH Key.
