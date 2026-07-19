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
        Schema::table('payment_gateway_configs', function (Blueprint $table): void {
            $table->longText('webhook_public_token')->nullable();
            $table->string('webhook_public_token_hash', 64)->nullable();
        });

        foreach (DB::table('payment_gateway_configs')->orderBy('id')->get(['id']) as $gateway) {
            $token = bin2hex(random_bytes(48));

            DB::table('payment_gateway_configs')
                ->where('id', $gateway->id)
                ->update([
                    'webhook_public_token' => Crypt::encryptString($token),
                    'webhook_public_token_hash' => hash('sha256', $token),
                ]);
        }

        Schema::table('payment_gateway_configs', function (Blueprint $table): void {
            $table->unique('webhook_public_token_hash', 'gateway_webhook_public_token_hash_unique');
        });

        Schema::table('webhook_events', function (Blueprint $table): void {
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignUuid('payment_gateway_config_id')->nullable()->constrained()->nullOnDelete();
        });

        // Eventos legados são associados automaticamente apenas quando a origem é inequívoca.
        foreach (DB::table('webhook_events')->orderBy('created_at')->get(['id', 'tenant_id']) as $event) {
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

        Schema::table('webhook_events', function (Blueprint $table): void {
            $table->dropUnique('webhook_events_tenant_id_provider_external_event_id_unique');
            $table->unique(
                ['payment_gateway_config_id', 'provider', 'external_event_id'],
                'webhook_events_gateway_provider_event_unique',
            );
            $table->index(
                ['tenant_id', 'company_id', 'status'],
                'webhook_events_company_status_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('webhook_events', function (Blueprint $table): void {
            $table->dropUnique('webhook_events_gateway_provider_event_unique');
            $table->dropIndex('webhook_events_company_status_index');
            $table->dropConstrainedForeignId('payment_gateway_config_id');
            $table->dropConstrainedForeignId('company_id');
            $table->unique(
                ['tenant_id', 'provider', 'external_event_id'],
                'webhook_events_tenant_id_provider_external_event_id_unique',
            );
        });

        Schema::table('payment_gateway_configs', function (Blueprint $table): void {
            $table->dropUnique('gateway_webhook_public_token_hash_unique');
            $table->dropColumn(['webhook_public_token', 'webhook_public_token_hash']);
        });
    }
};
