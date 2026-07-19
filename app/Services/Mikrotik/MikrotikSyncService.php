<?php

namespace App\Services\Mikrotik;

use App\Models\InternetPlan;
use App\Models\MikrotikDevice;
use App\Models\NetworkAccess;
use App\Models\Voucher;
use App\Services\Security\RadiusCredentialVault;
use RuntimeException;

final class MikrotikSyncService
{
    public function __construct(
        private readonly MikrotikSshService $ssh,
        private readonly RadiusCredentialVault $credentials,
    ) {}

    public function syncPlan(InternetPlan $plan, ?MikrotikDevice $device = null): array
    {
        $device ??= $plan->company?->mikrotiks()->where('active', true)->first();
        if (! $device) {
            throw new RuntimeException('Nenhum MikroTik ativo foi encontrado para a empresa.');
        }

        return $this->ssh->executeApproved($device, 'sync-profile', [
            'name' => $plan->networkProfile?->name ?: $plan->name,
            'service_type' => $this->enumValue($plan->service_type),
            'rate_limit' => $plan->networkProfile?->rate_limit ?: $plan->rate_limit,
            'session_timeout_seconds' => $plan->networkProfile?->session_timeout_seconds ?: $plan->session_timeout,
            'max_devices' => $plan->networkProfile?->max_devices ?: $plan->simultaneous_use,
        ]);
    }

    public function syncAccess(NetworkAccess $access): array
    {
        $device = $access->mikrotik;
        if (! $device) {
            throw new RuntimeException('O acesso não possui MikroTik vinculado.');
        }

        return $this->ssh->executeApproved($device, 'sync-access', [
            'service_type' => $this->enumValue($access->service_type),
            'username' => $access->username,
            'password' => $this->credentials->decrypt($access->password_ciphertext),
            'profile' => $access->profile?->name ?: $access->plan?->networkProfile?->name ?: $access->plan?->name ?: 'default',
            'caller_id' => $access->caller_id,
            'disabled' => ! in_array($this->enumValue($access->status), ['active'], true),
        ]);
    }

    public function syncVoucher(Voucher $voucher): array
    {
        if (! $voucher->mikrotik) {
            throw new RuntimeException('O voucher não possui MikroTik vinculado.');
        }

        return $this->ssh->executeApproved($voucher->mikrotik, 'sync-voucher', [
            'code' => $voucher->code,
            'password' => $this->credentials->decrypt($voucher->password_ciphertext),
            'profile' => $voucher->profile?->name ?: $voucher->plan?->networkProfile?->name ?: $voucher->plan?->name ?: 'default',
            'disabled' => ! in_array($this->enumValue($voucher->status), ['available', 'active'], true),
        ]);
    }

    /**
     * Sincroniza os recursos da empresa com um equipamento específico.
     * Os recursos são enviados em ordem determinística: perfis, acessos e vouchers.
     */
    public function syncDevice(MikrotikDevice $device): array
    {
        if (! $device->active) {
            throw new RuntimeException('O equipamento está desativado.');
        }

        $summary = [
            'profiles' => ['success' => 0, 'failed' => 0],
            'accesses' => ['success' => 0, 'failed' => 0],
            'vouchers' => ['success' => 0, 'failed' => 0],
            'errors' => [],
        ];

        $continueOnError = (bool) config('mikrotik.synchronization.continue_on_error', true);
        $batchSize = max(10, min(1000, (int) config('mikrotik.synchronization.batch_size', 100)));

        InternetPlan::query()
            ->where('company_id', $device->company_id)
            ->where('active', true)
            ->with('networkProfile')
            ->orderBy('id')
            ->chunkById($batchSize, function ($plans) use ($device, &$summary, $continueOnError): void {
                foreach ($plans as $plan) {
                    $this->recordSyncResult('profiles', $plan->name, fn () => $this->syncPlan($plan, $device), $summary, $continueOnError);
                }
            });

        NetworkAccess::query()
            ->where('mikrotik_device_id', $device->id)
            ->with(['plan.networkProfile', 'profile'])
            ->orderBy('id')
            ->chunkById($batchSize, function ($accesses) use (&$summary, $continueOnError): void {
                foreach ($accesses as $access) {
                    $this->recordSyncResult('accesses', $access->username, fn () => $this->syncAccess($access), $summary, $continueOnError);
                }
            });

        Voucher::query()
            ->where('mikrotik_device_id', $device->id)
            ->whereNotIn('status', ['cancelled'])
            ->with(['plan.networkProfile', 'profile', 'mikrotik'])
            ->orderBy('id')
            ->chunkById($batchSize, function ($vouchers) use (&$summary, $continueOnError): void {
                foreach ($vouchers as $voucher) {
                    $this->recordSyncResult('vouchers', $voucher->code, fn () => $this->syncVoucher($voucher), $summary, $continueOnError);
                }
            });

        $failed = array_sum(array_column($summary, 'failed'));
        $device->forceFill([
            'last_sync_at' => now(),
            'status' => $failed === 0 ? 'online' : 'error',
            'last_error' => $failed === 0 ? null : implode(' | ', array_slice($summary['errors'], 0, 10)),
        ])->save();

        $summary['ok'] = $failed === 0;
        $summary['total_success'] = array_sum(array_column($summary, 'success'));
        $summary['total_failed'] = $failed;

        return $summary;
    }

    private function recordSyncResult(
        string $group,
        string $identifier,
        callable $operation,
        array &$summary,
        bool $continueOnError,
    ): void {
        try {
            $result = $operation();
            if (! ($result['ok'] ?? false)) {
                throw new RuntimeException((string) ($result['error'] ?? 'Falha não identificada.'));
            }

            $summary[$group]['success']++;
        } catch (\Throwable $exception) {
            $summary[$group]['failed']++;
            $summary['errors'][] = sprintf('%s %s: %s', $group, $identifier, $exception->getMessage());

            if (! $continueOnError) {
                throw $exception;
            }
        }
    }

    private function enumValue(mixed $value): string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
    }
}
