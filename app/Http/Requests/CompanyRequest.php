<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->user()?->roleForTenant(session(config('tenancy.session_key')));
        return (bool) ($this->user()?->is_super_admin || $role === 'tenant_admin');
    }


    protected function prepareForValidation(): void
    {
        $raw = trim((string) $this->input('usage_limits_json', ''));
        if ($raw === '') {
            $this->merge(['usage_limits' => null]);
            return;
        }

        try {
            $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages([
                'usage_limits_json' => 'Os limites devem ser informados como um objeto JSON válido.',
            ]);
        }

        $this->merge(['usage_limits' => $decoded]);
    }

    public function rules(): array
    {
        $company = $this->route('company');
        $createAdmin = $this->boolean('create_admin');

        return [
            'legal_name' => ['required', 'string', 'max:180'],
            'trade_name' => ['nullable', 'string', 'max:180'],
            'document' => ['nullable', 'string', 'max:20', Rule::unique('companies', 'document')->where(fn ($q) => $q->where('tenant_id', session(config('tenancy.session_key'))))->ignore($company?->id)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'zip_code' => ['nullable', 'string', 'max:12'],
            'street' => ['nullable', 'string', 'max:180'],
            'number' => ['nullable', 'string', 'max:30'],
            'complement' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'size:2'],
            'subscription_plan' => ['nullable', 'string', 'max:80'],
            'usage_limits' => ['nullable', 'array'],
            'usage_limits.*' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['active', 'suspended', 'cancelled'])],
            'active' => ['nullable', 'boolean'],
            'create_admin' => ['nullable', 'boolean'],
            'admin_name' => [Rule::requiredIf($createAdmin), 'nullable', 'string', 'max:120'],
            'admin_email' => [Rule::requiredIf($createAdmin), 'nullable', 'email', 'max:255'],
            'admin_login' => ['nullable', 'alpha_dash', 'max:80', Rule::unique('users', 'login')],
            'admin_password' => [Rule::requiredIf($createAdmin), 'nullable', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
            'admin_role_id' => [Rule::requiredIf($createAdmin), 'nullable', 'uuid', Rule::exists('roles', 'id')->where(fn ($q) => $q->where('scope', 'company')->where('active', true)->where(fn ($sub) => $sub->whereNull('tenant_id')->orWhere('tenant_id', session(config('tenancy.session_key')))))],
            'send_password_link' => ['nullable', 'boolean'],
        ];
    }
}
