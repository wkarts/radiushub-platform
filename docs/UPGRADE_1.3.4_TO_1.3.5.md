# Upgrade RadiusHub 1.3.4 para 1.3.5

A versão 1.3.5 corrige o erro final da automação de release:

```text
failed to run git: fatal: not a git repository
```

O job `publish` agora realiza checkout próprio, define explicitamente o repositório para o GitHub CLI e verifica a release depois da publicação.

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

## Recuperar a release v1.3.4

A execução que falhou já criou a tag `v1.3.4`, os pacotes e as imagens. Depois de incorporar a versão 1.3.5:

1. abra `Actions`;
2. selecione `Release automática`;
3. clique em `Run workflow`;
4. informe `ref` como `f3cccaf5b3910d366d30599b2037baf7be3d732c`;
5. mantenha `rebuild_existing=false`.

O workflow reutilizará a tag existente e publicará os artefatos da versão 1.3.4.

## Segurança

O upgrade preserva `.env`, `APP_KEY`, banco, credenciais SSH, RADIUS e Asaas.
