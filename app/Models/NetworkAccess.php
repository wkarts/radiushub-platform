<?php

namespace App\Models;

use App\Enums\AccessStatus;
use App\Enums\ServiceType;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NetworkAccess extends Model
{
    use BelongsToTenant, BelongsToCompany, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['company_id', 'network_profile_id', 'subscriber_id', 'internet_plan_id', 'mikrotik_device_id', 'service_type', 'username', 'password_ciphertext', 'caller_id', 'simultaneous_use', 'static_ip', 'pool_name', 'starts_at', 'expires_at', 'connection_limit', 'status', 'notes'];
    protected $hidden = ['password_ciphertext'];

    protected function casts(): array
    {
        return ['service_type' => ServiceType::class, 'status' => AccessStatus::class, 'starts_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    public function subscriber(): BelongsTo { return $this->belongsTo(Subscriber::class); }
    public function plan(): BelongsTo { return $this->belongsTo(InternetPlan::class, 'internet_plan_id'); }
    public function profile(): BelongsTo { return $this->belongsTo(NetworkProfile::class, 'network_profile_id'); }
    public function mikrotik(): BelongsTo { return $this->belongsTo(MikrotikDevice::class, 'mikrotik_device_id'); }
    public function contracts(): HasMany { return $this->hasMany(ServiceContract::class); }
}
