<?php

namespace App\Console\Commands;

use App\Enums\AccessStatus;
use App\Enums\ContractStatus;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\RadiusAccounting;
use App\Models\Tenant;
use App\Services\Mikrotik\MikrotikSessionControlService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SuspendOverdueContracts extends Command
{
    protected $signature = 'billing:suspend-overdue';
    protected $description = 'Suspende contratos com faturas vencidas além da tolerância.';

    public function handle(TenantContext $context, MikrotikSessionControlService $sessions): int
    {
        Tenant::query()->where('active', true)->each(function (Tenant $tenant) use ($context, $sessions): void {
            $context->set($tenant);

            try {
                Invoice::query()
                    ->whereIn('status', [InvoiceStatus::Pending, InvoiceStatus::Overdue])
                    ->whereDate('due_date', '<', today())
                    ->with(['contract.access'])
                    ->each(function (Invoice $invoice) use ($sessions): void {
                        $invoice->update(['status' => InvoiceStatus::Overdue]);
                        $contract = $invoice->contract;

                        if (! $contract || $invoice->due_date->copy()->addDays($contract->grace_days)->isFuture()) {
                            return;
                        }

                        $contract->update([
                            'status' => ContractStatus::Suspended,
                            'suspended_at' => now(),
                        ]);

                        $access = $contract->access;
                        $access?->update(['status' => AccessStatus::Suspended]);

                        if (! $access) {
                            return;
                        }

                        RadiusAccounting::query()
                            ->where('network_access_id', $access->id)
                            ->whereNull('acct_stop_time')
                            ->with('mikrotik')
                            ->each(function (RadiusAccounting $session) use ($sessions): void {
                                try {
                                    $sessions->disconnect($session);
                                } catch (Throwable $exception) {
                                    Log::warning('Falha ao desconectar sessão após suspensão financeira.', [
                                        'session_id' => $session->id,
                                        'error' => $exception->getMessage(),
                                    ]);
                                }
                            });
                    });
            } finally {
                $context->clear();
            }
        });

        return self::SUCCESS;
    }
}
