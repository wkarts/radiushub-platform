<?php

namespace App\Console\Commands;

use App\Models\MikrotikDevice;
use App\Models\Tenant;
use App\Services\Mikrotik\MikrotikSshService;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\TenantContext;
use Illuminate\Console\Command;

class NetworkHealthCheck extends Command
{
    protected $signature = 'network:health-check';
    protected $description = 'Testa por SSH Key os MikroTiks ativos.';

    public function handle(
        TenantContext $tenantContext,
        CompanyContext $companyContext,
        MikrotikSshService $service,
    ): int {
        Tenant::query()->where('active', true)->each(function (Tenant $tenant) use ($tenantContext, $companyContext, $service): void {
            $tenantContext->set($tenant);

            try {
                MikrotikDevice::query()
                    ->withoutGlobalScopes(['tenant', 'company'])
                    ->where('tenant_id', $tenant->id)
                    ->where('active', true)
                    ->with('company')
                    ->each(function (MikrotikDevice $device) use ($companyContext, $service): void {
                        $companyContext->set($device->company);
                        $service->test($device);
                    });
            } finally {
                $companyContext->clear();
                $tenantContext->clear();
            }
        });

        return self::SUCCESS;
    }
}
