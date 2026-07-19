# Implantação no CloudPanel

## Opção A — PHP nativo

Crie um PHP Site com PHP 8.3/8.4 e document root:

```text
/home/USUARIO/htdocs/DOMINIO/public
```

Nunca aponte o vhost para a raiz do projeto.

### Banco e cache

Configuração conservadora sem Redis:

```env
DEPLOYMENT_MODE=native
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
SESSION_DRIVER=database
CACHE_STORE=database
CACHE_LIMITER=database
QUEUE_CONNECTION=database
REDIS_HOST=127.0.0.1
PLAYGROUND_MODE=false
```

Redis pode ser ativado depois da validação do serviço e da extensão PHP. O hostname `redis` é exclusivo do Compose.

### Instalação

```bash
cd /home/USUARIO/htdocs/DOMINIO
cp .env.cloudpanel.example .env
nano .env
chmod +x scripts/*.sh
./scripts/install-cloudpanel.sh
```

O instalador:

- valida PHP e a extensão PDO do banco selecionado;
- valida a extensão Redis quando cache ou filas usam Redis;
- preserva e protege `.env`;
- instala dependências;
- executa migrations e seed;
- gera caches;
- valida readiness;
- gera Nginx, Supervisor e Cron.

### Nginx

O snippet nativo é gerado em:

```text
storage/app/deploy/nginx-native.conf
```

O arquivo separado para Docker/reverse proxy é:

```text
storage/app/deploy/nginx-docker-reverse-proxy.conf
```

Não misture os dois modelos.

### Supervisor

```bash
sudo cp storage/app/deploy/supervisor-radiushub.conf /etc/supervisor/conf.d/radiushub.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

### Cron

Instale a linha de `storage/app/deploy/cron.txt` no crontab do usuário do site.

### FreeRADIUS nativo

```bash
sudo SITE_USER=USUARIO ./scripts/install-freeradius-native.sh
sudo freeradius -XC
sudo systemctl restart freeradius
```

Restrinja UDP 1812/1813 aos NAS autorizados.

### Verificação

```bash
./scripts/validate-deployment.sh --http
```

## Opção B — Docker + reverse proxy CloudPanel

O instalador integrado mantém a porta web vinculada a `127.0.0.1`, sobe os serviços e gera o snippet de proxy:

```bash
./scripts/install-cloudpanel-docker.sh \
  --postgres \
  --pull-images \
  --url https://radius.exemplo.com
```

Para usar MySQL, substitua `--postgres` por `--mysql`. O proxy local será:

```text
http://127.0.0.1:8080
```

Cole `storage/app/deploy/nginx-docker-reverse-proxy.conf` em **Custom Nginx Configuration** no CloudPanel.

No primeiro deploy HTTPS, o instalador valida o stack local e adia apenas o login pelo domínio público até o proxy ser aplicado. Depois execute:

```bash
ENV_FILE=.env.playground ./scripts/validate-deployment.sh \
  --http --login \
  --url https://playground-radius.exemplo.com
```

Para um playground Docker atrás do CloudPanel:

```bash
./scripts/install-cloudpanel-docker.sh \
  --playground \
  --pull-images \
  --url https://playground-radius.exemplo.com
```

## Playground

Para um ambiente de testes isolado, consulte `docs/PLAYGROUND.md`.

```bash
cp .env.cloudpanel.playground.example .env
nano .env
./scripts/install-cloudpanel-playground.sh --reuse-env
./scripts/validate-deployment.sh --http --login
```

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

Para 1.3.5 → 1.4.0, use `scripts/upgrade-1.3.5-to-1.4.0.sh`.
