# Upgrade RadiusHub 1.3.1 para 1.3.2

A versão 1.3.2 corrige definitivamente as falhas observadas no workflow `80361125869`:

- restaura os traits de autorização e validação do controller base;
- torna as migrations multiempresa e webhook retomáveis após DDL parcial no MySQL;
- cria índices substitutos antes de remover índices usados implicitamente por foreign keys;
- prepara `.env.testing` no CI e exibe warnings completos do PHPUnit;
- atualiza GitHub Actions para runtimes atuais.

## CloudPanel

Preserve `.env`, `APP_KEY`, `storage` e o banco de dados. Depois de substituir os arquivos:

```bash
cd /home/argws-radius/htdocs/radius.home.argws.com.br
chmod +x scripts/*.sh
./scripts/upgrade-1.3.1-to-1.3.2.sh
```

O script executa backup, instala dependências, limpa caches, executa migrations, seeders, doctor e reinicia workers.

## Docker

```bash
export RADIUSHUB_TAG=1.3.2
./scripts/update-docker.sh --build
```

## Banco MySQL após migration interrompida

A migration 1.3.2 detecta a estrutura criada parcialmente pela versão anterior e conclui somente a reconciliação dos índices. Caso a estrutura esteja incompleta em um ponto anterior, a migration interrompe com mensagem explícita para evitar mascarar inconsistências; nesse cenário, restaure o backup anterior ao upgrade.
