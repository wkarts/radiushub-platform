# Playground RadiusHub

O playground é uma instalação descartável para validar interface, multiempresa, RBAC, usuários, vouchers, banco, filas, Scheduler, FreeRADIUS, login e comandos MikroTik simulados.

Nunca use o playground no mesmo banco ou domínio da produção.

## Docker — instalação completa

### Usando build local

```bash
chmod +x scripts/*.sh
./scripts/playground.sh up
```

### Usando imagens da release/GHCR

```bash
./scripts/playground.sh up --pull-images
```

O comando cria `.env.playground`, gera segredos, sobe os serviços e só conclui depois de validar:

- PostgreSQL;
- Redis;
- Laravel/PHP-FPM;
- Nginx;
- worker;
- Scheduler;
- FreeRADIUS;
- `/health/live` e `/health/ready`;
- login do Superadministrador;
- simulador MikroTik;
- autenticação RADIUS com `Access-Accept`;
- accounting com `Accounting-Response` e persistência no banco.

A aplicação fica disponível em:

```text
http://127.0.0.1:8080
```

As credenciais são geradas e exibidas ao final. Para consultá-las novamente:

```bash
./scripts/playground.sh credentials
```

### Operação

```bash
./scripts/playground.sh status
./scripts/playground.sh verify
./scripts/playground.sh logs
./scripts/playground.sh logs --follow
./scripts/playground.sh down
```

### Reinicialização total

```bash
./scripts/playground.sh reset
```

`reset` remove os volumes do projeto `radiushub-playground`. Não use esse projeto Compose para dados permanentes.

## Dados demonstrativos

O seeder cria de forma idempotente:

- tenant e empresa de playground;
- Superadministrador, Operador e Técnico;
- papéis e permissões;
- perfil e plano de 100 Mbps;
- MikroTik CHR simulado;
- cliente, acesso Hotspot/PPPoE e contrato;
- fatura pendente;
- lote com vouchers em vários estados;
- sessão de accounting e eventos de auditoria.

O equipamento simulado aparece na interface, registra testes/comandos e responde ao mesmo serviço usado pelos controllers. Nenhum pacote SSH é enviado à rede.

## CloudPanel — playground nativo

Crie um site e banco exclusivos para testes. O document root deve terminar em `/public`.

```bash
cd /home/USUARIO/htdocs/playground.exemplo.com
cp .env.cloudpanel.playground.example .env
nano .env
```

Ajuste pelo menos:

```env
APP_URL=https://playground.exemplo.com
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=radiushub_playground
DB_USERNAME=radiushub_playground
DB_PASSWORD=SENHA_REAL_DO_BANCO
```

Depois execute:

```bash
chmod +x scripts/*.sh
./scripts/install-cloudpanel-playground.sh --reuse-env
```

Instale os artefatos gerados:

```bash
sudo cp storage/app/deploy/supervisor-radiushub.conf /etc/supervisor/conf.d/radiushub-playground.conf
sudo supervisorctl reread
sudo supervisorctl update
cat storage/app/deploy/cron.txt
```

O instalador gera também:

- `storage/app/deploy/nginx-native.conf`;
- `storage/app/deploy/nginx-docker-reverse-proxy.conf`;
- arquivo Supervisor;
- linha de Cron.

### Verificação CloudPanel

```bash
./scripts/validate-deployment.sh --http --login
```

O modo nativo testa a aplicação e o simulador. Para testar RADIUS real no próprio servidor, instale e configure o FreeRADIUS nativo conforme `docs/DEPLOY_CLOUDPANEL.md`. O Docker Playground já executa esse teste automaticamente.

## Docker atrás do CloudPanel

O fluxo pode ser preparado em um comando:

```bash
./scripts/install-cloudpanel-docker.sh \
  --playground \
  --pull-images \
  --url https://playground-radius.exemplo.com
```

O script mantém `APP_BIND_ADDRESS=127.0.0.1`, configura a URL/cookie e gera `storage/app/deploy/nginx-docker-reverse-proxy.conf`. Cole o snippet em **Custom Nginx Configuration** no CloudPanel.

Em uma instalação HTTPS nova, o script valida localmente banco, Redis, Laravel, Nginx, worker, Scheduler, FreeRADIUS e accounting, mas adia o login HTTP público até o proxy ser aplicado. Finalize com:

```bash
ENV_FILE=.env.playground ./scripts/validate-deployment.sh \
  --http --login \
  --url https://playground-radius.exemplo.com
```

## Segurança do playground

- portas web e RADIUS são vinculadas a `127.0.0.1` por padrão;
- senhas são geradas no primeiro `up` e o arquivo recebe modo `600`;
- o simulador exige `PLAYGROUND_MODE=true`;
- o instalador CloudPanel Docker desativa `APP_DEBUG` quando a URL não é local;
- a aplicação recusa playground em `APP_ENV=production` sem `PLAYGROUND_ALLOW_PRODUCTION=true`;
- não configure chaves, tokens Asaas ou dados reais no playground;
- para acesso remoto, use HTTPS, VPN ou túnel seguro; não abra banco ou Redis.
