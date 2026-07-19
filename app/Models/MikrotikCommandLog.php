<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MikrotikCommandLog extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected function casts(): array { return ['created_at' => 'datetime']; }
    public function device(): BelongsTo { return $this->belongsTo(MikrotikDevice::class, 'mikrotik_device_id'); }
}
