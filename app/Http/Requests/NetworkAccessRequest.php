<?php

namespace App\Http\Requests;

use App\Models\InternetPlan;
use App\Services\Tenancy\CompanyContext;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class NetworkAccessRequest extends TenantAwareRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('accesses.manage', session(config('tenancy.session_key')), app(CompanyContext::class)->id()) ?? false;
    }

    public function rules(): array
    {
        $access = $this->route('access');
        $companyId = app(CompanyContext::class)->requireId();

        return [
            'subscriber_id' => ['required', 'uuid', Rule::exists('subscribers', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'internet_plan_id' => ['required', 'uuid', Rule::exists('internet_plans', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'network_profile_id' => ['nullable', 'uuid', Rule::exists('network_profiles', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'mikrotik_device_id' => ['nullable', 'uuid', Rule::exists('mikrotik_devices', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'service_type' => ['required', Rule::in(['hotspot', 'pppoe', 'both'])],
            'username' => ['required', 'string', 'max:120', Rule::unique('network_accesses', 'username')->where(fn ($q) => $q->where('company_id', $companyId))->ignore($access?->id)],
            'password' => [$access ? 'nullable' : 'required', 'nullable', 'string', 'min:4', 'max:255'],
            'caller_id' => ['nullable', 'string', 'max:80'],
            'simultaneous_use' => ['nullable', 'integer', 'min:1', 'max:999'],
            'connection_limit' => ['nullable', 'integer', 'min:1', 'max:999'],
            'static_ip' => ['nullable', 'ip'],
            'pool_name' => ['nullable', 'string', 'max:100'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::in(['active', 'suspended', 'blocked', 'disabled'])],
            'notes' => ['nullable', 'string', 'max:5000'],
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
