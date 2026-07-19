<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use BelongsToTenant, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'legal_name', 'trade_name', 'document', 'email', 'phone',
        'zip_code', 'street', 'number', 'complement', 'district', 'city', 'state',
        'subscription_plan', 'usage_limits', 'status', 'active',
    ];

    protected function casts(): array
    {
        return ['usage_limits' => 'array', 'active' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role_id', 'is_primary', 'active'])
            ->withTimestamps();
    }

    public function mikrotiks(): HasMany { return $this->hasMany(MikrotikDevice::class); }
    public function vouchers(): HasMany { return $this->hasMany(Voucher::class); }
    public function profiles(): HasMany { return $this->hasMany(NetworkProfile::class); }
}
