<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MikrotikDeviceRequest extends TenantAwareRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->route('mikrotik') ? 'update' : 'create', $this->route('mikrotik') ?: \App\Models\MikrotikDevice::class) ?? false;
    }

    public function rules(): array
    {
        $device = $this->route('mikrotik');
        $companyId = session(config('tenancy.company_session_key'));

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('mikrotik_devices', 'name')
                ->where(fn ($q) => $q->where('tenant_id', $this->tenantId())->where('company_id', $companyId))
                ->ignore($device?->id)],
            'site_name' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:3000'],
            'management_host' => ['required', 'string', 'max:255'],
            'ssh_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'ssh_username' => ['required', 'string', 'max:120'],
            'ssh_private_key' => [$device ? 'nullable' : 'required', 'nullable', 'string', 'max:32768'],
            'ssh_public_key' => ['nullable', 'string', 'max:8192'],
            'ssh_passphrase' => ['nullable', 'string', 'max:1024'],
            'ssh_password' => ['nullable', 'string', 'max:1024'],
            'ssh_password_fallback_enabled' => ['nullable', 'boolean'],
            'ssh_host_fingerprint' => ['nullable', 'string', 'max:255', 'regex:/^SHA256:[A-Za-z0-9+\/_-]+$/'],
            'ssh_connection_timeout' => ['required', 'integer', 'min:3', 'max:120'],
            'ssh_command_timeout' => ['required', 'integer', 'min:3', 'max:300'],
            'radius_source_ip' => ['required', 'ip', Rule::unique('mikrotik_devices', 'radius_source_ip')->ignore($device?->id)],
            'radius_secret' => [$device ? 'nullable' : 'required', 'nullable', 'string', 'min:12', 'max:255'],
            'coa_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'hotspot_enabled' => ['nullable', 'boolean'],
            'pppoe_enabled' => ['nullable', 'boolean'],
            'active' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $device = $this->route('mikrotik');
            if (! $device && ! $this->filled('ssh_private_key')) {
                $validator->errors()->add('ssh_private_key', 'Informe a chave privada SSH. A senha não substitui a chave no cadastro inicial.');
            }

            if ($this->boolean('ssh_password_fallback_enabled') && ! $this->filled('ssh_password') && ! $device?->ssh_password_ciphertext) {
                $validator->errors()->add('ssh_password', 'Informe a senha de contingência ou desative o fallback por senha.');
            }

            if ($this->boolean('ssh_password_fallback_enabled') && ! config('mikrotik.ssh.allow_password_fallback')) {
                $validator->errors()->add('ssh_password_fallback_enabled', 'O fallback por senha está desabilitado globalmente no servidor.');
            }
        }];
    }
}
