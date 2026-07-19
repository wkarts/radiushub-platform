<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_customer_links', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subscriber_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('payment_gateway_config_id')->constrained()->cascadeOnDelete();
            $table->string('external_customer_id', 100);
            $table->json('payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'subscriber_id', 'payment_gateway_config_id'], 'billing_customer_links_owner_unique');
            $table->unique(['tenant_id', 'payment_gateway_config_id', 'external_customer_id'], 'billing_customer_links_external_unique');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignUuid('payment_gateway_config_id')->nullable()->constrained()->nullOnDelete();
            $table->string('billing_type', 30)->default('UNDEFINED');
            $table->string('gateway_status', 50)->nullable();
            $table->text('bank_slip_url')->nullable();
            $table->text('bank_slip_line')->nullable();
            $table->text('pix_qr_code')->nullable();
            $table->timestamp('pix_expiration_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->unique(['tenant_id', 'external_id'], 'invoices_gateway_external_unique');
            $table->index(['tenant_id', 'payment_gateway_config_id'], 'invoices_gateway_config_index');
            $table->index(['tenant_id', 'gateway_driver', 'gateway_status'], 'invoices_gateway_status_index');
        });

        Schema::create('payment_refunds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('status', 40)->default('pending');
            $table->string('description')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'external_id']);
            $table->index(['tenant_id', 'invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropForeign(['payment_gateway_config_id']);
            $table->dropUnique('invoices_gateway_external_unique');
            $table->dropIndex('invoices_gateway_config_index');
            $table->dropIndex('invoices_gateway_status_index');
            $table->dropColumn([
                'payment_gateway_config_id',
                'billing_type',
                'gateway_status',
                'bank_slip_url',
                'bank_slip_line',
                'pix_qr_code',
                'pix_expiration_at',
                'last_synced_at',
            ]);
        });

        Schema::dropIfExists('billing_customer_links');
    }
};
