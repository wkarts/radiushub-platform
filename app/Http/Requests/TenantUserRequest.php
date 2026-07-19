<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class TenantUserRequest extends TenantAwareRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'nullable', 'string', 'min:10', 'confirmed'],
            'role' => ['required', Rule::in(['tenant_admin', 'network_admin', 'billing', 'operator', 'viewer'])],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
