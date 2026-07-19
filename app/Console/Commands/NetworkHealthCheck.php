<?php

namespace App\Console\Commands;

use App\Models\MikrotikDevice;
use App\Models\Tenant;
use App\Services\Mikrotik\MikrotikService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Console\Command;

class NetworkHealthCheck extends Command
{
    protected $signature = 'network:health-check';
    protected $description = 'Testa os MikroTiks ativos.';

    public function handle(TenantContext $context, MikrotikService $service): int
    {
        Tenant::query()->where('active', true)->each(function (Tenant $tenant) use ($context, $service): void {
            $context->set($tenant);

            try {
                MikrotikDevice::query()
                    ->where('active', true)
                    ->each(fn (MikrotikDevice $device) => $service->test($device));
            } finally {
                $context->clear();
            }
        });

        return self::SUCCESS;
    }
}
