<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Invoice;

final class InvoiceRefundRequest extends TenantAwareRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Invoice|null $invoice */
        $invoice = $this->route('invoice');

        $alreadyRequested = $invoice?->refunds()
            ->where('status', '!=', 'PAYMENT_REFUND_DENIED')
            ->sum('amount') ?? 0;
        $remaining = max(0, (float) ($invoice?->paid_amount ?? 0) - (float) $alreadyRequested);

        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$remaining],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
