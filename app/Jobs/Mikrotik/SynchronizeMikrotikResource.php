<?php

namespace App\Jobs\Mikrotik;

use App\Models\InternetPlan;
use App\Models\NetworkAccess;
use App\Models\Voucher;
use App\Services\Mikrotik\MikrotikSyncService;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class SynchronizeMikrotikResource implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 180;
    public array $backoff = [10, 30, 90, 180];

    public function __construct(
        public readonly string $resourceType,
        public readonly string $resourceId,
    ) {
        $this->onQueue('network');
        $this->afterCommit();
    }

    public function handle(
        MikrotikSyncService $sync,
        TenantContext $tenantContext,
        CompanyContext $companyContext,
    ): void {
        $model = match ($this->resourceType) {
            'plan' => InternetPlan::query()->withoutGlobalScopes(['tenant', 'company'])->with(['tenant', 'company', 'networkProfile'])->find($this->resourceId),
            'access' => NetworkAccess::query()->withoutGlobalScopes(['tenant', 'company'])->with(['tenant', 'company', 'mikrotik', 'plan.networkProfile', 'profile'])->find($this->resourceId),
            'voucher' => Voucher::query()->withoutGlobalScopes(['tenant', 'company'])->with(['tenant', 'company', 'mikrotik', 'plan.networkProfile', 'profile'])->find($this->resourceId),
            default => throw new RuntimeException('Tipo de recurso de sincronização não autorizado.'),
        };

        if (! $model || ! $model->tenant || ! $model->company) {
            return;
        }

        $tenantContext->set($model->tenant);
        $companyContext->set($model->company);

        try {
            $result = match ($this->resourceType) {
                'plan' => $sync->syncPlan($model),
                'access' => $sync->syncAccess($model),
                'voucher' => $sync->syncVoucher($model),
            };

            if (! ($result['ok'] ?? false)) {
                throw new RuntimeException((string) ($result['error'] ?? 'Falha na sincronização SSH.'));
            }
        } finally {
            $companyContext->clear();
            $tenantContext->clear();
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Sincronização automática com MikroTik esgotou as tentativas.', [
            'resource_type' => $this->resourceType,
            'resource_id' => $this->resourceId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
