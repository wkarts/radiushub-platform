<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MikrotikDevice extends Model
{
    use BelongsToTenant, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['name', 'site_name', 'description', 'management_host', 'api_port', 'api_ssl', 'api_username', 'api_password', 'radius_source_ip', 'radius_secret_ciphertext', 'coa_port', 'hotspot_enabled', 'pppoe_enabled', 'active', 'status', 'last_seen_at', 'last_error'];
    protected $hidden = ['api_password', 'radius_secret_ciphertext'];

    protected function casts(): array
    {
        return ['api_ssl' => 'boolean', 'api_password' => 'encrypted', 'hotspot_enabled' => 'boolean', 'pppoe_enabled' => 'boolean', 'active' => 'boolean', 'last_seen_at' => 'datetime'];
    }

    public function accesses(): HasMany { return $this->hasMany(NetworkAccess::class); }
}
