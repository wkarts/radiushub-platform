<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_super_admin;
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
        $tenant = $this->route('tenant');

        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'alpha_dash', 'max:80', Rule::unique('tenants', 'slug')->ignore($tenant?->id)],
            'document' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'timezone' => ['required', 'timezone'],
            'subscription_plan' => ['nullable', 'string', 'max:80'],
            'usage_limits' => ['nullable', 'array'],
            'usage_limits.*' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['active', 'suspended', 'cancelled'])],
            'active' => ['nullable', 'boolean'],
        ];
    }
}
