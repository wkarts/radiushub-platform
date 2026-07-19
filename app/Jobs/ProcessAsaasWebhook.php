<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\PaymentRefund;
use App\Models\WebhookEvent;
use App\Services\Billing\Asaas\AsaasWebhookEventMapper;
use App\Services\Billing\InvoiceService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

final class ProcessAsaasWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $backoff = 30;

    public function __construct(public readonly string $eventId)
    {
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('asaas-webhook:'.$this->eventId))
                ->expireAfter(300)
                ->releaseAfter(15),
        ];
    }

    public function handle(
        TenantContext $context,
        InvoiceService $invoiceService,
        AsaasWebhookEventMapper $mapper,
    ): void {
        $event = WebhookEvent::withoutGlobalScopes()->with('tenant')->findOrFail($this->eventId);
        $context->set($event->tenant);

        try {
            if ($event->processed_at) {
                return;
            }

            $event->update(['status' => 'processing', 'error' => null]);
            $payment = (array) data_get($event->payload, 'payment', []);
            $invoice = $this->findInvoice($payment);

            if (! $invoice) {
                $event->update([
                    'status' => 'ignored',
                    'error' => 'Cobrança não vinculada a uma fatura RadiusHub.',
                    'processed_at' => now(),
                ]);

                return;
            }

            $invoice = $invoiceService->applyGatewayPayload($invoice, $payment);
            $mappedStatus = $mapper->invoiceStatus($event->event_type);

            if (in_array($event->event_type, AsaasWebhookEventMapper::PAID_EVENTS, true)) {
                $invoiceService->markPaid(
                    $invoice,
                    (float) ($payment['value'] ?? $invoice->amount),
                    (string) ($payment['billingType'] ?? 'asaas'),
                    (string) ($payment['id'] ?? $invoice->external_id),
                    $event->payload,
                );
            } elseif (in_array($event->event_type, AsaasWebhookEventMapper::REFUNDED_EVENTS, true)) {
                $this->processRefundEvent($event, $invoice, $payment, $mappedStatus);
            } elseif ($event->event_type === 'PAYMENT_REFUND_DENIED') {
                $invoice->refunds()
                    ->where('status', 'requested')
                    ->oldest()
                    ->first()?->update([
                        'external_id' => $event->external_event_id,
                        'status' => 'PAYMENT_REFUND_DENIED',
                        'payload' => $event->payload,
                        'processed_at' => now(),
                    ]);
            } elseif ($mappedStatus === InvoiceStatus::Chargeback) {
                $invoice->update(['status' => $mappedStatus]);
            } elseif ($mappedStatus !== null && ! in_array($invoice->status, [
                InvoiceStatus::Paid,
                InvoiceStatus::Refunded,
                InvoiceStatus::PartiallyRefunded,
                InvoiceStatus::Chargeback,
            ], true)) {
                $invoice->update(['status' => $mappedStatus]);
            }

            $event->update([
                'status' => 'processed',
                'error' => null,
                'processed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $event->update([
                'status' => 'failed',
                'error' => mb_substr($exception->getMessage(), 0, 4000),
            ]);

            throw $exception;
        } finally {
            $context->clear();
        }
    }

    /** @param array<string, mixed> $payment */
    private function processRefundEvent(
        WebhookEvent $event,
        Invoice $invoice,
        array $payment,
        ?InvoiceStatus $mappedStatus,
    ): void {
        if (PaymentRefund::query()->where('external_id', $event->external_event_id)->exists()) {
            if ($mappedStatus !== null) {
                $invoice->update(['status' => $mappedStatus]);
            }

            return;
        }

        $confirmedBefore = (float) $invoice->refunds()
            ->whereIn('status', AsaasWebhookEventMapper::REFUNDED_EVENTS)
            ->sum('amount');
        $refundedTotal = (float) ($payment['refundedValue'] ?? 0);
        $delta = $refundedTotal > 0
            ? max(0, $refundedTotal - $confirmedBefore)
            : (float) ($payment['value'] ?? $invoice->paid_amount);

        $refund = $invoice->refunds()
            ->where('status', 'requested')
            ->oldest()
            ->first();

        $values = [
            'payment_id' => $invoice->payments()->latest('paid_at')->value('id'),
            'external_id' => $event->external_event_id,
            'amount' => $delta > 0 ? $delta : (float) $invoice->paid_amount,
            'status' => $event->event_type,
            'payload' => $event->payload,
            'processed_at' => now(),
        ];

        if ($refund) {
            $refund->update($values);
        } else {
            PaymentRefund::query()->create($values + ['invoice_id' => $invoice->id]);
        }

        if ($mappedStatus !== null) {
            $invoice->update(['status' => $mappedStatus]);
        }
    }

    /** @param array<string, mixed> $payment */
    private function findInvoice(array $payment): ?Invoice
    {
        $paymentId = trim((string) ($payment['id'] ?? ''));
        $externalReference = trim((string) ($payment['externalReference'] ?? ''));
        $localId = null;

        if (str_starts_with($externalReference, 'RADIUSHUB:invoice:')) {
            $localId = substr($externalReference, strlen('RADIUSHUB:invoice:'));
        } elseif ($externalReference !== '') {
            $localId = $externalReference;
        }

        if ($paymentId === '' && ($localId === null || $localId === '')) {
            return null;
        }

        return Invoice::query()
            ->where(function ($query) use ($paymentId, $localId): void {
                if ($paymentId !== '') {
                    $query->where('external_id', $paymentId);
                }

                if ($localId !== null && $localId !== '') {
                    $paymentId !== ''
                        ? $query->orWhere('id', $localId)
                        : $query->where('id', $localId);
                }
            })
            ->first();
    }
}
