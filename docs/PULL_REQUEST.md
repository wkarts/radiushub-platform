# Sugestão de Pull Request

**Branch:** `feat/docker-cloudpanel-mysql-postgres-v1.3.0`

**Título:** `feat: preparar RadiusHub 1.3.0 para Docker, CloudPanel e GHCR`

**Commit principal:**

```text
feat(platform): adicionar deploy Docker/CloudPanel e suporte MySQL/PostgreSQL
```

**Descrição:**

- corrige dependência indevida do hostname `redis` no CloudPanel nativo;
- adiciona cache resiliente e limitador de login em banco;
- implementa suporte real a MySQL e PostgreSQL;
- adiciona criptografia RADIUS nativa por banco;
- adiciona imagens Docker app/web/FreeRADIUS;
- adiciona Compose com profiles MySQL/PostgreSQL;
- adiciona instaladores, atualização, backup e diagnóstico;
- adiciona CI, publicação GHCR, releases e Dependabot;
- preserva integração Asaas SDK ARGWS 0.2.62.
