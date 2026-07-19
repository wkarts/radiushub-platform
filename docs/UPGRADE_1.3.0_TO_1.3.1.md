# Upgrade RadiusHub 1.3.0 para 1.3.1

A versão 1.3.1 corrige a migration MySQL, os diretórios de cache Blade, a sanitização de chaves privadas e a seleção de tenant nos testes e na aplicação.

## Atualização nativa/CloudPanel

Preserve `.env`, `APP_KEY`, `storage` e o banco atual. Substitua os arquivos da aplicação e execute:

```bash
chmod +x scripts/*.sh
./scripts/upgrade-1.3.0-to-1.3.1.sh
```

## Atualização Docker

```bash
export RADIUSHUB_TAG=1.3.1
./scripts/update-docker.sh --build
```

## Instalação MySQL que falhou no meio da migration 000700

A migration 000700 utiliza DDL e o MySQL pode ter persistido alterações anteriores ao erro. Não execute `migrate:fresh` em produção.

Restaure o backup criado antes do upgrade e reaplique a versão 1.3.1. Em bancos de homologação descartáveis, recrie o banco e execute as migrations desde o início.

## Validação

```bash
php artisan migrate:status
php artisan radiushub:doctor --strict
php artisan test
```
