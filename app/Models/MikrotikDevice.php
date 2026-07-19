<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MikrotikDevice extends Model
{
    use BelongsToTenant, BelongsToCompany, HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'company_id', 'name', 'site_name', 'description', 'management_host',
        'connection_method', 'ssh_port', 'ssh_username', 'ssh_private_key_ciphertext',
        'ssh_public_key', 'ssh_passphrase_ciphertext', 'ssh_password_ciphertext',
        'ssh_password_fallback_enabled', 'ssh_host_fingerprint', 'ssh_connection_timeout', 'ssh_command_timeout',
        'api_port', 'api_ssl', 'api_username', 'api_password', 'radius_source_ip',
        'radius_secret_ciphertext', 'coa_port', 'hotspot_enabled', 'pppoe_enabled',
        'active', 'status', 'last_seen_at', 'last_connected_at', 'last_sync_at',
        'last_error', 'router_identity', 'router_model', 'routerboard_name', 'routeros_version',
    ];

    protected $hidden = [
        'api_password', 'radius_secret_ciphertext', 'ssh_private_key_ciphertext',
        'ssh_passphrase_ciphertext', 'ssh_password_ciphertext',
    ];

    protected function casts(): array
    {
        return [
            'api_ssl' => 'boolean', 'api_password' => 'encrypted',
            'ssh_password_fallback_enabled' => 'boolean',
            'hotspot_enabled' => 'boolean', 'pppoe_enabled' => 'boolean', 'active' => 'boolean',
            'last_seen_at' => 'datetime', 'last_connected_at' => 'datetime', 'last_sync_at' => 'datetime',
        ];
    }

    public function accesses(): HasMany { return $this->hasMany(NetworkAccess::class); }
    public function vouchers(): HasMany { return $this->hasMany(Voucher::class); }
    public function connectionLogs(): HasMany { return $this->hasMany(MikrotikConnectionLog::class); }
    public function commandLogs(): HasMany { return $this->hasMany(MikrotikCommandLog::class); }
}
