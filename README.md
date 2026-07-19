# RadiusHub Platform 1.4.0

Plataforma web multi-tenant em PHP/Laravel para administrar empresas, clientes, planos, Hotspot, PPPoE, vouchers, FreeRADIUS, equipamentos MikroTik por **SSH Key**, financeiro e integração Asaas.

## Recursos principais

- Superadministrador global, tenants e múltiplas empresas por tenant.
- RBAC por empresa com papéis, permissões e isolamento por `tenant_id` e `company_id`.
- Criação de empresa com administrador opcional, senha inicial alterável e auditoria.
- MikroTik por SSH Key, fingerprint, chave criptografada, allowlist, inventário e histórico.
- Usuários Hotspot/PPPoE pré-cadastrados e sincronização controlada.
- Vouchers individuais/em lote, validade fixa/primeiro acesso, limites, impressão, CSV e PDF.
- FreeRADIUS para autenticação, autorização e accounting em MySQL ou PostgreSQL.
- Sessões, consumo, disconnect e rate-limit administrativo por SSH.
- Faturas, pagamentos, reembolso e Asaas SDK ARGWS com webhook secreto por gateway/empresa.
- Blade responsivo, CRUDs em modal, menu colapsável, cards mobile e dashboards por escopo.
- Recuperação de senha, rate limiting, troca obrigatória e TOTP/2FA.
- Docker, CloudPanel nativo, workers, Scheduler, CI, GHCR e releases automáticas.
- Playground funcional com simulador MikroTik, login, RADIUS e accounting.

## Requisitos

### Docker

- Docker Engine;
- Docker Compose v2;
- acesso TCP de saída até a porta SSH dos MikroTiks;
- UDP 1812/1813 liberado somente para NAS autorizados quando usado em rede real.

### CloudPanel nativo

- PHP 8.3 ou 8.4;
- extensões PDO MySQL/PostgreSQL, mbstring, OpenSSL, intl, curl, DOM, bcmath e fileinfo;
- Composer 2;
- MySQL 8+ ou PostgreSQL 15+;
- Supervisor e Cron;
- FreeRADIUS 3.2 para AAA real.

## Playground Docker — início rápido

```bash
chmod +x scripts/*.sh
./scripts/playground.sh up
```

Usando imagens da release:

```bash
./scripts/playground.sh up --pull-images
```

O comando gera segredos e valida o stack completo antes de concluir. URL padrão:

```text
http://127.0.0.1:8080
```

Comandos úteis:

```bash
./scripts/playground.sh credentials
./scripts/playground.sh verify
./scripts/playground.sh status
./scripts/playground.sh logs --follow
./scripts/playground.sh reset
./scripts/playground.sh down
```

Detalhes: [docs/PLAYGROUND.md](docs/PLAYGROUND.md).

## Docker de produção/homologação

### PostgreSQL

```bash
cp .env.docker.postgres.example .env
nano .env
./scripts/install-docker.sh --postgres
```

### MySQL

```bash
cp .env.docker.mysql.example .env
nano .env
./scripts/install-docker.sh --mysql
```

Para usar as imagens do GHCR:

```bash
./scripts/install-docker.sh --postgres --pull-images
```

A aplicação é vinculada por padrão a `127.0.0.1:8080`, adequada para reverse proxy do CloudPanel.

### Docker atrás do CloudPanel

Produção/homologação com PostgreSQL e imagens do GHCR:

```bash
./scripts/install-cloudpanel-docker.sh \
  --postgres \
  --pull-images \
  --url https://radius.exemplo.com
```

Playground Docker isolado atrás do CloudPanel:

```bash
./scripts/install-cloudpanel-docker.sh \
  --playground \
  --pull-images \
  --url https://playground-radius.exemplo.com
```

O instalador mantém a porta em `127.0.0.1`, gera `storage/app/deploy/nginx-docker-reverse-proxy.conf` e, no primeiro deploy HTTPS, adia somente o teste de login público até o snippet ser aplicado no CloudPanel. Depois valide:

```bash
ENV_FILE=.env.playground ./scripts/validate-deployment.sh \
  --http --login \
  --url https://playground-radius.exemplo.com
```

## CloudPanel nativo

```bash
cp .env.cloudpanel.example .env
nano .env
chmod +x scripts/*.sh
./scripts/install-cloudpanel.sh
sudo SITE_USER=USUARIO ./scripts/install-freeradius-native.sh
```

Configure o document root para `public/` e instale os arquivos de Supervisor/Cron gerados em `storage/app/deploy/`.

### CloudPanel Playground

Use domínio e banco separados:

```bash
cp .env.cloudpanel.playground.example .env
nano .env
./scripts/install-cloudpanel-playground.sh --reuse-env
./scripts/validate-deployment.sh --http --login
```

## Saúde e diagnóstico

```bash
php scripts/check-planning-compliance.php
php artisan radiushub:health --ready
php artisan radiushub:doctor
./scripts/validate-deployment.sh --http
```

Endpoints:

```text
/health/live
/health/ready
```

## Atualização 1.3.5 → 1.4.0

```bash
chmod +x scripts/upgrade-1.3.5-to-1.4.0.sh
./scripts/upgrade-1.3.5-to-1.4.0.sh
```

O upgrade não habilita playground na instalação existente e preserva `.env`, `APP_KEY`, banco, chaves SSH, segredos RADIUS e credenciais Asaas.

## Documentação

- [Conformidade do planejamento](docs/PLANNING_COMPLIANCE.md)
- [Playground](docs/PLAYGROUND.md)
- [Arquitetura](docs/ARCHITECTURE.md)
- [MikroTik SSH Key](docs/MIKROTIK_SSH.md)
- [RADIUS, Hotspot e PPPoE](docs/MIKROTIK.md)
- [Vouchers](docs/VOUCHERS.md)
- [Multi-tenancy e RBAC](docs/TENANCY_RBAC.md)
- [Padrão de CRUD e interface](docs/CRUD_UI.md)
- [Segurança e auditoria](docs/SECURITY_OPERATIONS.md)
- [Docker](docs/DEPLOY_DOCKER.md)
- [CloudPanel](docs/DEPLOY_CLOUDPANEL.md)
- [Asaas multiempresa](docs/ASAAS_WEBHOOKS_MULTIEMPRESA.md)
- [GitHub, GHCR e releases](docs/GITHUB.md)
- [Upgrade 1.3.5 → 1.4.0](docs/UPGRADE_1.3.5_TO_1.4.0.md)

## Segurança inicial obrigatória

1. Preserve `APP_KEY`; use rotação controlada e `APP_PREVIOUS_KEYS` quando necessário.
2. Substitua todos os valores `change-this-*`.
3. Mantenha `MIKROTIK_SSH_ALLOW_PASSWORD_FALLBACK=false`.
4. Fixe a fingerprint SSH de cada MikroTik real.
5. Não exponha banco, Redis ou portas RADIUS a origens não autorizadas.
6. Ative 2FA nas contas administrativas.
7. Mantenha `PLAYGROUND_MODE=false` em produção.

## Autoria

WWSoftware's Sistemas / Wallace Kleiton — GitHub `@wkarts`.
