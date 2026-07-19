<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\InvoiceStatus;
use App\Services\Billing\Asaas\AsaasWebhookEventMapper;
use PHPUnit\Framework\TestCase;

final class AsaasWebhookEventMapperTest extends TestCase
{
    public function test_it_maps_payment_events_to_local_invoice_statuses(): void
    {
        $mapper = new AsaasWebhookEventMapper();

        self::assertSame(InvoiceStatus::Paid, $mapper->invoiceStatus('PAYMENT_RECEIVED'));
        self::assertSame(InvoiceStatus::Overdue, $mapper->invoiceStatus('PAYMENT_OVERDUE'));
        self::assertSame(InvoiceStatus::Refunded, $mapper->invoiceStatus('PAYMENT_REFUNDED'));
        self::assertSame(InvoiceStatus::PartiallyRefunded, $mapper->invoiceStatus('PAYMENT_PARTIALLY_REFUNDED'));
        self::assertSame(InvoiceStatus::Chargeback, $mapper->invoiceStatus('PAYMENT_CHARGEBACK_REQUESTED'));
        self::assertNull($mapper->invoiceStatus('UNRELATED_EVENT'));
    }
}
