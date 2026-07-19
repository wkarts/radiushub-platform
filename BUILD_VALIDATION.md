# Validação da entrega 1.3.2

## Log analisado

Workflow GitHub Actions `80361125869`.

Falhas confirmadas nos logs:

- PHP 8.3 e 8.4: `CompanyController::authorize()` inexistente devido ao controller base incompleto;
- efeito secundário: empresa não criada e `ModelNotFoundException` no teste de provisionamento;
- MySQL 8.4: erro 1553 ao remover índice do webhook ainda utilizado pela foreign key `tenant_id`;
- warnings do PHPUnit apresentados de forma truncada;
- avisos de runtime antigo nas ações GitHub.

Os jobs PostgreSQL 17 e as imagens Docker app, web e FreeRADIUS concluíram sem a falha funcional acima.

## Correções verificadas estaticamente

- controller base herda `Illuminate\Routing\Controller`;
- traits `AuthorizesRequests` e `ValidatesRequests` restaurados;
- migration do webhook cria índice substituto em DDL anterior ao `dropUnique`;
- migrations 000700 e 000800 possuem retomada idempotente para DDL parcial conhecido;
- retomada da migration 000700 valida a estrutura antes de concluir somente os índices;
- tokens e índices do webhook não são recriados quando já existem;
- `.env.testing` é preparado no CI;
- PHPUnit executa com `--display-warnings`;
- ações checkout/cache/Docker atualizadas;
- testes de regressão adicionados para o controller base e ordem de DDL.

## Validações executadas no ambiente de geração

- sintaxe de todos os arquivos PHP com `php -l`;
- sintaxe de todos os scripts Bash com `bash -n`;
- parsing de JSON e YAML;
- verificação de referências de versão;
- busca por credenciais expostas e blocos de chave privada;
- geração do manifesto SHA-256 e árvore do projeto;
- teste de integridade do ZIP.

## Validações delegadas ao CI/homologação

O ambiente de geração não possui Composer, Docker Engine, MySQL ou PostgreSQL. Por isso, as seguintes verificações ficam configuradas no workflow:

- Composer e PHPUnit em PHP 8.3/8.4;
- migrations, seeders e doctor no MySQL 8.4 e PostgreSQL 17;
- renderização das configurações FreeRADIUS;
- build das imagens app, web e FreeRADIUS.
