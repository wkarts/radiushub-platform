<?php

namespace App\Services\Vouchers;

use App\Models\Voucher;
use App\Models\VoucherBatch;
use App\Services\Limits\UsageLimitService;
use App\Services\Security\RadiusCredentialVault;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class VoucherGeneratorService
{
    private const ALPHABETS = [
        'readable' => 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789',
        'numeric' => '0123456789',
        'alphanumeric' => 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789',
    ];

    public function __construct(
        private readonly TenantContext $tenant,
        private readonly CompanyContext $company,
        private readonly RadiusCredentialVault $vault,
        private readonly UsageLimitService $limits,
    ) {}

    public function generate(array $data): VoucherBatch
    {
        $quantity = max(1, min((int) ($data['quantity'] ?? 1), 5000));
        $length = max(4, min((int) ($data['code_length'] ?? 8), 32));
        $alphabet = self::ALPHABETS[$data['alphabet'] ?? 'readable'] ?? self::ALPHABETS['readable'];
        $prefix = trim((string) ($data['prefix'] ?? ''));
        $suffix = trim((string) ($data['suffix'] ?? ''));
        $this->limits->assertCompany($this->company->company(), 'vouchers', $quantity);

        return DB::transaction(function () use ($data, $quantity, $length, $alphabet, $prefix, $suffix): VoucherBatch {
            $batch = VoucherBatch::query()->create([
                'tenant_id' => $this->tenant->requireId(),
                'company_id' => $this->company->requireId(),
                'generated_by' => Auth::id(),
                'name' => $data['batch_name'],
                'quantity' => $quantity,
                'settings' => collect($data)->except(['password'])->all(),
            ]);

            for ($i = 0; $i < $quantity; $i++) {
                [$code, $password] = $this->uniqueCredentials($alphabet, $length, $prefix, $suffix);

                Voucher::query()->create([
                    'tenant_id' => $this->tenant->requireId(),
                    'company_id' => $this->company->requireId(),
                    'voucher_batch_id' => $batch->id,
                    'mikrotik_device_id' => $data['mikrotik_device_id'] ?? null,
                    'internet_plan_id' => $data['internet_plan_id'] ?? null,
                    'network_profile_id' => $data['network_profile_id'] ?? null,
                    'code' => $code,
                    'password_ciphertext' => $this->vault->encrypt($password),
                    'prefix' => $prefix ?: null,
                    'suffix' => $suffix ?: null,
                    'code_length' => $length,
                    'speed_limit' => $data['speed_limit'] ?? null,
                    'data_limit_bytes' => $data['data_limit_bytes'] ?? null,
                    'usage_time_limit_seconds' => $data['usage_time_limit_seconds'] ?? null,
                    'max_devices' => $data['max_devices'] ?? 1,
                    'valid_from' => $data['valid_from'] ?? null,
                    'expires_at' => ($data['validity_mode'] ?? 'fixed') === 'fixed' ? ($data['expires_at'] ?? null) : null,
                    'validity_mode' => $data['validity_mode'] ?? 'fixed',
                    'validity_duration_minutes' => $data['validity_duration_minutes'] ?? null,
                    'session_timeout_seconds' => $data['session_timeout_seconds'] ?? null,
                    'status' => 'available',
                    'notes' => $data['notes'] ?? null,
                ]);
            }

            return $batch->load(['vouchers.plan', 'vouchers.profile', 'vouchers.mikrotik']);
        });
    }

    public function reveal(Voucher $voucher): array
    {
        return ['code' => $voucher->code, 'password' => $this->vault->decrypt($voucher->password_ciphertext)];
    }

    public function activateOnFirstAccess(Voucher $voucher, ?string $deviceIdentifier = null): void
    {
        if ($voucher->first_access_at) return;
        $expiresAt = $voucher->validity_mode === 'first_access' && $voucher->validity_duration_minutes
            ? now()->addMinutes($voucher->validity_duration_minutes)
            : $voucher->expires_at;

        $voucher->forceFill([
            'status' => 'active', 'activated_at' => now(), 'first_access_at' => now(),
            'last_access_at' => now(), 'expires_at' => $expiresAt,
            'device_identifier' => $deviceIdentifier ?: $voucher->device_identifier,
        ])->save();
    }

    private function uniqueCredentials(string $alphabet, int $length, string $prefix, string $suffix): array
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $body = '';
            for ($i = 0; $i < $length; $i++) $body .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            $code = $prefix.$body.$suffix;
            if (! Voucher::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->requireId())->where('code', $code)->exists()) {
                return [$code, Str::password(10, letters: true, numbers: true, symbols: false, spaces: false)];
            }
        }

        throw new RuntimeException('Não foi possível gerar um código único após várias tentativas.');
    }
}
