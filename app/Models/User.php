<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, HasUuids, Notifiable;

    protected $fillable = [
        'name', 'login', 'email', 'password', 'is_super_admin', 'active',
        'must_change_password', 'email_verified_at', 'two_factor_secret', 'two_factor_recovery_codes',
        'two_factor_confirmed_at', 'last_login_at', 'last_login_ip',
    ];

    protected $hidden = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'active' => 'boolean',
            'must_change_password' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)->withPivot('role')->withTimestamps();
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)
            ->withPivot(['role_id', 'is_primary', 'active'])
            ->withTimestamps();
    }

    public function roleForTenant(?string $tenantId): ?string
    {
        if (! $tenantId) return null;
        $tenant = $this->tenants->firstWhere('id', $tenantId) ?? $this->tenants()->whereKey($tenantId)->first();
        return $tenant?->pivot?->role;
    }

    public function roleForCompany(?string $companyId): ?Role
    {
        if (! $companyId) return null;

        $company = $this->companies()
            ->whereKey($companyId)
            ->wherePivot('active', true)
            ->first();

        $roleId = $company?->pivot?->role_id;

        return $roleId ? Role::query()->with('permissions')->find($roleId) : null;
    }

    public function hasPermission(string $permission, ?string $tenantId = null, ?string $companyId = null): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        if ($companyId) {
            $role = $this->roleForCompany($companyId);
            if ($role && $role->active) {
                return $role->permissions->contains('slug', $permission)
                    || $role->permissions->contains('slug', '*');
            }
        }

        // Compatibilidade com os perfis legados da versão 1.2.
        $legacy = $this->roleForTenant($tenantId);
        if ($legacy === 'tenant_admin') return true;

        $map = [
            'network_admin' => ['mikrotiks.*', 'profiles.*', 'plans.*', 'accesses.*', 'vouchers.*', 'sessions.*'],
            'billing' => ['subscribers.*', 'contracts.*', 'invoices.*', 'gateways.*'],
            'operator' => ['subscribers.view', 'subscribers.manage', 'accesses.view', 'accesses.manage', 'vouchers.view', 'vouchers.manage', 'sessions.view'],
            'viewer' => ['*.view'],
        ];

        foreach ($map[$legacy] ?? [] as $allowed) {
            if ($allowed === $permission || ($allowed === '*.view' && str_ends_with($permission, '.view'))) return true;
            if (str_ends_with($allowed, '.*') && str_starts_with($permission, substr($allowed, 0, -1))) return true;
        }

        return false;
    }
}
