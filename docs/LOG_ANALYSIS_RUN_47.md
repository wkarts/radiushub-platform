# Análise do CI nº 47 — Pull Request 13
> **Nota da versão 1.4.1:** a condição por label descrita neste documento foi revertida. Playground e CloudPanel voltaram a ser obrigatórios em todo Pull Request.


## Resultado observado

Os jobs de PHP 8.3, PHP 8.4, MySQL 8.4, PostgreSQL 17, CloudPanel nativo e os builds individuais das imagens `app`, `web` e `freeradius` foram aprovados.

A única falha funcional ocorreu em `Docker Playground / smoke completo`.

## Causa da demora

O smoke RADIUS iniciou às 00:33:31 e somente encerrou às 00:46:36. O `radclient` utilizava o timeout e a quantidade padrão de retransmissões dentro de um laço externo de 45 tentativas. Uma falha de resposta podia, portanto, manter o job bloqueado por mais de 13 minutos.

Além disso, o workflow executava trabalho redundante:

1. o mesmo commit do PR disparava `push` e `pull_request`;
2. três imagens eram construídas no job matricial `docker-build`;
3. as mesmas imagens eram reconstruídas pelo Docker Compose do playground;
4. após merge, `docker-publish.yml` publicava imagens que também seriam publicadas por `release.yml`.

## Causa do `No reply from server`

O monitor do container FreeRADIUS observava `MAX(updated_at)` em `mikrotik_devices`. Testes do simulador e tarefas de monitoramento atualizam timestamps operacionais sem alterar IP ou segredo RADIUS. Cada alteração disparava `SIGHUP` no FreeRADIUS durante o smoke.

O log mostrou recargas repetidas imediatamente antes da conclusão sem `Access-Accept`.

## Correções da revisão 5

- PR padrão não dispara mais workflow de `push` da branch de feature;
- removido o build matricial redundante das três imagens;
- PR executa validação de Compose sem construir imagens;
- smokes completos ficam restritos à `main`, execução manual ou rótulo `full-validation`;
- publicação Docker automática fica centralizada em `release.yml`;
- playground desabilita recarga automática de clientes NAS;
- produção usa fingerprint somente dos campos que afetam o cliente RADIUS;
- `radclient` usa timeout de 1 segundo e uma retransmissão controlada;
- o smoke executa preflight da credencial e do NAS antes de enviar UDP;
- falhas deixam de bloquear o runner por mais de 13 minutos.
