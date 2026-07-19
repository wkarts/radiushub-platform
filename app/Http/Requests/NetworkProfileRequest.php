<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class NetworkProfileRequest extends TenantAwareRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $profile = $this->route('profile');
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('network_profiles', 'name')
                ->where(fn ($q) => $q->where('tenant_id', $this->tenantId())->where('company_id', session(config('tenancy.company_session_key'))))
                ->ignore($profile?->id)],
            'service_type' => ['required', Rule::in(['hotspot', 'pppoe', 'both'])],
            'rate_limit' => ['nullable', 'string', 'max:120'],
            'data_limit_bytes' => ['nullable', 'integer', 'min:1'],
            'usage_time_limit_seconds' => ['nullable', 'integer', 'min:1'],
            'session_timeout_seconds' => ['nullable', 'integer', 'min:1'],
            'idle_timeout_seconds' => ['nullable', 'integer', 'min:1'],
            'max_devices' => ['required', 'integer', 'min:1', 'max:999'],
            'radius_attributes' => ['nullable', 'array'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
