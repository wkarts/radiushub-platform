<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class TenantUserRequest extends TenantAwareRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(
            'users.manage',
            $this->tenantId(),
            session(config('tenancy.company_session_key'))
        ) ?? false;
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $tenantId = $this->tenantId();
        $canManageTenant = $this->user()?->is_super_admin || $this->user()?->roleForTenant($tenantId) === 'tenant_admin';

        return [
            'name' => ['required', 'string', 'max:120'],
            'login' => ['nullable', 'alpha_dash', 'max:80', Rule::unique('users', 'login')->ignore($user?->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'nullable', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
            'role_id' => ['required', 'uuid', Rule::exists('roles', 'id')->where(fn ($q) => $q
                ->where('scope', 'company')
                ->where('active', true)
                ->where(fn ($sub) => $sub->whereNull('tenant_id')->orWhere('tenant_id', $tenantId)))],
            'tenant_admin' => [$canManageTenant ? 'nullable' : 'prohibited', 'boolean'],
            'must_change_password' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
