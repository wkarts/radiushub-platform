# Validação da entrega 1.3.3

## Log analisado

Workflow GitHub Actions `80393755885`.

## Causa-raiz confirmada

O pacote 1.3.2 continha simultaneamente duas migrations com a mesma sequência `2026_07_19_000800`:

- `2026_07_19_000800_secure_asaas_webhook_per_gateway.php`;
- `2026_07_19_000800_secure_asaas_webhooks_by_gateway.php`.

A primeira migration concluía corretamente as alterações do webhook. Em seguida, a segunda tentava adicionar novamente as mesmas colunas. Isso produziu:

- SQLite/PHP 8.3 e 8.4: `duplicate column name: webhook_public_token`;
- MySQL 8.4: `Duplicate column name 'webhook_public_token'`;
- PostgreSQL 17: `column "webhook_public_token" already exists`.

Os 13 testes de feature falharam pelo mesmo erro de bootstrap do banco; não eram 13 defeitos independentes. As três imagens Docker foram construídas com sucesso.

## Correção aplicada

- removida a migration duplicada plural;
- preservada a migration singular, idempotente e retomável;
- adicionado `scripts/check-migration-integrity.php`;
- o verificador rejeita nomes fora do padrão, sequências duplicadas e a migration obsoleta;
- adicionado teste unitário de inventário de migrations;
- CI executa `composer migrations:check` em PHP, MySQL e PostgreSQL;
- instaladores, atualizadores e entrypoint Docker validam as migrations antes de executar `artisan migrate`;
- upgrade 1.3.2 → 1.3.3 remove o arquivo obsoleto com backup antes da migração.

## Validações executadas neste ambiente

- integridade do inventário de migrations;
- sintaxe de todos os arquivos PHP com `php -l`;
- sintaxe dos scripts Bash com `bash -n`;
- parsing dos arquivos JSON e YAML;
- conferência das referências de versão;
- busca por credenciais expostas e chaves privadas;
- geração de árvore e manifesto SHA-256;
- teste de integridade do ZIP.

## Validações executadas pelo workflow após o push

- Composer e PHPUnit em PHP 8.3 e 8.4;
- migrations, seeders, doctor e renderização FreeRADIUS no MySQL 8.4;
- migrations, seeders, doctor e renderização FreeRADIUS no PostgreSQL 17;
- build das imagens app, web e FreeRADIUS.

O ambiente local não possui Composer, Docker Engine, MySQL ou PostgreSQL. Portanto, a matriz completa não foi executada localmente; a causa-raiz, contudo, foi reproduzida diretamente pelos logs e removida do inventário de migrations.
