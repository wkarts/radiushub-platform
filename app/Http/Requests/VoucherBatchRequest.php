<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class VoucherBatchRequest extends TenantAwareRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $companyId = session(config('tenancy.company_session_key'));

        return [
            'batch_name' => ['required', 'string', 'max:140'],
            'quantity' => ['required', 'integer', 'min:1', 'max:5000'],
            'alphabet' => ['required', Rule::in(['readable', 'numeric', 'alphanumeric'])],
            'prefix' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]*$/'],
            'suffix' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]*$/'],
            'code_length' => ['required', 'integer', 'min:4', 'max:32'],
            'internet_plan_id' => ['nullable', 'uuid', Rule::exists('internet_plans', 'id')->where(fn ($q) => $q->where('tenant_id', $this->tenantId())->where('company_id', $companyId))],
            'network_profile_id' => ['nullable', 'uuid', Rule::exists('network_profiles', 'id')->where(fn ($q) => $q->where('tenant_id', $this->tenantId())->where('company_id', $companyId))],
            'mikrotik_device_id' => ['nullable', 'uuid', Rule::exists('mikrotik_devices', 'id')->where(fn ($q) => $q->where('tenant_id', $this->tenantId())->where('company_id', $companyId))],
            'speed_limit' => ['nullable', 'string', 'max:120'],
            'data_limit_bytes' => ['nullable', 'integer', 'min:1'],
            'usage_time_limit_seconds' => ['nullable', 'integer', 'min:1'],
            'max_devices' => ['required', 'integer', 'min:1', 'max:999'],
            'validity_mode' => ['required', Rule::in(['fixed', 'first_access'])],
            'valid_from' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:valid_from', Rule::requiredIf($this->input('validity_mode') === 'fixed')],
            'validity_duration_minutes' => ['nullable', 'integer', 'min:1', Rule::requiredIf($this->input('validity_mode') === 'first_access')],
            'session_timeout_seconds' => ['nullable', 'integer', 'min:1'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'sync_after_generate' => ['nullable', 'boolean'],
            'print_title' => ['nullable', 'string', 'max:160'],
            'print_footer' => ['nullable', 'string', 'max:500'],
            'print_columns' => ['nullable', 'integer', Rule::in([1, 2, 3, 4])],
            'print_show_company' => ['nullable', 'boolean'],
            'print_show_password' => ['nullable', 'boolean'],
        ];
    }
}
