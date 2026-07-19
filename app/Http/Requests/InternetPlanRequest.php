<?php

namespace App\Http\Requests;

use App\Services\Tenancy\CompanyContext;
use Illuminate\Validation\Rule;

class InternetPlanRequest extends TenantAwareRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('plans.manage', session(config('tenancy.session_key')), app(CompanyContext::class)->id()) ?? false;
    }

    public function rules(): array
    {
        $plan = $this->route('plan');
        $companyId = app(CompanyContext::class)->requireId();

        return [
            'network_profile_id' => ['nullable', 'uuid', Rule::exists('network_profiles', 'id')->where(fn ($q) => $q->where('company_id', $companyId))],
            'name' => ['required', 'string', 'max:120', Rule::unique('internet_plans', 'name')->where(fn ($q) => $q->where('company_id', $companyId))->ignore($plan?->id)],
            'service_type' => ['required', Rule::in(['hotspot', 'pppoe', 'both'])],
            'download_bps' => ['required', 'integer', 'min:1'],
            'upload_bps' => ['required', 'integer', 'min:1'],
            'rate_limit' => ['required', 'string', 'max:120'],
            'burst_limit' => ['nullable', 'string', 'max:120'],
            'burst_threshold' => ['nullable', 'string', 'max:120'],
            'burst_time' => ['nullable', 'string', 'max:120'],
            'session_timeout' => ['nullable', 'integer', 'min:0'],
            'idle_timeout' => ['nullable', 'integer', 'min:0'],
            'simultaneous_use' => ['required', 'integer', 'min:1', 'max:999'],
            'address_pool' => ['nullable', 'string', 'max:100'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'active' => ['nullable', 'boolean'],
            'radius_reply_attributes' => ['nullable', 'array'],
        ];
    }
}
