# Integração Asaas SDK ARGWS

A integração financeira do RadiusHub utiliza o pacote local `argws/asaas-sdk-php` versão `0.2.62`, originado do projeto `wkarts/asaas-sdk-argws`.

## Arquitetura multi-tenant

Cada tenant cadastra uma configuração Asaas em **Financeiro → Gateways**. A API Key e o token do webhook são criptografados pelo cast `encrypted` do Laravel.

A instância da SDK é criada por `AsaasSdkFactory` para a combinação:

- gateway;
- ambiente `sandbox` ou `production`;
- hash da API Key atual.

Não existe singleton global compartilhado entre empresas.

## Recursos implementados

- teste autenticado da conta Asaas;
- cadastro e atualização de clientes;
- prevenção de duplicidade por CPF/CNPJ, `externalReference` e vínculo local;
- criação de cobrança;
- atualização da cobrança existente sem gerar duplicata;
- consulta e reconciliação de cobrança;
- Pix Copia e Cola e QR Code;
- linha digitável e URL do boleto;
- cancelamento;
- estorno total ou parcial;
- cadastro/atualização automática do webhook;
- validação do cabeçalho `asaas-access-token`;
- idempotência por ID do evento;
- processamento assíncrono em fila dedicada `webhooks`;
- baixa automática, reativação do contrato e Disconnect/CoA;
- fallback de reconciliação a cada 30 minutos.

## Configuração

1. Defina corretamente `APP_URL` com HTTPS público.
2. Acesse **Financeiro → Gateways**.
3. Cadastre o driver **Asaas SDK ARGWS**.
4. Informe API Key, ambiente, e-mail técnico e token do webhook.
5. Clique em **Testar**.
6. Clique em **Sincronizar webhook**.

A URL cadastrada será:

```text
https://SEU-DOMINIO/webhooks/asaas/SLUG-DO-TENANT
```

## Filas e Scheduler

O container `worker` deve permanecer ativo para processar webhooks. O container `scheduler` executa a reconciliação de segurança.

```bash
docker compose logs -f worker scheduler
docker compose exec app php artisan billing:reconcile-asaas --limit=100
```

## Idempotência

Clientes usam o vínculo único `billing_customer_links` e o `externalReference`:

```text
RADIUSHUB:subscriber:{uuid}
```

Cobranças usam:

```text
RADIUSHUB:invoice:{uuid}
```

Ao editar uma fatura que já possui `external_id`, a integração chama `updateExistingPayment`; não cria outra cobrança.

## Segurança

- Nunca grave API Keys em `.env` global para uso multi-tenant.
- Não registre credenciais em logs.
- Use HTTPS no webhook.
- O token do webhook deve ser longo e exclusivo por tenant.
- Após trocar a API Key, teste novamente a conexão.
- Após trocar URL ou token, sincronize novamente o webhook.

## Concorrência, idempotência e troca de conta

A emissão de clientes e cobranças utiliza locks distribuídos do Laravel para impedir duplicação por requisições concorrentes. Cada recurso também recebe uma `externalReference` determinística:

```text
RADIUSHUB:subscriber:{uuid}
RADIUSHUB:invoice:{uuid}
```

Antes de criar uma cobrança, a aplicação procura a mesma referência no Asaas. Cobranças existentes são atualizadas pelo ID remoto, em vez de gerar um novo título.

Cada vínculo remoto armazena uma impressão criptográfica da combinação gateway, ambiente e API Key. Ao trocar a conta ou alternar Sandbox/Produção, vínculos pendentes são invalidados de forma controlada e reconstruídos na conta atual, sem reutilizar IDs de outra conta.

## Requisitos do cliente

A API de clientes do Asaas exige nome e CPF/CNPJ. A integração valida que o documento normalizado possua 11 ou 14 dígitos antes de enviar a requisição e retorna uma mensagem clara quando o cadastro está incompleto.
