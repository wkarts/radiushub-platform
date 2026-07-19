# RadiusHub Platform 1.2.0

Plataforma web multi-tenant em PHP/Laravel para administração de MikroTik Hotspot e PPPoE, FreeRADIUS, accounting, CoA/Disconnect, contratos, faturamento e integração Asaas pelo SDK `argws/asaas-sdk-php` 0.2.62.

## Recursos principais

- Laravel full-stack com Blade, sem Tauri e sem backend Node.js;
- isolamento por tenant, usuários, perfis e permissões;
- clientes, planos, contratos e credenciais Hotspot/PPPoE;
- MikroTik RouterOS API, RADIUS, accounting, CoA e Disconnect;
- FreeRADIUS 3.2 com PostgreSQL 17 ou MySQL 8.4;
- criptografia de credenciais RADIUS legível somente pelo banco/FreeRADIUS;
- faturas, Pix, boleto, cartão, webhooks, reconciliação e estorno Asaas;
- cache e filas com Redis no Docker;
- operação nativa no CloudPanel sem dependência obrigatória de Redis;
- imagens Docker preparadas para GHCR;
- CI para PHP 8.3/8.4, MySQL e PostgreSQL.

## Instalação Docker

### PostgreSQL

```bash
cp .env.docker.postgres.example .env
./scripts/install-docker.sh --postgres
```

### MySQL

```bash
cp .env.docker.mysql.example .env
./scripts/install-docker.sh --mysql
```

Para usar imagens publicadas no GHCR em vez de compilar localmente:

```bash
./scripts/install-docker.sh --postgres --pull-images
```

A aplicação web fica, por padrão, em `127.0.0.1:8080`. Publique-a por HTTPS usando CloudPanel, Nginx ou outro proxy reverso. As portas UDP 1812/1813 devem ser liberadas somente para os MikroTiks ou para a VPN da rede.

## Instalação nativa no CloudPanel

Crie um site PHP 8.3 ou 8.4 e configure o Document Root para o diretório `public` do projeto.

```bash
cp .env.cloudpanel.example .env
./scripts/install-cloudpanel.sh
```

Instale o FreeRADIUS após validar a aplicação:

```bash
sudo ./scripts/install-freeradius-native.sh
```

Instale o worker gerado pelo instalador:

```bash
sudo cp storage/app/deploy/supervisor-radiushub.conf /etc/supervisor/conf.d/radiushub.conf
sudo supervisorctl reread
sudo supervisorctl update
```

Adicione ao cron do usuário do site o conteúdo de:

```text
storage/app/deploy/cron.txt
```

Documentação detalhada: [docs/DEPLOY_CLOUDPANEL.md](docs/DEPLOY_CLOUDPANEL.md).

## Diagnóstico

```bash
./scripts/doctor.sh
```

Ou diretamente:

```bash
php artisan radiushub:doctor --strict
php artisan radiushub:radius:render --force
```

No Docker:

```bash
docker compose --profile postgres ps
docker compose --profile postgres logs -f app worker freeradius
```

## Atualização

Docker:

```bash
./scripts/update-docker.sh
```

CloudPanel nativo:

```bash
./scripts/update-cloudpanel.sh
```

## Backup

```bash
./scripts/backup.sh --docker
./scripts/backup.sh --native
```

## GitHub e imagens

O workflow `.github/workflows/docker-publish.yml` publica:

```text
ghcr.io/<proprietário>/radiushub-app
ghcr.io/<proprietário>/radiushub-web
ghcr.io/<proprietário>/radiushub-freeradius
```

Para criar e publicar com GitHub CLI: `./scripts/publish-github.sh wkarts/radiushub-platform --public`. Ao usar outro proprietário, ajuste `RADIUSHUB_REGISTRY` no `.env` quando o proprietário não for `wkarts`. Consulte [docs/GITHUB.md](docs/GITHUB.md).

## Segurança

Nunca versione:

- `.env`;
- chaves Asaas;
- `APP_KEY`;
- `RADIUS_CREDENTIAL_KEY`;
- segredos dos MikroTiks;
- diretórios FreeRADIUS gerados em `storage/app/freeradius-generated`.

Troque imediatamente qualquer credencial exposta em logs, prints ou conversas. Restrinja RADIUS e RouterOS API por VPN ou firewall de origem.

## Autoria

**WWSoftware's Sistemas / Wallace Kleiton**  
GitHub: `@wkarts`
