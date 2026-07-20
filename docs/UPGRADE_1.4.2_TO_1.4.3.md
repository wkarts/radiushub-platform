# Upgrade RadiusHub 1.4.2 para 1.4.3

A versão 1.4.3 corrige a sintaxe do virtual server do FreeRADIUS no bloco `authenticate`. Não adiciona migrations, não altera tabelas e não modifica contratos públicos da plataforma.

## Causa corrigida

O parser real da imagem FreeRADIUS 3.2.10 rejeitou as declarações compactadas:

```text
Auth-Type PAP { pap }
Auth-Type CHAP { chap }
Auth-Type MS-CHAP { mschap }
```

O erro registrado foi:

```text
sites-enabled/default[29]: Parse error after "pap": unexpected token "}"
```

Na 1.4.3, cada `Auth-Type` utiliza bloco multilinha compatível com o parser.

## CloudPanel nativo

Preserve `.env`, `APP_KEY`, banco, `storage`, chaves SSH, segredos RADIUS e credenciais Asaas. Depois de substituir os arquivos:

```bash
cd /home/USUARIO/htdocs/DOMINIO
chmod +x artisan scripts/*.sh docker/freeradius/*.sh
./scripts/upgrade-1.4.2-to-1.4.3.sh
```

Para reaplicar o FreeRADIUS nativo:

```bash
sudo SITE_USER=USUARIO ./scripts/install-freeradius-native.sh
sudo freeradius -XC
sudo systemctl restart freeradius
```

## Docker

```bash
export RADIUSHUB_TAG=1.4.3
docker compose build --no-cache freeradius
docker compose up -d --force-recreate freeradius
```

Ou:

```bash
./scripts/update-docker.sh --build
```

## Validação

```bash
php scripts/check-freeradius-templates.php
php scripts/check-version-integrity.php
php scripts/check-migration-integrity.php
php scripts/check-planning-compliance.php
```

O Dockerfile continua executando `freeradius -XC` para PostgreSQL e MySQL durante o build. Nenhuma validação da Pull Request foi removida ou tornada opcional.
