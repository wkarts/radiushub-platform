<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ServiceContract;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class InvoiceRequest extends TenantAwareRequest
{
    public function authorize(): bool
    {
        return true;
    }


    protected function prepareForValidation(): void
    {
        if ($this->input('gateway_driver') === 'manual') {
            $this->merge([
                'payment_gateway_config_id' => null,
                'billing_type' => 'UNDEFINED',
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'subscriber_id' => ['required', 'uuid', Rule::exists('subscribers', 'id')->where(fn ($query) => $query->where('tenant_id', $this->tenantId()))],
            'service_contract_id' => ['nullable', 'uuid', Rule::exists('service_contracts', 'id')->where(fn ($query) => $query->where('tenant_id', $this->tenantId()))],
            'description' => ['required', 'string', 'max:255'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'gateway_driver' => ['required', Rule::in(['manual', 'asaas'])],
            'payment_gateway_config_id' => [
                'nullable',
                'uuid',
                Rule::exists('payment_gateway_configs', 'id')->where(fn ($query) => $query
                    ->where('tenant_id', $this->tenantId())
                    ->where('active', true)
                    ->where('driver', 'asaas')),
            ],
            'billing_type' => ['nullable', Rule::in(['PIX', 'BOLETO', 'CREDIT_CARD', 'UNDEFINED'])],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->filled('service_contract_id')) {
                $contract = ServiceContract::query()->find($this->input('service_contract_id'));
                if ($contract && $contract->subscriber_id !== $this->input('subscriber_id')) {
                    $validator->errors()->add('service_contract_id', 'O contrato pertence a outro cliente.');
                }
            }

            if ($this->input('gateway_driver') === 'asaas' && ! $this->filled('payment_gateway_config_id')) {
                $validator->errors()->add('payment_gateway_config_id', 'Selecione a configuração Asaas da empresa.');
            }
        }];
    }
}
