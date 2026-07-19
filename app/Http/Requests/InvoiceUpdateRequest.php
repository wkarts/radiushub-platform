<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

final class InvoiceUpdateRequest extends TenantAwareRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'due_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'billing_type' => ['required', Rule::in(['PIX', 'BOLETO', 'CREDIT_CARD', 'UNDEFINED'])],
        ];
    }
}
