# Implantação nativa no CloudPanel

## Site

Crie um PHP Site com PHP 8.3/8.4 e document root apontando para:

```text
/home/USUARIO/htdocs/DOMINIO/public
```

Nunca aponte o vhost para a raiz do projeto.

## Banco e cache

CloudPanel nativo recomendado:

```env
DEPLOYMENT_MODE=native
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
SESSION_DRIVER=database
CACHE_STORE=database
CACHE_LIMITER=database
QUEUE_CONNECTION=database
REDIS_HOST=127.0.0.1
```

Redis pode ser ativado após validar serviço/extensão, mas não use `REDIS_HOST=redis` fora do Docker.

## Instalação

```bash
cd /home/USUARIO/htdocs/DOMINIO
cp .env.cloudpanel.example .env
chmod +x scripts/*.sh
./scripts/install-cloudpanel.sh
```

O script cria segredos ausentes, instala Composer, migra, faz seed, gera caches e prepara Supervisor/Cron.

## FreeRADIUS

```bash
sudo SITE_USER=USUARIO ./scripts/install-freeradius-native.sh
sudo freeradius -XC
sudo systemctl restart freeradius
```

## Supervisor

```bash
sudo cp storage/app/deploy/supervisor-radiushub.conf /etc/supervisor/conf.d/radiushub.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

O worker consome `network,webhooks,default`.

## Cron

Instale a linha gerada em `storage/app/deploy/cron.txt` no crontab do usuário do site.

## Permissões

```bash
chown -R USUARIO:USUARIO storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod 775 {} \;
find storage bootstrap/cache -type f -exec chmod 664 {} \;
chmod 600 .env
```

## Atualização

```bash
./scripts/update-cloudpanel.sh
```

Para migrar 1.2.x para 1.3.0, use `scripts/upgrade-1.2-to-1.3.sh`.
