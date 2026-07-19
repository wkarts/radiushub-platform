# Upgrade RadiusHub 1.3.2 para 1.3.3

A versão 1.3.2 publicou acidentalmente duas migrations com a sequência `2026_07_19_000800`, ambas alterando os mesmos campos e índices do webhook Asaas. Em instalações novas isso causava coluna duplicada no SQLite, MySQL e PostgreSQL.

A versão 1.3.3 mantém somente a migration canônica:

```text
database/migrations/2026_07_19_000800_secure_asaas_webhook_per_gateway.php
```

A migration removida é:

```text
database/migrations/2026_07_19_000800_secure_asaas_webhooks_by_gateway.php
```

## CloudPanel nativo

Preserve `.env`, `APP_KEY`, banco de dados, `storage/` e credenciais criptografadas. Depois de substituir os arquivos:

```bash
cd /home/argws-radius/htdocs/radius.home.argws.com.br
chmod +x scripts/*.sh
./scripts/upgrade-1.3.2-to-1.3.3.sh
```

O script cria backup da pasta de migrations, remove a migration obsoleta caso ela tenha permanecido por sobreposição de arquivos, valida a unicidade das sequências e só então executa `migrate`.

## Docker

```bash
export RADIUSHUB_TAG=1.3.3
./scripts/update-docker.sh --build
```

Ou:

```bash
docker compose pull
docker compose up -d --remove-orphans
docker compose exec -T app php scripts/check-migration-integrity.php
docker compose exec -T app php artisan migrate --force
```

## Instalações em que a migration antiga já foi registrada

Não remova linhas manualmente da tabela `migrations`. A ausência do arquivo antigo no novo código não desfaz uma migration já concluída. A migration canônica é idempotente e reconcilia somente colunas e índices ausentes.

## Validação

```bash
php scripts/check-migration-integrity.php
php artisan migrate:status
php artisan radiushub:doctor
```
