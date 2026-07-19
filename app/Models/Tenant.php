<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['name', 'slug', 'document', 'email', 'phone', 'timezone', 'active', 'brand_settings'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'brand_settings' => 'array'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function subscribers(): HasMany { return $this->hasMany(Subscriber::class); }
    public function plans(): HasMany { return $this->hasMany(InternetPlan::class); }
    public function mikrotiks(): HasMany { return $this->hasMany(MikrotikDevice::class); }
}
