<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NetworkProfile extends Model
{
    use BelongsToTenant, BelongsToCompany, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'company_id', 'name', 'service_type', 'rate_limit',
        'data_limit_bytes', 'usage_time_limit_seconds', 'session_timeout_seconds',
        'idle_timeout_seconds', 'max_devices', 'radius_attributes', 'active',
    ];

    public function vouchers(): HasMany { return $this->hasMany(Voucher::class); }

    protected function casts(): array
    {
        return ['radius_attributes' => 'array', 'active' => 'boolean'];
    }
}
