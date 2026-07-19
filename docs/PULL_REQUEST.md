# Pull Request — RadiusHub Platform 1.3.1

## Branch

`fix/v1.3.1-ci-mysql-tests`

## Título

`fix(ci): corrigir migrations MySQL, cache Blade e testes de segurança`

## Commit principal

`fix(platform): estabilizar CI no MySQL e corrigir testes multiempresa`

## Mensagem de merge

`fix: publicar RadiusHub Platform v1.3.1 com CI estável em PHP, MySQL, PostgreSQL e Docker`

## Objetivo

Corrigir as falhas identificadas no workflow de CI da versão 1.3.0 sem remover funcionalidades ou alterar abruptamente a arquitetura Laravel/Blade, FreeRADIUS, MikroTik SSH Key, vouchers e Asaas.

## Causas corrigidas

- remoção de índice MySQL ainda utilizado por chave estrangeira;
- ausência do diretório de cache das views Blade após checkout;
- sanitizador sem suporte ao formato genérico `BEGIN PRIVATE KEY`;
- consulta ambígua do campo `active` na relação usuário → tenants;
- falhas derivadas nos testes de login, criação de empresa e isolamento multiempresa.

## Solução

- cria os índices multiempresa antes de remover os índices antigos;
- restaura índices na ordem segura durante rollback;
- prepara diretórios graváveis no CI e no bootstrap dos testes;
- mantém placeholders versionáveis nos diretórios de runtime;
- amplia a sanitização de chaves e credenciais;
- qualifica `tenants.active` nas relações many-to-many;
- limpa a empresa selecionada ao trocar de tenant.

## Compatibilidade

- nenhuma API ou funcionalidade existente foi removida;
- migrations continuam incrementais;
- compatível com PHP 8.3/8.4, MySQL 8.4, PostgreSQL 17, CloudPanel e Docker;
- integração Asaas SDK ARGWS e FreeRADIUS preservadas.

## Validações

- `php -l` em todos os arquivos PHP;
- validação de JSON, YAML e scripts Bash;
- teste isolado do sanitizador;
- verificação da presença dos diretórios de runtime no pacote;
- execução integral do CI recomendada após o push.
