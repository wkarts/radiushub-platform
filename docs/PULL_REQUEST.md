# Pull Request — RadiusHub Platform 1.4.2

## Branch

`fix/v1.4.2-freeradius-sql-pool-parser`

## Título

`fix(radius): corrigir pool SQL e validar templates no build`

## Commit

`fix(radius): corrigir sintaxe do pool e executar parser no build`

## Mensagem de merge

`fix: publicar RadiusHub Platform v1.4.2 com FreeRADIUS validado`

## Objetivo

Corrigir a falha do FreeRADIUS encontrada no workflow 80453029123 sem reduzir, ignorar ou condicionar as validações obrigatórias do Pull Request.

## Causa-raiz

O módulo SQL já era renderizado em `/etc/freeradius/mods-enabled/sql`, mas o bloco `pool` colocava todas as diretivas em uma única linha. O FreeRADIUS 3.2.10 encerrou a análise com `Expected comma after '5'`, deixando o container `unhealthy`.

## Alterações

- incrementa a versão para 1.4.2;
- corrige os templates SQL MySQL e PostgreSQL;
- separa cada diretiva do `pool` em sua própria linha;
- adiciona `max_retries` e `cleanup_interval`;
- adiciona `scripts/check-freeradius-templates.php`;
- adiciona `composer radius:check`;
- executa a validação estrutural nos jobs PHP, MySQL e PostgreSQL;
- adiciona `docker/freeradius/validate-templates.sh`;
- executa `freeradius -XC` para os dois dialetos durante o build da imagem;
- usa `rlm_sql_null` somente no parser de build, sem depender de banco externo;
- mantém PostgreSQL e MySQL reais no runtime;
- melhora o diagnóstico do entrypoint com linhas numeradas e segredos mascarados;
- integra a validação ao instalador nativo;
- mantém Playground, login, Access-Accept, Accounting-Response e CloudPanel obrigatórios antes do merge;
- não cria tag, release ou publicação de imagem durante a PR.

## Compatibilidade

- nenhuma migration nova;
- nenhuma tabela ou coluna alterada;
- nenhuma rota pública alterada;
- Laravel/Blade preservado;
- MySQL e PostgreSQL preservados;
- Docker e CloudPanel preservados;
- MikroTik SSH Key preservado;
- vouchers, financeiro e Asaas preservados.

## Validação obrigatória da PR

1. PHP 8.3 e PHP 8.4;
2. testes Laravel;
3. migrations e seeders MySQL 8.4;
4. migrations e seeders PostgreSQL 17;
5. verificador estrutural dos templates;
6. parser FreeRADIUS 3.2.x durante o build;
7. build das imagens app, web e FreeRADIUS;
8. Docker Playground;
9. login HTTP;
10. Access-Accept;
11. Accounting-Response;
12. persistência de accounting;
13. CloudPanel nativo.
