# RadiusHub Platform 1.3.0

Plataforma web multi-tenant em PHP/Laravel para administrar empresas, clientes, planos, Hotspot, PPPoE, vouchers, FreeRADIUS, equipamentos MikroTik por **SSH Key**, financeiro e integração Asaas.

## Principais recursos

- Superadministração global, tenants e múltiplas empresas por tenant.
- RBAC por empresa com papéis, permissões e isolamento por `tenant_id` + `company_id`.
- Criação de empresa com administrador opcional, senha inicial obrigatoriamente alterável e convite por recuperação de senha.
- Comunicação administrativa com MikroTik exclusivamente por SSH Key.
- Fixação de fingerprint do host, chave privada criptografada, comandos em allowlist e auditoria completa.
- Usuários de rede pré-cadastrados para Hotspot e PPPoE.
- Vouchers individuais ou em lote, validade fixa ou iniciada no primeiro acesso, limites, impressão, CSV e PDF.
- FreeRADIUS 3.2 para autenticação, autorização e accounting; desconexões e limites administrativos são aplicados por SSH Key.
- Planos, perfis de velocidade, sessões, autenticações e consumo.
- Financeiro, faturas, bloqueio por inadimplência e Asaas SDK ARGWS.
- Interface Blade responsiva, menu colapsável, CRUDs modais, visualização mobile por cards e tema claro/escuro.
- Login por e-mail ou login, recuperação de senha, rate limiting, troca obrigatória de senha e TOTP/2FA.
- Docker Compose com PostgreSQL ou MySQL, CloudPanel nativo, filas, Scheduler, CI, GHCR e releases.

## Requisitos

### CloudPanel nativo

- PHP 8.3 ou 8.4;
- extensões PDO MySQL ou PostgreSQL, mbstring, OpenSSL, intl, curl, DOM, bcmath e fileinfo;
- Composer 2;
- MySQL 8.0+/MariaDB compatível ou PostgreSQL 15+;
- FreeRADIUS 3.2 para AAA; `radclient` é opcional e usado apenas quando o fallback CoA estiver explicitamente habilitado;
- Supervisor e Cron.

### Docker

- Docker Engine;
- Docker Compose v2;
- portas UDP 1812/1813 acessíveis apenas pelos NAS autorizados;
- saída TCP da aplicação até a porta SSH dos MikroTiks.

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

A aplicação é publicada, por padrão, em `127.0.0.1:8080` para uso atrás do reverse proxy do CloudPanel.

## Instalação nativa no CloudPanel

```bash
cp .env.cloudpanel.example .env
chmod +x scripts/*.sh
./scripts/install-cloudpanel.sh
sudo ./scripts/install-freeradius-native.sh
```

Configure o document root do site para `public/`, instale o arquivo de Supervisor gerado em `storage/app/deploy/` e adicione o Cron gerado no mesmo diretório.

## Atualização 1.2.x para 1.3.0

```bash
chmod +x scripts/upgrade-1.2-to-1.3.sh
./scripts/upgrade-1.2-to-1.3.sh
```

O script preserva `.env`, `APP_KEY`, credenciais criptografadas e banco atual, realiza backup, instala dependências, executa migrations e reinicia workers.

## Documentação

- [Arquitetura](docs/ARCHITECTURE.md)
- [Análise técnica 1.3](docs/ANALISE_TECNICA_1.3.md)
- [SSH Key no MikroTik](docs/MIKROTIK_SSH.md)
- [RADIUS, Hotspot, PPPoE e controle de sessões](docs/MIKROTIK.md)
- [Vouchers](docs/VOUCHERS.md)
- [Multi-tenancy e RBAC](docs/TENANCY_RBAC.md)
- [Padrão de CRUD e interface](docs/CRUD_UI.md)
- [Segurança e auditoria](docs/SECURITY_OPERATIONS.md)
- [Docker](docs/DEPLOY_DOCKER.md)
- [CloudPanel](docs/DEPLOY_CLOUDPANEL.md)
- [Asaas SDK ARGWS](docs/ASAAS_SDK_ARGWS.md)
- [GitHub, GHCR e releases](docs/GITHUB.md)
- [Upgrade 1.2 → 1.3](docs/UPGRADE_1.2_TO_1.3.md)

## Diagnóstico

```bash
php artisan radiushub:doctor
php artisan radiushub:doctor --strict
```

No Docker:

```bash
./scripts/doctor.sh
```

## Segurança inicial obrigatória

1. Mantenha `APP_KEY` estável e protegida; use `APP_PREVIOUS_KEYS` durante rotação controlada.
2. Substitua todos os valores `change-this-*`.
3. Mantenha `MIKROTIK_SSH_ALLOW_PASSWORD_FALLBACK=false`.
4. Fixe a fingerprint do host SSH de cada equipamento.
5. Não exponha Redis, banco, SSH ou FreeRADIUS para origens não autorizadas.
6. Troque a senha inicial do Superadministrador no primeiro acesso e ative 2FA.

## Autoria

WWSoftware's Sistemas / Wallace Kleiton — GitHub `@wkarts`.

## Webhooks Asaas por empresa

A URL do webhook não expõe tenant, slug ou UUID interno. Cada gateway Asaas recebe um token público aleatório de 96 caracteres e mantém também um token independente no cabeçalho `asaas-access-token`.

Após atualização de uma instalação 1.2.x:

```bash
php artisan migrate --force
php artisan asaas:webhooks:sync
```

Consulte `docs/ASAAS_WEBHOOKS_MULTIEMPRESA.md`.
