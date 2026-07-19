<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class GatewayRequest extends TenantAwareRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'driver' => [
                'required',
                Rule::in(['manual', 'asaas']),
                Rule::unique('payment_gateway_configs', 'driver')
                    ->where(fn ($query) => $query->where('tenant_id', $this->tenantId())->where('company_id', $this->companyId()))
                    ->ignore($this->route('gateway')?->id),
            ],
            'name' => ['required', 'string', 'max:120'],
            'environment' => ['required', Rule::in(['sandbox', 'production'])],
            'active' => ['nullable', 'boolean'],
            'api_key' => ['nullable', 'string', 'max:1000'],
            'webhook_token' => ['nullable', 'string', 'min:12', 'max:255'],
            'webhook_email' => ['nullable', 'email:rfc', 'max:255'],
            'notification_disabled' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $gateway = $this->route('gateway');

            if ($gateway !== null && $gateway->driver !== $this->input('driver')) {
                $validator->errors()->add('driver', 'O driver do gateway não pode ser alterado após o cadastro.');

                return;
            }

            if ($this->input('driver') !== 'asaas') {
                return;
            }

            $isCreate = $gateway === null;
            if ($isCreate && trim((string) $this->input('api_key')) === '') {
                $validator->errors()->add('api_key', 'A API Key é obrigatória ao cadastrar o Asaas.');
            }
        }];
    }
}
