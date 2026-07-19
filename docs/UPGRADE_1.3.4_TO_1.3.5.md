# Upgrade RadiusHub 1.3.4 para 1.3.5

A versão 1.3.5 corrigiu o contexto do GitHub CLI no job final de publicação da release. A aplicação, o banco e as integrações não foram removidos ou reestruturados.

## CloudPanel

```bash
chmod +x scripts/upgrade-1.3.4-to-1.3.5.sh
./scripts/upgrade-1.3.4-to-1.3.5.sh
```

## Docker

```bash
export RADIUSHUB_TAG=1.3.5
./scripts/update-docker.sh --build
```

O upgrade preserva `.env`, `APP_KEY`, banco, chaves SSH, segredos RADIUS e credenciais Asaas.
