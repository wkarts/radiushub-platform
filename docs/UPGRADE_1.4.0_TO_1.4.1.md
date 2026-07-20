# Upgrade RadiusHub 1.4.0 para 1.4.1

A versão 1.4.1 corrige o carregamento do módulo SQL do FreeRADIUS e restaura a homologação integral em todo Pull Request. Não adiciona migrations nem remove recursos da versão 1.4.0.

## CloudPanel nativo

Preserve obrigatoriamente `.env`, `APP_KEY`, `storage/`, banco de dados, chaves SSH, segredos RADIUS e credenciais Asaas.

```bash
cd /home/USUARIO/htdocs/DOMINIO
chmod +x scripts/*.sh artisan docker/app/entrypoint.sh docker/freeradius/entrypoint.sh
./scripts/upgrade-1.4.0-to-1.4.1.sh
```

Quando o FreeRADIUS estiver instalado nativamente, reaplique a configuração para que o instalador descubra o diretório efetivamente usado pelo binário:

```bash
sudo SITE_USER=USUARIO ./scripts/install-freeradius-native.sh
sudo freeradius -XC
sudo systemctl restart freeradius
```

A validação agora falha explicitamente caso apareça `Ignoring "sql"` ou o módulo `rlm_sql` não seja carregado.

## Docker

```bash
export RADIUSHUB_TAG=1.4.1
./scripts/update-docker.sh --build
```

Para forçar a reconstrução da imagem corrigida do FreeRADIUS:

```bash
docker compose build --no-cache freeradius
docker compose up -d --force-recreate freeradius
```

Valide:

```bash
docker compose logs --tail=200 freeradius
docker compose exec -T app php artisan radiushub:health --ready
```

Nos logs do FreeRADIUS deve aparecer o diretório ativo e o carregamento de `rlm_sql`; não pode aparecer `Ignoring "sql"`.

## Banco de dados

Não existe migration nova na versão 1.4.1. O comando `php artisan migrate --force` permanece no upgrade por segurança e idempotência.
