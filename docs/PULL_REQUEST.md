# Pull Request — RadiusHub Platform 1.3.3

## Branch

`fix/v1.3.3-duplicate-migration-guard`

## Título

`fix(ci): remover migration duplicada e proteger o inventário do RadiusHub`

## Commit

`fix(database): remover migration duplicada do webhook e validar sequências`

## Mensagem de merge

`fix: publicar RadiusHub Platform v1.3.3 com migrations consistentes`

## Descrição

### Objetivo

Corrigir as falhas do workflow GitHub Actions `80393755885`, preservando a arquitetura Laravel/Blade e todas as integrações existentes.

### Causa-raiz

A branch 1.3.2 continha duas migrations diferentes com a mesma sequência `2026_07_19_000800`. A migration canônica criava as colunas do webhook por gateway e a migration obsoleta executada logo depois tentava criá-las novamente.

O mesmo defeito derrubava:

- testes em SQLite com PHP 8.3 e 8.4;
- migrations no MySQL 8.4;
- migrations no PostgreSQL 17.

### Alterações

- remove `2026_07_19_000800_secure_asaas_webhooks_by_gateway.php`;
- mantém `2026_07_19_000800_secure_asaas_webhook_per_gateway.php` como migration canônica;
- adiciona verificador independente de integridade das migrations;
- impede sequências de timestamp duplicadas;
- impede retorno do arquivo obsoleto;
- adiciona teste unitário de inventário;
- executa a validação no CI antes dos testes e das migrations;
- executa a validação em CloudPanel, Docker e entrypoint;
- adiciona upgrade 1.3.2 → 1.3.3 com backup e remoção segura do arquivo obsoleto;
- atualiza versão, documentação, changelog e imagens.

### Compatibilidade

- nenhuma funcionalidade removida;
- nenhum dado ou tabela removido;
- não exige apagar registros da tabela `migrations`;
- preserve MikroTik SSH Key, vouchers, FreeRADIUS, multiempresa, financeiro e Asaas;
- compatível com CloudPanel, Docker, MySQL e PostgreSQL.

### Validação

- PHP 8.3 e PHP 8.4;
- SQLite em memória;
- MySQL 8.4;
- PostgreSQL 17;
- verificação de sequência única de migrations;
- builds Docker app, web e FreeRADIUS.
