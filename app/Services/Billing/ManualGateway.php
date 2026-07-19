<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Billing\BillingGateway;
use App\Models\Invoice;
use App\Models\PaymentGatewayConfig;
use App\Models\Subscriber;

final class ManualGateway implements BillingGateway
{
    public function ensureCustomer(Subscriber $subscriber): string
    {
        return (string) $subscriber->id;
    }

    public function syncCustomer(Subscriber $subscriber): array
    {
        return ['id' => $subscriber->id, 'name' => $subscriber->name];
    }

    public function createOrUpdateCharge(Invoice $invoice, string $billingType = 'UNDEFINED'): array
    {
        return [
            'id' => null,
            'invoiceUrl' => null,
            'status' => 'PENDING',
            'billingType' => 'MANUAL',
        ];
    }

    public function retrieveCharge(Invoice $invoice): array
    {
        return (array) data_get($invoice->metadata, 'gateway_response', []);
    }

    public function cancelCharge(Invoice $invoice): void
    {
    }

    public function refundCharge(Invoice $invoice, float $amount, ?string $description = null): array
    {
        return ['status' => 'REFUNDED', 'value' => $amount, 'description' => $description];
    }

    public function testConnection(): array
    {
        return ['ok' => true, 'message' => 'Gateway manual não depende de serviço externo.'];
    }

    public function synchronizeWebhook(PaymentGatewayConfig $gateway, string $url): array
    {
        return ['ok' => true, 'message' => 'Gateway manual não utiliza webhook.', 'url' => $url];
    }

    public function parseWebhook(array $payload): array
    {
        return $payload;
    }
}
