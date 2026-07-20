# Pull Request — RadiusHub Platform 1.4.3

## Branch

`fix/v1.4.3-freeradius-auth-type-parser`

## Título

`fix(radius): corrigir blocos Auth-Type do virtual server FreeRADIUS`

## Commit

`fix(radius): tornar authenticate compatível com o parser FreeRADIUS 3.2`

## Mensagem de merge

`fix: publicar RadiusHub Platform v1.4.3 com virtual server validado`

## Objetivo

Corrigir a falha encontrada no workflow 80457568511 sem remover, ignorar ou condicionar nenhuma validação obrigatória da Pull Request.

## Causa-raiz

A imagem FreeRADIUS passou a executar corretamente o parser real durante o build. Esse parser encontrou no `sites-enabled/default` declarações compactadas como `Auth-Type PAP { pap }` e encerrou com `Parse error after "pap": unexpected token "}"`.

## Alterações

- incrementa a versão para 1.4.3;
- converte PAP, CHAP e MS-CHAP para blocos multilinha;
- adiciona validação estrutural do virtual server;
- rejeita blocos `Auth-Type`, `Post-Auth-Type`, `Autz-Type` e `Acct-Type` compactados;
- valida balanceamento de chaves;
- exige os três tipos de autenticação e seus respectivos módulos;
- adiciona teste unitário específico;
- mantém `freeradius -XC` para PostgreSQL e MySQL durante o build;
- mantém builds app, web e FreeRADIUS;
- mantém Docker Playground, login, Access-Accept, Accounting-Response e accounting persistido;
- mantém CloudPanel nativo obrigatório antes do merge;
- não cria tag, release ou publicação de imagens durante a PR.

## Compatibilidade

- nenhuma migration nova;
- nenhuma tabela ou coluna alterada;
- nenhuma rota pública alterada;
- Laravel/Blade, MySQL, PostgreSQL, Docker e CloudPanel preservados;
- MikroTik SSH Key, vouchers, financeiro e Asaas preservados.
