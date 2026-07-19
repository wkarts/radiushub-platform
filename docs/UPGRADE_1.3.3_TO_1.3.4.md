# Upgrade RadiusHub 1.3.3 para 1.3.4

A versão 1.3.4 corrige a automação de publicação. O merge na `main` agora cria automaticamente a tag e a GitHub Release depois que o workflow `CI` for aprovado.

Nenhuma migration de negócio foi adicionada nesta versão.

## CloudPanel

```bash
cd /home/argws-radius/htdocs/radius.home.argws.com.br
chmod +x scripts/*.sh
./scripts/upgrade-1.3.3-to-1.3.4.sh
```

O script preserva `.env`, `APP_KEY`, banco, credenciais SSH, RADIUS e Asaas.

## Docker

```bash
export RADIUSHUB_TAG=1.3.4
./scripts/update-docker.sh --build
```

## GitHub

Depois do merge do PR com `VERSION=1.3.4`, aguarde o CI da `main`. O workflow `Release automática` criará:

- tag `v1.3.4`;
- GitHub Release;
- ZIP e TAR.GZ;
- checksums SHA-256;
- imagens GHCR `1.3.4`, `1.3` e `latest`.

Para recuperação manual, execute o workflow pela interface Actions.
