# Upgrade RadiusHub 1.4.1 para 1.4.2

A versão 1.4.2 corrige a sintaxe do bloco de conexão `pool` dos módulos SQL MySQL e PostgreSQL do FreeRADIUS. Não adiciona migrations e não altera contratos públicos da plataforma.

## Causa corrigida

Na versão 1.4.1, o módulo SQL já era escrito no diretório ativo, porém o bloco estava em uma única linha:

```text
pool { start = 5 min = 3 max = 20 ... }
```

O parser do FreeRADIUS 3.2.10 interpretava a sequência como uma única diretiva e encerrava com `Expected comma after '5'`.

Na 1.4.2, cada diretiva ocupa sua própria linha. A imagem também executa `freeradius -XC` para os templates PostgreSQL e MySQL durante o próprio build.

## CloudPanel nativo

Preserve `.env`, `APP_KEY`, `storage/`, banco, chaves SSH, segredos RADIUS e credenciais Asaas.

```bash
cd /home/USUARIO/htdocs/DOMINIO
chmod +x scripts/*.sh artisan docker/app/entrypoint.sh docker/freeradius/entrypoint.sh docker/freeradius/validate-templates.sh
./scripts/upgrade-1.4.1-to-1.4.2.sh
```

Quando o FreeRADIUS for nativo:

```bash
sudo SITE_USER=USUARIO ./scripts/install-freeradius-native.sh
sudo freeradius -XC
sudo systemctl restart freeradius
sudo systemctl status freeradius
```

## Docker

```bash
export RADIUSHUB_TAG=1.4.2
./scripts/update-docker.sh --build
```

Para evitar reutilizar a camada inválida da versão anterior:

```bash
docker compose build --no-cache freeradius
docker compose up -d --force-recreate freeradius
docker compose logs --tail=200 freeradius
```

O build da imagem agora valida os dois dialetos com o parser do FreeRADIUS antes de concluir.

## Banco

Não existe migration nova. `php artisan migrate --force` permanece no script por segurança e idempotência.
