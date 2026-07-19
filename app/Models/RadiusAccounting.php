<?php
namespace App\Models;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class RadiusAccounting extends Model { use BelongsToTenant; protected $table='radius_accounting'; protected $guarded=[]; public $timestamps=false; protected function casts(): array { return ['acct_start_time'=>'datetime','acct_update_time'=>'datetime','acct_stop_time'=>'datetime']; } public function access(): BelongsTo { return $this->belongsTo(NetworkAccess::class,'network_access_id'); } public function mikrotik(): BelongsTo { return $this->belongsTo(MikrotikDevice::class,'mikrotik_device_id'); } }
