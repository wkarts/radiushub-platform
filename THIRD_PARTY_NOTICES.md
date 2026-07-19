# Avisos de terceiros

## Asaas SDK ARGWS 0.2.62

Este pacote inclui uma cópia local do projeto `argws/asaas-sdk-php`, mantido no diretório:

```text
packages/asaas-sdk-argws
```

- Projeto de origem: `wkarts/asaas-sdk-argws`
- Versão integrada: `0.2.62`
- Licença: MIT
- Namespace: `Asaas\Sdk\`
- Dependências principais do SDK: Guzzle 7.8+ e PSR Log 3
- SHA-256 do arquivo-fonte recebido para integração: `5fce33529fd85c4fdfd431df11c0b31f0d5900bc3968a472b9aa00759287357f`

A licença e os avisos originais estão preservados dentro do diretório do SDK. A única alteração no `composer.json` da cópia local foi a inclusão explícita do campo `version: 0.2.62`, necessária para resolução determinística pelo repositório Composer do tipo `path`.

O SDK é não oficial e não implica vínculo, endosso ou garantia da Asaas. Antes do uso em produção, valide a integração no ambiente Sandbox e mantenha o processamento idempotente de webhooks habilitado.
