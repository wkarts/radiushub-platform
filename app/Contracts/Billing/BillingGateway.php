<?php

declare(strict_types=1);

namespace App\Contracts\Billing;

use App\Models\Invoice;
use App\Models\PaymentGatewayConfig;
use App\Models\Subscriber;

interface BillingGateway
{
    public function ensureCustomer(Subscriber $subscriber): string;

    /** @return array<string, mixed> */
    public function syncCustomer(Subscriber $subscriber): array;

    /** @return array<string, mixed> */
    public function createOrUpdateCharge(Invoice $invoice, string $billingType = 'UNDEFINED'): array;

    /** @return array<string, mixed> */
    public function retrieveCharge(Invoice $invoice): array;

    public function cancelCharge(Invoice $invoice): void;

    /** @return array<string, mixed> */
    public function refundCharge(Invoice $invoice, float $amount, ?string $description = null): array;

    /** @return array<string, mixed> */
    public function testConnection(): array;

    /** @return array<string, mixed> */
    public function synchronizeWebhook(PaymentGatewayConfig $gateway, string $url): array;

    /** @return array<string, mixed> */
    public function parseWebhook(array $payload): array;
}
