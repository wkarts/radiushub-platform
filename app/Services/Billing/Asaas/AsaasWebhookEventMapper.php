<?php

declare(strict_types=1);

namespace App\Services\Billing\Asaas;

use App\Enums\InvoiceStatus;

final class AsaasWebhookEventMapper
{
    /** @var list<string> */
    public const PAID_EVENTS = ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'];

    /** @var list<string> */
    public const REFUNDED_EVENTS = ['PAYMENT_REFUNDED', 'PAYMENT_PARTIALLY_REFUNDED'];

    /** @var list<string> */
    public const CHARGEBACK_EVENTS = [
        'PAYMENT_CHARGEBACK_REQUESTED',
        'PAYMENT_CHARGEBACK_DISPUTE',
        'PAYMENT_AWAITING_CHARGEBACK_REVERSAL',
    ];

    public function invoiceStatus(string $event): ?InvoiceStatus
    {
        return match (true) {
            in_array($event, self::PAID_EVENTS, true) => InvoiceStatus::Paid,
            $event === 'PAYMENT_OVERDUE' => InvoiceStatus::Overdue,
            $event === 'PAYMENT_DELETED' => InvoiceStatus::Cancelled,
            $event === 'PAYMENT_RESTORED' => InvoiceStatus::Pending,
            $event === 'PAYMENT_REFUNDED' => InvoiceStatus::Refunded,
            $event === 'PAYMENT_PARTIALLY_REFUNDED' => InvoiceStatus::PartiallyRefunded,
            in_array($event, self::CHARGEBACK_EVENTS, true) => InvoiceStatus::Chargeback,
            in_array($event, ['PAYMENT_CREATED', 'PAYMENT_UPDATED', 'PAYMENT_REFUND_IN_PROGRESS'], true) => InvoiceStatus::Pending,
            default => null,
        };
    }
}
