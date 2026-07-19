# Atualização do RadiusHub 1.0.0 para 1.1.0

A versão 1.1.0 adiciona a integração operacional com o Asaas SDK ARGWS 0.2.62.

## Atualização com Docker Compose

```bash
cp .env .env.backup
unzip radiushub-platform-v1.1.0.zip
cd radiushub-platform-v1.1.0
docker compose build --no-cache
docker compose up -d postgres redis
docker compose run --rm app php artisan migrate --force
docker compose up -d
```

## Atualização em instalação PHP tradicional

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan optimize
php artisan queue:restart
```

## Configuração por tenant

1. Acesse **Financeiro → Gateways**.
2. Cadastre o driver **Asaas SDK ARGWS**.
3. Informe a API Key do Sandbox ou Produção.
4. Salve, execute **Testar conexão** e depois **Sincronizar webhook**.
5. Confirme que o worker processa as filas `webhooks,default`.

## Alteração de API Key ou ambiente

Quando a API Key ou o ambiente é alterado, a aplicação:

- invalida os vínculos de clientes da conta anterior;
- limpa artefatos remotos apenas das cobranças pendentes ou vencidas;
- preserva cobranças pagas para auditoria;
- recria ou reutiliza clientes e cobranças pela chave `externalReference`;
- exige nova sincronização do webhook.

## Migração adicionada

```text
database/migrations/2026_07_18_000600_add_asaas_sdk_integration.php
```

Ela adiciona vínculos de clientes por gateway, artefatos Pix/boleto, estado remoto, reconciliação e controle de estornos.
