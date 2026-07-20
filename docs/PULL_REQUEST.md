# Pull Request — RadiusHub Platform 1.4.1

## Branch

`fix/v1.4.1-full-pr-validation-freeradius-sql`

## Título

`fix(ci): restaurar validação integral do PR e corrigir SQL do FreeRADIUS`

## Commit

`fix(runtime): detectar configuração ativa do FreeRADIUS e validar rlm_sql`

## Mensagem de merge

`fix: publicar RadiusHub Platform v1.4.1 com PR integralmente homologado`

## Objetivo

Corrigir a falha de autenticação RADIUS encontrada no workflow 80449518778 e restaurar todas as validações do Pull Request, sem exigir label, execução manual ou merge prévio.

## Alterações

- incrementa a versão para 1.4.1;
- restaura Playground Docker e CloudPanel nativo em todo PR;
- mantém PHP 8.3/8.4, MySQL 8.4 e PostgreSQL 17;
- constrói app, web e FreeRADIUS antes de executar o smoke;
- mantém login HTTP, `Access-Accept` e `Accounting-Response` obrigatórios;
- detecta a árvore ativa do FreeRADIUS pelo `radiusd.conf` carregado;
- renderiza SQL, queries, clients e virtual server no diretório correto;
- recusa inicialização quando o log contiver `Ignoring "sql"`;
- exige evidência de carregamento do módulo `rlm_sql`;
- aplica a mesma proteção ao instalador nativo;
- não publica release ou imagens GHCR no CI do PR;
- adiciona testes de regressão contra nova redução silenciosa das validações.

## Compatibilidade

- nenhuma migration nova;
- nenhuma tabela ou rota removida;
- Laravel/Blade, MySQL, PostgreSQL, Docker e CloudPanel preservados;
- MikroTik SSH Key, vouchers, FreeRADIUS e Asaas preservados;
- atualização incremental a partir da 1.4.0.

## Validação esperada antes do merge

1. PHP 8.3 e 8.4;
2. MySQL 8.4;
3. PostgreSQL 17;
4. contratos Docker/Compose;
5. build das imagens app, web e FreeRADIUS;
6. Playground completo com login;
7. RADIUS `Access-Accept`;
8. accounting `Accounting-Response`;
9. instalação nativa equivalente ao CloudPanel.
