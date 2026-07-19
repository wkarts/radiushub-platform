# Pull Request — RadiusHub Platform 1.3.2

## Branch

`fix/v1.3.2-definitive-ci-validation`

## Título

`fix(ci): estabilizar autorização, migrations MySQL e validações do RadiusHub`

## Commit

`fix(platform): corrigir controller base e migrations retomáveis no MySQL`

## Mensagem de merge

`fix: publicar RadiusHub Platform v1.3.2 com validações estáveis`

## Descrição

### Objetivo

Resolver as falhas remanescentes do workflow GitHub Actions `80361125869`, preservando integralmente a arquitetura Laravel/Blade, MikroTik SSH Key, vouchers, FreeRADIUS, multiempresa e integração Asaas.

### Causas-raiz corrigidas

- `CompanyController::authorize()` inexistente porque o controller base não herdava o controller de roteamento do Laravel nem utilizava `AuthorizesRequests`;
- migration do webhook removia no MySQL um índice ainda utilizado pela foreign key de `tenant_id`;
- migrations DDL não podiam ser retomadas com segurança após falha parcial no MySQL;
- warnings de teste eram truncados e o ambiente de teste não possuía `.env.testing` explícito;
- workflows ainda utilizavam ações baseadas no runtime Node anterior.

### Alterações

- restaura `BaseController`, `AuthorizesRequests` e `ValidatesRequests`;
- cria o índice substituto do webhook em DDL separado antes de remover o índice legado;
- torna as migrations 000700 e 000800 idempotentes nos pontos de falha conhecidos;
- valida a integridade estrutural antes de retomar migration parcialmente executada;
- evita duplicação de tokens de webhook e índices durante reexecução;
- adiciona `.env.testing` no job de qualidade;
- habilita `--display-warnings` no PHPUnit;
- atualiza checkout, cache e ações Docker;
- adiciona upgrade CloudPanel/Docker 1.3.1 → 1.3.2;
- mantém todos os recursos e contratos públicos existentes.

### Validação

- sintaxe integral de PHP;
- sintaxe de scripts Bash;
- parsing de JSON e YAML;
- verificação da ordem de DDL dos índices;
- verificação de retomada das migrations;
- matriz CI para PHP 8.3/8.4, MySQL 8.4 e PostgreSQL 17;
- builds das imagens app, web e FreeRADIUS.
