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

    protected $fillable = ['name', 'email', 'password', 'is_super_admin', 'active'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_super_admin' => 'boolean', 'active' => 'boolean'];
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)->withPivot('role')->withTimestamps();
    }

    public function roleForTenant(?string $tenantId): ?string
    {
        if (! $tenantId) return null;
        $tenant = $this->tenants->firstWhere('id', $tenantId) ?? $this->tenants()->whereKey($tenantId)->first();
        return $tenant?->pivot?->role;
    }
}
