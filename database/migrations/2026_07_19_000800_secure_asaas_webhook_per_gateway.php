<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('payment_gateway_configs', 'webhook_public_token')) {
            Schema::table('payment_gateway_configs', function (Blueprint $table): void {
                $table->longText('webhook_public_token')->nullable();
            });
        }

        if (! Schema::hasColumn('payment_gateway_configs', 'webhook_public_token_hash')) {
            Schema::table('payment_gateway_configs', function (Blueprint $table): void {
                $table->string('webhook_public_token_hash', 64)->nullable();
            });
        }

        foreach (DB::table('payment_gateway_configs')
            ->whereNull('webhook_public_token_hash')
            ->orderBy('id')
            ->get(['id']) as $gateway) {
            $token = bin2hex(random_bytes(48));

            DB::table('payment_gateway_configs')
                ->where('id', $gateway->id)
                ->update([
                    'webhook_public_token' => Crypt::encryptString($token),
                    'webhook_public_token_hash' => hash('sha256', $token),
                ]);
        }

        if (! $this->indexExists('payment_gateway_configs', 'gateway_webhook_public_token_hash_unique')) {
            Schema::table('payment_gateway_configs', function (Blueprint $table): void {
                $table->unique('webhook_public_token_hash', 'gateway_webhook_public_token_hash_unique');
            });
        }

        if (! Schema::hasColumn('webhook_events', 'company_id')) {
            Schema::table('webhook_events', function (Blueprint $table): void {
                $table->foreignUuid('company_id')->nullable()->constrained('companies')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('webhook_events', 'payment_gateway_config_id')) {
            Schema::table('webhook_events', function (Blueprint $table): void {
                $table->foreignUuid('payment_gateway_config_id')->nullable()->constrained()->nullOnDelete();
            });
        }

        // Eventos legados só são associados quando existe exatamente um gateway Asaas no tenant.
        foreach (DB::table('webhook_events')
            ->whereNull('payment_gateway_config_id')
            ->orderBy('created_at')
            ->get(['id', 'tenant_id']) as $event) {
            $gateways = DB::table('payment_gateway_configs')
                ->where('tenant_id', $event->tenant_id)
                ->where('driver', 'asaas')
                ->get(['id', 'company_id']);

            if ($gateways->count() !== 1) {
                continue;
            }

            $gateway = $gateways->first();
            DB::table('webhook_events')->where('id', $event->id)->update([
                'company_id' => $gateway->company_id,
                'payment_gateway_config_id' => $gateway->id,
            ]);
        }

        // No MySQL, o índice único legado pode ser utilizado implicitamente pela FK tenant_id.
        // O índice substituto deve existir em uma instrução anterior à remoção do índice antigo.
        if (! $this->indexExists('webhook_events', 'webhook_events_tenant_company_status_index')) {
            Schema::table('webhook_events', function (Blueprint $table): void {
                $table->index(
                    ['tenant_id', 'company_id', 'status'],
                    'webhook_events_tenant_company_status_index',
                );
            });
        }

        if ($this->indexExists('webhook_events', 'webhook_events_tenant_id_provider_external_event_id_unique')) {
            Schema::table('webhook_events', function (Blueprint $table): void {
                $table->dropUnique('webhook_events_tenant_id_provider_external_event_id_unique');
            });
        }

        if (! $this->indexExists('webhook_events', 'webhook_events_gateway_provider_event_unique')) {
            Schema::table('webhook_events', function (Blueprint $table): void {
                $table->unique(
                    ['payment_gateway_config_id', 'provider', 'external_event_id'],
                    'webhook_events_gateway_provider_event_unique',
                );
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('webhook_events', 'webhook_events_gateway_provider_event_unique')) {
            Schema::table('webhook_events', function (Blueprint $table): void {
                $table->dropUnique('webhook_events_gateway_provider_event_unique');
            });
        }

        if (! $this->indexExists('webhook_events', 'webhook_events_tenant_id_provider_external_event_id_unique')) {
            Schema::table('webhook_events', function (Blueprint $table): void {
                $table->unique(
                    ['tenant_id', 'provider', 'external_event_id'],
                    'webhook_events_tenant_id_provider_external_event_id_unique',
                );
            });
        }

        if ($this->indexExists('webhook_events', 'webhook_events_tenant_company_status_index')) {
            Schema::table('webhook_events', function (Blueprint $table): void {
                $table->dropIndex('webhook_events_tenant_company_status_index');
            });
        }

        if (Schema::hasColumn('webhook_events', 'payment_gateway_config_id')) {
            Schema::table('webhook_events', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('payment_gateway_config_id');
            });
        }

        if (Schema::hasColumn('webhook_events', 'company_id')) {
            Schema::table('webhook_events', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('company_id');
            });
        }

        if ($this->indexExists('payment_gateway_configs', 'gateway_webhook_public_token_hash_unique')) {
            Schema::table('payment_gateway_configs', function (Blueprint $table): void {
                $table->dropUnique('gateway_webhook_public_token_hash_unique');
            });
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('payment_gateway_configs', 'webhook_public_token') ? 'webhook_public_token' : null,
            Schema::hasColumn('payment_gateway_configs', 'webhook_public_token_hash') ? 'webhook_public_token_hash' : null,
        ]));

        if ($columns !== []) {
            Schema::table('payment_gateway_configs', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }
};
