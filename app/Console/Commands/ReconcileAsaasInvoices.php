<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\Billing\InvoiceService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ReconcileAsaasInvoices extends Command
{
    protected $signature = 'billing:reconcile-asaas {--tenant=} {--limit=100}';
    protected $description = 'Reconcilia cobranças Asaas pendentes/vencidas como fallback dos webhooks.';

    public function handle(TenantContext $context, InvoiceService $service): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $tenants = Tenant::query()->where('active', true);

        if ($tenantId = $this->option('tenant')) {
            $tenants->whereKey((string) $tenantId);
        }

        $tenants->each(function (Tenant $tenant) use ($context, $service, $limit): void {
            $context->set($tenant);

            try {
                Invoice::query()
                    ->where('gateway_driver', 'asaas')
                    ->whereNotNull('external_id')
                    ->whereIn('status', ['pending', 'overdue'])
                    ->orderBy('last_synced_at')
                    ->limit($limit)
                    ->get()
                    ->each(function (Invoice $invoice) use ($service): void {
                        try {
                            $service->synchronize($invoice);
                        } catch (Throwable $exception) {
                            Log::warning('Falha ao reconciliar cobrança Asaas.', [
                                'invoice_id' => $invoice->id,
                                'error' => $exception->getMessage(),
                            ]);
                        }
                    });
            } finally {
                $context->clear();
            }
        });

        $this->info('Reconciliação Asaas concluída.');

        return self::SUCCESS;
    }
}
