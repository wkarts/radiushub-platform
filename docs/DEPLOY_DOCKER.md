# Deploy Docker

## Requisitos

- Docker Engine atual;
- Docker Compose v2;
- OpenSSL;
- DNS e HTTPS no proxy reverso;
- portas UDP 1812 e 1813 alcançáveis pelos MikroTiks.

## PostgreSQL

```bash
cp .env.docker.postgres.example .env
nano .env
./scripts/install-docker.sh --postgres
```

## MySQL

```bash
cp .env.docker.mysql.example .env
nano .env
./scripts/install-docker.sh --mysql
```

O instalador gera `APP_KEY`, chaves RADIUS, senha do banco e senha administrativa quando ainda estiverem com valores provisórios.

## Serviços

- `app`: PHP-FPM/Laravel;
- `web`: Nginx interno;
- `worker`: filas `webhooks,default`;
- `scheduler`: Laravel Scheduler;
- `freeradius`: autenticação e accounting;
- `redis`: cache e filas;
- `postgres` ou `mysql`: banco selecionado por profile.

No Docker, o cache usa `failover` (`redis,database`) e o limitador de login usa o banco. Uma indisponibilidade temporária do Redis não derruba o formulário de login; apenas as filas Redis ficam aguardando o serviço retornar.

## Comandos

```bash
docker compose --profile postgres ps
docker compose --profile postgres logs -f app
docker compose --profile postgres logs -f freeradius
docker compose --profile postgres exec app php artisan radiushub:doctor
docker compose --profile postgres exec freeradius freeradius -XC
```

Troque `postgres` por `mysql` conforme o profile.

## CloudPanel como proxy

Crie um site Reverse Proxy apontando para `http://127.0.0.1:8080` e use o trecho `deploy/cloudpanel/nginx-docker-reverse-proxy.conf`.

No `.env`:

```env
APP_URL=https://radius.seudominio.com.br
SESSION_SECURE_COOKIE=true
TRUSTED_PROXIES=*
APP_BIND_ADDRESS=127.0.0.1
APP_PORT=8080
```

## Atualização e backup

```bash
./scripts/backup.sh --docker
./scripts/update-docker.sh
```
