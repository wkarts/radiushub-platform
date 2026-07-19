<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class PaymentGatewayConfig extends Model
{
    use BelongsToTenant, BelongsToCompany, HasUuids;

    public const WEBHOOK_PUBLIC_TOKEN_BYTES = 48;

    protected $fillable = [
        'company_id',
        'driver',
        'name',
        'environment',
        'active',
        'credentials',
        'settings',
        'webhook_token',
        'webhook_public_token',
        'webhook_public_token_hash',
    ];

    protected $hidden = [
        'credentials',
        'webhook_token',
        'webhook_public_token',
        'webhook_public_token_hash',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $gateway): void {
            if (blank($gateway->webhook_token)) {
                $gateway->webhook_token = bin2hex(random_bytes(32));
            }

            if (blank($gateway->webhook_public_token)) {
                $gateway->setWebhookPublicToken(self::generateWebhookPublicToken());
            } elseif (blank($gateway->webhook_public_token_hash)) {
                $gateway->webhook_public_token_hash = hash('sha256', (string) $gateway->webhook_public_token);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'webhook_token' => 'encrypted',
            'webhook_public_token' => 'encrypted',
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

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(WebhookEvent::class);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function mergeSettings(array $values): void
    {
        $this->forceFill(['settings' => array_replace($this->settings ?? [], $values)])->save();
    }

    public function ensureWebhookPublicToken(): string
    {
        $token = trim((string) $this->webhook_public_token);

        if ($token === '') {
            $token = self::generateWebhookPublicToken();
            $this->setWebhookPublicToken($token);
            $this->save();
        } elseif (! hash_equals(hash('sha256', $token), (string) $this->webhook_public_token_hash)) {
            $this->forceFill(['webhook_public_token_hash' => hash('sha256', $token)])->save();
        }

        return $token;
    }

    public function regenerateWebhookPublicToken(): string
    {
        $token = self::generateWebhookPublicToken();
        $this->setWebhookPublicToken($token);
        $this->save();

        return $token;
    }

    public function setWebhookPublicToken(string $token): void
    {
        $token = strtolower(trim($token));

        if (! preg_match('/\A[a-f0-9]{96}\z/', $token)) {
            throw new RuntimeException('O token público do webhook deve conter 96 caracteres hexadecimais.');
        }

        $this->forceFill([
            'webhook_public_token' => $token,
            'webhook_public_token_hash' => hash('sha256', $token),
        ]);
    }

    public function webhookUrl(): string
    {
        $token = $this->ensureWebhookPublicToken();

        return rtrim((string) config('app.url'), '/')
            .route('webhooks.asaas', ['token' => $token], false);
    }

    public static function generateWebhookPublicToken(): string
    {
        return bin2hex(random_bytes(self::WEBHOOK_PUBLIC_TOKEN_BYTES));
    }
}
