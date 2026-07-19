<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InternetPlan extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['name', 'service_type', 'download_bps', 'upload_bps', 'rate_limit', 'burst_limit', 'burst_threshold', 'burst_time', 'session_timeout', 'idle_timeout', 'simultaneous_use', 'address_pool', 'monthly_price', 'active', 'radius_reply_attributes'];

    public function contracts(): HasMany { return $this->hasMany(ServiceContract::class); }

    protected function casts(): array
    {
        return ['active' => 'boolean', 'monthly_price' => 'decimal:2', 'radius_reply_attributes' => 'array'];
    }
}
