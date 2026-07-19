# Correções de CI — RadiusHub Platform 1.3.1

## Causas encontradas

1. O MySQL recusava remover índices únicos que também sustentavam FKs de `tenant_id`.
2. `storage/framework/views` não estava garantido após checkout/empacotamento.
3. O sanitizador não reconhecia o cabeçalho genérico `BEGIN PRIVATE KEY`.
4. A relação `User::tenants()` consultava `active` sem qualificar a tabela.

## Soluções

- criação dos novos índices multiempresa antes da remoção dos índices antigos;
- restauração na ordem inversa durante rollback;
- preparação explícita dos diretórios Laravel no CI e no `Tests\TestCase`;
- placeholders versionáveis nos diretórios de runtime;
- sanitização ampliada para os formatos usuais de chave privada;
- uso de `tenants.active` nas consultas `belongsToMany`.

## Validação recomendada

```bash
composer install
composer lint
php artisan test

DB_CONNECTION=mysql php artisan migrate:fresh --seed --force
DB_CONNECTION=pgsql php artisan migrate:fresh --seed --force

docker compose build
```
