<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PaymentGatewayConfig;
use App\Services\Billing\BillingManager;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Throwable;

final class SynchronizeAsaasWebhooks extends Command
{
    protected $signature = 'asaas:webhooks:sync
        {--gateway= : UUID de um gateway específico}
        {--inactive : Inclui gateways inativos}';

    protected $description = 'Gera endpoints secretos por gateway e sincroniza as URLs dos webhooks no Asaas.';

    public function handle(
        BillingManager $billing,
        TenantContext $tenantContext,
        CompanyContext $companyContext,
    ): int {
        $query = PaymentGatewayConfig::withoutGlobalScopes()
            ->with(['tenant', 'company'])
            ->where('driver', 'asaas')
            ->orderBy('tenant_id')
            ->orderBy('company_id');

        if ($gatewayId = trim((string) $this->option('gateway'))) {
            $query->whereKey($gatewayId);
        }

        if (! $this->option('inactive')) {
            $query->where('active', true);
        }

        $gateways = $query->get();
        if ($gateways->isEmpty()) {
            $this->components->info('Nenhum gateway Asaas elegível foi encontrado.');

            return self::SUCCESS;
        }

        $failures = 0;

        foreach ($gateways as $gateway) {
            if (! $gateway->tenant || ! $gateway->company) {
                $failures++;
                $this->components->error("Gateway {$gateway->id} sem tenant ou empresa válida.");
                continue;
            }

            $tenantContext->set($gateway->tenant);
            $companyContext->set($gateway->company);

            try {
                $url = $gateway->webhookUrl();
                $result = $billing->forGateway($gateway)->synchronizeWebhook($gateway, $url);
                $gateway->mergeSettings([
                    'webhook_external_id' => (string) ($result['id'] ?? $gateway->setting('webhook_external_id', '')),
                    'webhook_url' => $url,
                    'webhook_synced_at' => now()->toIso8601String(),
                    'webhook_sync_status' => 'success',
                    'webhook_sync_message' => null,
                ]);

                $this->components->info("Webhook sincronizado: {$gateway->tenant->name} / {$gateway->company->legal_name} / {$gateway->name}");
            } catch (Throwable $exception) {
                $failures++;
                $gateway->mergeSettings([
                    'webhook_sync_status' => 'failed',
                    'webhook_sync_message' => mb_substr($exception->getMessage(), 0, 1000),
                ]);
                $this->components->error("Falha no gateway {$gateway->name}: {$exception->getMessage()}");
            } finally {
                $companyContext->clear();
                $tenantContext->clear();
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
