<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()?->id)],
            'current_password' => ['required_with:password', 'nullable', 'current_password'],
            'password' => ['nullable', 'string', 'min:12', 'confirmed'],
        ];
    }
}
