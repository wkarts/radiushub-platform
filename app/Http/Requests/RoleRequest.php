<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user()?->is_super_admin || $this->user()?->roleForTenant(session(config('tenancy.session_key'))) === 'tenant_admin');
    }

    public function rules(): array
    {
        $role = $this->route('role');
        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'alpha_dash', 'max:80', Rule::unique('roles', 'slug')->where(fn ($q) => $q->where('tenant_id', session(config('tenancy.session_key'))))->ignore($role?->id)],
            'scope' => ['required', Rule::in(['tenant', 'company'])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'slug')],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
