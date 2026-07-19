<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\AccessStatus;
use App\Enums\ContractStatus;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\RadiusAccounting;
use App\Services\Radius\CoaService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class InvoiceService
{
    public function __construct(
        private readonly BillingManager $billing,
        private readonly CoaService $coa,
    ) {
    }

    public function issue(Invoice $invoice, string $billingType = 'UNDEFINED'): Invoice
    {
        if ($invoice->gateway_driver === 'manual') {
            return $invoice;
        }

        $invoice->forceFill(['billing_type' => $billingType])->save();
        $payload = $this->billing->forInvoice($invoice)->createOrUpdateCharge($invoice, $billingType);

        return $this->applyGatewayPayload($invoice, $payload);
    }

    public function updateRemoteCharge(Invoice $invoice): Invoice
    {
        if ($invoice->gateway_driver === 'manual') {
            return $invoice;
        }

        $payload = $this->billing->forInvoice($invoice)->createOrUpdateCharge(
            $invoice,
            $invoice->billing_type ?: 'UNDEFINED',
        );

        return $this->applyGatewayPayload($invoice, $payload);
    }

    public function synchronize(Invoice $invoice): Invoice
    {
        if ($invoice->gateway_driver === 'manual' || ! $invoice->external_id) {
            return $invoice;
        }

        $payload = $this->billing->forInvoice($invoice)->retrieveCharge($invoice);
        $invoice = $this->applyGatewayPayload($invoice, $payload);
        $status = strtoupper((string) ($payload['status'] ?? ''));

        if (in_array($status, ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH'], true)) {
            return $this->markPaid(
                $invoice,
                (float) ($payload['value'] ?? $invoice->amount),
                (string) ($payload['billingType'] ?? 'asaas'),
                (string) ($payload['id'] ?? $invoice->external_id),
                $payload,
            );
        }

        $localStatus = match ($status) {
            'OVERDUE' => InvoiceStatus::Overdue,
            'REFUNDED' => InvoiceStatus::Refunded,
            'PARTIALLY_REFUNDED' => InvoiceStatus::PartiallyRefunded,
            'CHARGEBACK_REQUESTED', 'CHARGEBACK_DISPUTE' => InvoiceStatus::Chargeback,
            'DELETED' => InvoiceStatus::Cancelled,
            default => null,
        };

        if ($localStatus === InvoiceStatus::Chargeback) {
            $invoice->update(['status' => $localStatus]);
        } elseif ($localStatus !== null && ! in_array($invoice->status, [
            InvoiceStatus::Paid,
            InvoiceStatus::Refunded,
            InvoiceStatus::PartiallyRefunded,
        ], true)) {
            $invoice->update(['status' => $localStatus]);
        }

        return $invoice->refresh();
    }

    public function cancel(Invoice $invoice): Invoice
    {
        if ($invoice->gateway_driver !== 'manual' && $invoice->external_id) {
            $this->billing->forInvoice($invoice)->cancelCharge($invoice);
        }

        $invoice->update([
            'status' => InvoiceStatus::Cancelled,
            'gateway_status' => 'DELETED',
            'cancelled_at' => now(),
        ]);

        return $invoice->refresh();
    }

    public function refund(Invoice $invoice, float $amount, ?string $description = null): Invoice
    {
        $payload = $this->billing->forInvoice($invoice)->refundCharge($invoice, $amount, $description);
        $externalId = 'refund-request:'.Str::uuid();
        $payment = $invoice->payments()->latest('paid_at')->first();

        PaymentRefund::query()->firstOrCreate(
            ['external_id' => $externalId],
            [
                'invoice_id' => $invoice->id,
                'payment_id' => $payment?->id,
                'amount' => $amount,
                'status' => 'requested',
                'description' => $description,
                'payload' => $payload,
                'processed_at' => null,
            ],
        );

        $requestedAmount = (float) $invoice->refunds()->sum('amount');
        $invoice->update([
            'gateway_status' => (string) ($payload['status'] ?? 'REFUND_REQUESTED'),
            'metadata' => array_replace_recursive($invoice->metadata ?? [], [
                'last_refund_response' => $payload,
                'refund_requested_amount' => $requestedAmount,
            ]),
        ]);

        return $invoice->refresh();
    }

    public function markPaid(
        Invoice $invoice,
        float $amount,
        string $method,
        ?string $externalId = null,
        array $payload = [],
    ): Invoice {
        $accessId = null;

        $paidInvoice = DB::transaction(function () use ($invoice, $amount, $method, $externalId, $payload, &$accessId): Invoice {
            $invoice->refresh();

            if (in_array($invoice->status, [
                InvoiceStatus::Paid,
                InvoiceStatus::Refunded,
                InvoiceStatus::PartiallyRefunded,
                InvoiceStatus::Chargeback,
            ], true)) {
                return $invoice;
            }

            Payment::query()->firstOrCreate(
                ['external_id' => $externalId ?: 'manual-'.$invoice->id],
                [
                    'invoice_id' => $invoice->id,
                    'amount' => $amount,
                    'method' => $method,
                    'paid_at' => now(),
                    'payload' => $payload,
                ]
            );

            $invoice->update([
                'paid_amount' => $amount,
                'status' => InvoiceStatus::Paid,
                'gateway_status' => (string) ($payload['status'] ?? 'RECEIVED'),
                'paid_at' => now(),
            ]);

            if ($invoice->contract) {
                $invoice->contract->update([
                    'status' => ContractStatus::Active,
                    'suspended_at' => null,
                ]);

                $invoice->contract->access?->update(['status' => AccessStatus::Active]);
                $accessId = $invoice->contract->network_access_id;
            }

            return $invoice->refresh();
        });

        if ($accessId) {
            RadiusAccounting::query()
                ->where('network_access_id', $accessId)
                ->whereNull('acct_stop_time')
                ->with('mikrotik')
                ->each(function (RadiusAccounting $session): void {
                    try {
                        $this->coa->disconnect($session, 'Administrative-Reset');
                    } catch (Throwable $exception) {
                        Log::warning('Pagamento confirmado, mas a sessão não pôde ser reiniciada.', [
                            'session_id' => $session->id,
                            'error' => $exception->getMessage(),
                        ]);
                    }
                });
        }

        return $paidInvoice;
    }

    /** @param array<string, mixed> $payload */
    public function applyGatewayPayload(Invoice $invoice, array $payload): Invoice
    {
        $pixExpiration = null;
        $expirationValue = $payload['pixExpirationDate'] ?? null;

        if (is_string($expirationValue) && $expirationValue !== '') {
            try {
                $pixExpiration = CarbonImmutable::parse($expirationValue);
            } catch (Throwable) {
                $pixExpiration = null;
            }
        }

        $fingerprint = trim((string) ($payload['_radiushubGatewayFingerprint'] ?? ''));
        $metadata = array_replace_recursive($invoice->metadata ?? [], [
            'gateway_response' => $payload,
        ]);

        if ($fingerprint !== '') {
            $metadata['gateway_fingerprint'] = $fingerprint;
        }

        $invoice->update([
            'external_id' => $payload['id'] ?? $invoice->external_id,
            'gateway_status' => $payload['status'] ?? $invoice->gateway_status,
            'billing_type' => $payload['billingType'] ?? $invoice->billing_type,
            'payment_url' => $payload['invoiceUrl'] ?? $invoice->payment_url,
            'bank_slip_url' => $payload['bankSlipUrl'] ?? $payload['invoiceUrl'] ?? $invoice->bank_slip_url,
            'bank_slip_line' => $payload['bankSlipIdentificationField'] ?? $invoice->bank_slip_line,
            'pix_copy_paste' => $payload['pixCopyPaste'] ?? $invoice->pix_copy_paste,
            'pix_qr_code' => $payload['pixEncodedImage'] ?? $invoice->pix_qr_code,
            'pix_expiration_at' => $pixExpiration ?? $invoice->pix_expiration_at,
            'last_synced_at' => now(),
            'metadata' => $metadata,
        ]);

        return $invoice->refresh();
    }
}
