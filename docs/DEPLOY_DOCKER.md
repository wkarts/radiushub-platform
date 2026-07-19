# Implantação Docker

## Perfis disponíveis

- `postgres`: PostgreSQL 17;
- `mysql`: MySQL 8.4.

Serviços: `app`, `web`, `worker`, `scheduler`, `freeradius`, `redis` e banco escolhido.

## PostgreSQL

```bash
cp .env.docker.postgres.example .env
./scripts/install-docker.sh --postgres
```

## MySQL

```bash
cp .env.docker.mysql.example .env
./scripts/install-docker.sh --mysql
```

## Reverse proxy CloudPanel

A publicação padrão é local:

```env
APP_BIND_ADDRESS=127.0.0.1
APP_PORT=8080
```

Use `deploy/cloudpanel/nginx-docker-reverse-proxy.conf` no site Reverse Proxy e ative SSL no CloudPanel.

## Portas

- TCP 8080: web local;
- UDP 1812: RADIUS auth;
- UDP 1813: accounting;
- TCP SSH: saída da aplicação até os MikroTiks para sincronização, desconexão e limites;
- UDP 3799: opcional, somente quando `MIKROTIK_ALLOW_COA_FALLBACK=true`;
- TCP 22 ou porta customizada: SSH no MikroTik, saída da aplicação.

Não exponha PostgreSQL/MySQL/Redis. Restrinja 1812/1813 no firewall aos IPs dos MikroTiks ou à VPN.

## Imagens GHCR

```env
RADIUSHUB_REGISTRY=ghcr.io/wkarts
RADIUSHUB_TAG=1.3.3
```

```bash
./scripts/install-docker.sh --postgres --pull-images
```

Se as imagens não estiverem publicadas, omita `--pull-images` para build local.

## Operação

```bash
docker compose --profile postgres ps
docker compose --profile postgres logs -f app worker scheduler freeradius
./scripts/doctor.sh
./scripts/backup.sh --docker
./scripts/update-docker.sh
```

No MySQL, substitua `postgres` por `mysql`.
