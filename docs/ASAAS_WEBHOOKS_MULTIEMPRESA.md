# Webhooks Asaas multiempresa

Cada configuração Asaas possui um endpoint público secreto, exclusivo e independente do slug, UUID ou identificador interno do tenant/empresa:

```text
https://painel.exemplo.com/webhooks/asaas/<TOKEN-ALEATORIO-DE-96-CARACTERES>
```

O token da URL possui 384 bits de entropia, é armazenado criptografado e também indexado apenas por SHA-256 para resolução segura. O endpoint identifica diretamente o `payment_gateway_config_id`, o `company_id` e o `tenant_id` corretos antes de processar o evento.

O cabeçalho `asaas-access-token` continua obrigatório e utiliza outro segredo, também criptografado. Portanto, o recebimento exige simultaneamente:

1. URL secreta válida;
2. token de autenticação do cabeçalho válido;
3. gateway ativo;
4. payload JSON válido.

## Sincronizar todos os gateways

Após atualizar da versão 1.2.x:

```bash
php artisan migrate --force
php artisan asaas:webhooks:sync
```

Para um gateway específico:

```bash
php artisan asaas:webhooks:sync --gateway=UUID_DO_GATEWAY
```

## Rotacionar uma URL

No painel, abra **Financeiro → Gateways** e utilize **Regenerar URL**. A plataforma primeiro atualiza o webhook remoto no Asaas e somente depois persiste o novo token. Se a API rejeitar a atualização, a URL anterior permanece válida.

A rotação é registrada na auditoria sem armazenar o token ou a URL em texto puro; apenas o hash da URL é registrado.

## Idempotência e isolamento

A unicidade dos eventos é aplicada por:

```text
payment_gateway_config_id + provider + external_event_id
```

O job restringe a busca da fatura ao mesmo tenant, empresa e gateway. Um evento recebido por uma empresa nunca pesquisa ou liquida faturas de outra empresa.
