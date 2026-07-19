# Implantação Docker

## Serviços

O Compose inclui:

- `app`: Laravel/PHP-FPM;
- `web`: Nginx;
- `worker`: filas `network`, `webhooks` e `default`;
- `scheduler`: `schedule:work`;
- `freeradius`: AAA e accounting;
- `redis`;
- `postgres` ou `mysql` por profile.

Worker, Scheduler, Nginx e FreeRADIUS aguardam a aplicação ficar saudável. O container `app` aguarda o banco, executa migrations/seed quando habilitado e só então passa no readiness.

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

## Imagens publicadas

```env
RADIUSHUB_REGISTRY=ghcr.io/wkarts
RADIUSHUB_TAG=1.4.0
RADIUSHUB_ENV_FILE=.env
```

```bash
./scripts/install-docker.sh --postgres --pull-images
```

Sem `--pull-images`, as imagens são compiladas localmente.


## HTTPS e cookies

Os exemplos de produção partem de `APP_URL=https://radius.example.com` e `SESSION_SECURE_COOKIE=true`. Substitua o domínio antes da instalação. Para um teste HTTP local descartável, use o playground, que configura o cookie seguro como `false` e vincula as portas a `127.0.0.1`.

## Reverse proxy CloudPanel

A publicação padrão é local:

```env
APP_BIND_ADDRESS=127.0.0.1
APP_PORT=8080
```

Use `deploy/cloudpanel/nginx-docker-reverse-proxy.conf` e configure SSL no CloudPanel.

## Portas

- TCP 8080: web local;
- UDP 1812: autenticação RADIUS;
- UDP 1813: accounting;
- TCP SSH: saída da aplicação até os MikroTiks;
- UDP 3799: opcional, somente quando o fallback CoA estiver habilitado.

Não publique PostgreSQL, MySQL ou Redis. Restrinja 1812/1813 aos IPs dos MikroTiks ou à VPN.

## Saúde e diagnóstico

```bash
docker compose --profile postgres ps
curl -fsS http://127.0.0.1:8080/health/live
curl -fsS http://127.0.0.1:8080/health/ready
docker compose --profile postgres exec -T app php artisan radiushub:doctor
```

## Playground funcional

```bash
./scripts/playground.sh up
# ou
./scripts/playground.sh up --pull-images
```

Consulte `docs/PLAYGROUND.md`. O playground executa smoke de login, RADIUS e accounting.

## Operação

```bash
docker compose --profile postgres logs -f app worker scheduler freeradius
./scripts/doctor.sh
./scripts/backup.sh --docker
./scripts/update-docker.sh
```

No MySQL, substitua o profile `postgres` por `mysql`.
