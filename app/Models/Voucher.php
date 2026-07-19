<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voucher extends Model
{
    use BelongsToTenant, BelongsToCompany, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'company_id', 'voucher_batch_id', 'mikrotik_device_id',
        'internet_plan_id', 'network_profile_id', 'code', 'password_ciphertext',
        'prefix', 'suffix', 'code_length', 'speed_limit', 'data_limit_bytes',
        'usage_time_limit_seconds', 'max_devices', 'valid_from', 'expires_at',
        'validity_mode', 'validity_duration_minutes', 'session_timeout_seconds',
        'status', 'activated_at', 'first_access_at', 'last_access_at', 'used_at',
        'blocked_at', 'cancelled_at', 'device_identifier', 'notes',
    ];

    protected $hidden = ['password_ciphertext'];

    protected function casts(): array
    {
        return [
            'valid_from' => 'datetime', 'expires_at' => 'datetime', 'activated_at' => 'datetime',
            'first_access_at' => 'datetime', 'last_access_at' => 'datetime',
            'used_at' => 'datetime', 'blocked_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    public function batch(): BelongsTo { return $this->belongsTo(VoucherBatch::class, 'voucher_batch_id'); }
    public function mikrotik(): BelongsTo { return $this->belongsTo(MikrotikDevice::class, 'mikrotik_device_id'); }
    public function plan(): BelongsTo { return $this->belongsTo(InternetPlan::class, 'internet_plan_id'); }
    public function profile(): BelongsTo { return $this->belongsTo(NetworkProfile::class, 'network_profile_id'); }
}
