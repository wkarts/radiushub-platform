# Financeiro

## Ciclo

1. contrato ativo define valor, dia e tolerância;
2. `billing:generate-recurring` gera fatura;
3. gateway cria cobrança;
4. webhook é persistido de forma idempotente;
5. pagamento baixa fatura e reativa acesso;
6. `billing:suspend-overdue` marca vencimento e suspende após tolerância.

## Novos gateways

Implemente `App\Contracts\Billing\BillingGateway` e registre o driver no `BillingManager`. Credenciais devem permanecer em `PaymentGatewayConfig` com cast `encrypted:array`.
