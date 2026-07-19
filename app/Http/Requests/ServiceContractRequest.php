<?php

namespace App\Http\Requests;

use App\Models\NetworkAccess;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ServiceContractRequest extends TenantAwareRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $contract = $this->route('contract');

        return [
            'subscriber_id' => ['required', 'uuid', Rule::exists('subscribers', 'id')->where(fn ($q) => $q->where('tenant_id', $this->tenantId())->where('company_id', $this->companyId()))],
            'network_access_id' => ['required', 'uuid', Rule::exists('network_accesses', 'id')->where(fn ($q) => $q->where('tenant_id', $this->tenantId())->where('company_id', $this->companyId()))],
            'internet_plan_id' => ['required', 'uuid', Rule::exists('internet_plans', 'id')->where(fn ($q) => $q->where('tenant_id', $this->tenantId())->where('company_id', $this->companyId()))],
            'number' => ['required', 'string', 'max:60', Rule::unique('service_contracts', 'number')->where(fn ($q) => $q->where('tenant_id', $this->tenantId())->where('company_id', $this->companyId()))->ignore($contract?->id)],
            'amount' => ['required', 'numeric', 'min:0'],
            'billing_day' => ['required', 'integer', 'between:1,28'],
            'grace_days' => ['required', 'integer', 'between:0,60'],
            'status' => ['required', Rule::in(['draft', 'active', 'suspended', 'cancelled'])],
            'started_at' => ['required', 'date'],
            'ended_at' => ['nullable', 'date', 'after_or_equal:started_at'],
            'next_invoice_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $access = NetworkAccess::query()->find($this->input('network_access_id'));

            if (! $access) return;

            if ($access->subscriber_id !== $this->input('subscriber_id')) {
                $validator->errors()->add('network_access_id', 'A credencial pertence a outro cliente.');
            }

            if ($access->internet_plan_id !== $this->input('internet_plan_id')) {
                $validator->errors()->add('internet_plan_id', 'O plano do contrato deve ser o mesmo da credencial de acesso.');
            }
        }];
    }
}
