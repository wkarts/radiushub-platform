# Validação da correção RadiusHub Platform 1.4.2

## Workflow analisado

`80453029123`

## Causa confirmada

O FreeRADIUS encontrou o módulo SQL no diretório correto, mas recusou o template porque todas as diretivas do `pool` estavam na mesma linha. O erro ocorreu antes do smoke RADIUS:

```text
/etc/freeradius/mods-enabled/sql[14]: Syntax error:
Expected comma after '5'
```

## Correção

- `pool` multilinha em MySQL e PostgreSQL;
- `max_retries` e `cleanup_interval` explícitos;
- verificador estático `scripts/check-freeradius-templates.php`;
- comando Composer `radius:check`;
- parser real `freeradius -XC` para ambos os dialetos durante o build;
- diagnóstico sanitizado em falhas de runtime;
- validação nativa antes de alterar `/etc/freeradius`;
- versão 1.4.2;
- PR completa preservada.

## Validações locais disponíveis

```bash
php scripts/check-freeradius-templates.php
php scripts/check-version-integrity.php
php scripts/check-migration-integrity.php
php scripts/check-planning-compliance.php
bash -n docker/freeradius/entrypoint.sh
bash -n docker/freeradius/validate-templates.sh
```

O parser real da imagem é executado pelo próprio Dockerfile. Portanto, a imagem FreeRADIUS não pode ser construída quando qualquer template MySQL ou PostgreSQL for rejeitado pelo FreeRADIUS 3.2.x.


## Revisão 5 — alteração posteriormente revertida pela 1.4.1

A revisão 5 tentou reduzir o tempo do PR condicionando smokes completos. Essa decisão foi considerada incorreta e foi revertida integralmente na 1.4.1. Historicamente, ela havia feito:

- `push` do CI limitado à `main`;
- PR executa somente qualidade, MySQL, PostgreSQL e contratos Docker/Compose;
- smokes completos condicionados à `main`, execução manual ou rótulo `full-validation`;
- removida a construção redundante das imagens `app`, `web` e `freeradius`;
- `docker-publish.yml` não reage mais a push/tag automaticamente.

A falha `Access-Accept` foi tratada com:

- desativação da recarga NAS no playground;
- fingerprint estável dos campos RADIUS no ambiente produtivo;
- preflight da credencial criptografada e do NAS;
- `radclient` com timeout e repetição controlados;
- diagnóstico ampliado em caso de falha.
