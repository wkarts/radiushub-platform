<?php

namespace App\Http\Requests;

use App\Models\InternetPlan;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class NetworkAccessRequest extends TenantAwareRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $access = $this->route('access');

        return [
            'subscriber_id' => ['required', 'uuid', Rule::exists('subscribers', 'id')->where(fn ($q) => $q->where('tenant_id', $this->tenantId()))],
            'internet_plan_id' => ['required', 'uuid', Rule::exists('internet_plans', 'id')->where(fn ($q) => $q->where('tenant_id', $this->tenantId()))],
            'mikrotik_device_id' => ['nullable', 'uuid', Rule::exists('mikrotik_devices', 'id')->where(fn ($q) => $q->where('tenant_id', $this->tenantId()))],
            'service_type' => ['required', Rule::in(['hotspot', 'pppoe', 'both'])],
            'username' => ['required', 'string', 'max:120', Rule::unique('network_accesses', 'username')->where(fn ($q) => $q->where('tenant_id', $this->tenantId()))->ignore($access?->id)],
            'password' => [$access ? 'nullable' : 'required', 'nullable', 'string', 'min:4', 'max:255'],
            'caller_id' => ['nullable', 'string', 'max:80'],
            'simultaneous_use' => ['nullable', 'integer', 'min:1', 'max:999'],
            'static_ip' => ['nullable', 'ip'],
            'pool_name' => ['nullable', 'string', 'max:100'],
            'expires_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['active', 'suspended', 'blocked', 'disabled'])],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $plan = InternetPlan::query()->find($this->input('internet_plan_id'));
            $service = (string) $this->input('service_type');

            if ($plan && $plan->service_type !== 'both' && $service !== 'both' && $plan->service_type !== $service) {
                $validator->errors()->add('service_type', 'O tipo do acesso deve ser compatível com o plano selecionado.');
            }
        }];
    }
}
