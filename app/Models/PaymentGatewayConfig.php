<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentGatewayConfig extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'driver',
        'name',
        'environment',
        'active',
        'credentials',
        'settings',
        'webhook_token',
    ];

    protected $hidden = ['credentials', 'webhook_token'];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'webhook_token' => 'encrypted',
        ];
    }

    public function customerLinks(): HasMany
    {
        return $this->hasMany(BillingCustomerLink::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function mergeSettings(array $values): void
    {
        $this->forceFill(['settings' => array_replace($this->settings ?? [], $values)])->save();
    }
}
