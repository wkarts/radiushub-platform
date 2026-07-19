# Multi-tenancy, empresas e RBAC

## Hierarquia

```text
Plataforma
└── Tenant
    ├── Empresa A
    ├── Empresa B
    └── Usuários vinculados por empresa e papel
```

- O Superadministrador possui acesso global.
- O Administrador do tenant pode administrar apenas o tenant atual.
- Usuários comuns acessam somente empresas presentes em `company_user`.
- Registros operacionais possuem `tenant_id` e `company_id`.

## Isolamento

Models operacionais usam `BelongsToTenant` e `BelongsToCompany`:

- global scopes limitam queries aos contextos atuais;
- eventos `creating` preenchem IDs automaticamente;
- criação sem contexto gera exceção;
- Form Requests validam `exists` e `unique` dentro da empresa;
- middleware troca tenant/empresa apenas entre vínculos autorizados;
- Policies e permissões validam operações críticas;
- `EnsureBoundModelsBelongToContext` valida todo model resolvido pela rota contra o tenant e a empresa atuais, impedindo acesso por UUID pertencente a outro escopo.

`withoutGlobalScopes()` é reservado para rotinas globais explícitas, como Superadmin, migrations, doctor e jobs que restabelecem os contextos antes da operação.

## Papéis mínimos

- Superadministrador da plataforma;
- Administrador do tenant (papel legado de vínculo ao tenant);
- Administrador da empresa;
- Operador;
- Atendente;
- Técnico;
- Financeiro;
- Consulta.

Papéis de empresa possuem permissões granulares por módulo. Papéis customizados podem ser criados sem alterar os papéis de sistema.

## Criação de empresa com administrador

A criação ocorre em transação:

1. cria a empresa no tenant atual;
2. cria ou vincula o usuário administrador;
3. vincula papel e empresa;
4. vincula o usuário ao tenant sem elevar para `tenant_admin`;
5. marca troca obrigatória de senha para usuário novo;
6. opcionalmente envia link para definição de senha;
7. registra auditoria.

A opção **Criar usuário administrador** pode ser desmarcada.

## Regra de privilégio

Um administrador de empresa não pode atribuir papel de tenant, acessar outra empresa ou promover a si próprio. Apenas Superadmin/Administrador de tenant gerenciam empresas e papéis do tenant.

## Limites de utilização

Os limites podem ser definidos em `tenants.usage_limits` e sobrescritos por `companies.usage_limits`. A empresa sempre respeita o menor limite efetivo aplicável.

Chaves suportadas:

- `companies`;
- `users`;
- `mikrotiks`;
- `subscribers`;
- `plans`;
- `accesses`;
- `vouchers`.

Valor ausente, nulo ou `0` significa ilimitado. As validações são executadas no backend antes da criação e não dependem da interface. Exclusões lógicas e registros inativos seguem as regras específicas de cada módulo.
