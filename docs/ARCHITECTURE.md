# Arquitetura

## Fluxo AAA

```text
MikroTik -> UDP 1812 -> FreeRADIUS -> PostgreSQL -> Access-Accept/Reject
MikroTik -> UDP 1813 -> FreeRADIUS -> radius_accounting
Laravel  -> UDP 3799 -> MikroTik -> CoA/Disconnect ACK/NAK
```

O Laravel administra o domínio e o FreeRADIUS executa o protocolo. Não existe servidor RADIUS artesanal em PHP.

## Identificação do tenant

O `radius_source_ip` do MikroTik identifica o tenant. As queries do FreeRADIUS combinam:

```text
NAS-IP-Address + User-Name + tenant_id
```

Assim `cliente01` pode existir em várias empresas sem colisão.

## Credenciais

Para CHAP/PPPoE, o FreeRADIUS precisa recuperar o segredo equivalente. O projeto usa `pgcrypto` com chave externa `RADIUS_CREDENTIAL_KEY`. A chave não fica no banco e é injetada somente nos containers autorizados.

## Módulos

- Tenancy e RBAC;
- Cadastro e contratos;
- Network Access;
- MikroTik API;
- RADIUS/Accounting/CoA;
- Billing e Webhooks;
- Jobs e Scheduler;
- Auditoria e Health.
