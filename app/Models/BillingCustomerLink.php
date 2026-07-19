<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BillingCustomerLink extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = [
        'subscriber_id',
        'payment_gateway_config_id',
        'external_customer_id',
        'payload',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGatewayConfig::class, 'payment_gateway_config_id');
    }
}
