# Deploy nativo no CloudPanel

## Site

Crie um site PHP com PHP 8.3 ou 8.4. O Document Root deve apontar para:

```text
/home/USUARIO/htdocs/DOMINIO/public
```

Não aponte o domínio para a raiz do projeto.

## Banco

A aplicação suporta MySQL 8+ e PostgreSQL 15+. Crie banco e usuário no CloudPanel antes de executar o instalador.

## Instalação

```bash
cd /home/USUARIO/htdocs/DOMINIO
cp .env.cloudpanel.example .env
./scripts/install-cloudpanel.sh
```

O instalador:

- verifica PHP e extensões;
- gera as chaves ausentes;
- instala dependências Composer;
- limpa cache de configuração antigo;
- executa migrations e seeders;
- cria o link de storage;
- gera configuração para Supervisor e Cron;
- executa `radiushub:doctor`.

## Redis

Redis é opcional no modo nativo. A configuração padrão usa:

```env
CACHE_STORE=database
CACHE_LIMITER=database
QUEUE_CONNECTION=database
REDIS_HOST=127.0.0.1
```

Para usar Redis local:

```env
CACHE_STORE=failover
CACHE_FAILOVER_STORES=redis,database
CACHE_LIMITER=database
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
```

Nunca use `REDIS_HOST=redis` fora do Docker; esse nome existe apenas na rede interna do Compose.

## Worker

```bash
sudo apt-get install -y supervisor
sudo cp storage/app/deploy/supervisor-radiushub.conf /etc/supervisor/conf.d/radiushub.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

## Scheduler

Adicione o conteúdo de `storage/app/deploy/cron.txt` ao crontab do usuário do site:

```bash
crontab -e
```

## FreeRADIUS nativo

```bash
sudo SITE_USER=USUARIO ./scripts/install-freeradius-native.sh
```

O script instala o driver compatível com `DB_CONNECTION`, gera a configuração a partir do `.env`, mantém backup da configuração anterior, valida com `freeradius -XC` e reinicia o serviço.

## Nginx

Use `deploy/cloudpanel/nginx-native-location.conf` como complemento do vhost criado pelo CloudPanel. Preserve o bloco PHP-FPM gerado pelo painel.

## Atualização

```bash
./scripts/update-cloudpanel.sh
```

## Diagnóstico

```bash
php artisan radiushub:doctor --strict
sudo freeradius -XC
sudo systemctl status freeradius
```
