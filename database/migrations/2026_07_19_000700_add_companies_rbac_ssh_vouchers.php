<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Recuperação idempotente para MySQL: DDL pode ter sido confirmado antes de uma
        // falha ao remover índices. Nesse cenário, a tabela companies já existe e basta
        // concluir a reconciliação de índices, sem tentar recriar colunas e tabelas.
        if (Schema::hasTable('companies')) {
            if (! $this->partialMigrationCanBeResumed()) {
                throw new RuntimeException(
                    'A migration multiempresa foi iniciada parcialmente, mas a estrutura necessária não está completa. ' .
                    'Restaure o backup anterior ao upgrade ou conclua a estrutura antes de executar migrate novamente.'
                );
            }

            $this->reconcileCompanyScopedIndexes();

            return;
        }
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('subscription_plan', 80)->nullable();
            $table->json('usage_limits')->nullable();
            $table->string('status', 20)->default('active');
        });

        Schema::create('companies', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('legal_name', 180);
            $table->string('trade_name', 180)->nullable();
            $table->string('document', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('zip_code', 12)->nullable();
            $table->string('street', 180)->nullable();
            $table->string('number', 30)->nullable();
            $table->string('complement', 100)->nullable();
            $table->string('district', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 2)->nullable();
            $table->string('subscription_plan', 80)->nullable();
            $table->json('usage_limits')->nullable();
            $table->string('status', 20)->default('active');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'document']);
            $table->index(['tenant_id', 'active']);
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('slug', 80);
            $table->string('scope', 20)->default('company');
            $table->boolean('is_system')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->string('module', 80);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table): void {
            $table->foreignUuid('role_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('company_user', function (Blueprint $table): void {
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('role_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->primary(['company_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('login', 80)->nullable()->unique();
            $table->boolean('must_change_password')->default(false);
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
        });

        Schema::create('network_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('service_type', 20)->default('both');
            $table->string('rate_limit', 120)->nullable();
            $table->unsignedBigInteger('data_limit_bytes')->nullable();
            $table->unsignedInteger('usage_time_limit_seconds')->nullable();
            $table->unsignedInteger('session_timeout_seconds')->nullable();
            $table->unsignedInteger('idle_timeout_seconds')->nullable();
            $table->unsignedInteger('max_devices')->default(1);
            $table->json('radius_attributes')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'company_id', 'name']);
        });

        Schema::create('voucher_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 140);
            $table->unsignedInteger('quantity');
            $table->json('settings');
            $table->timestamps();
        });

        Schema::create('vouchers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('voucher_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('mikrotik_device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('internet_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('network_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 120);
            $table->text('password_ciphertext');
            $table->string('prefix', 30)->nullable();
            $table->string('suffix', 30)->nullable();
            $table->unsignedSmallInteger('code_length')->default(8);
            $table->string('speed_limit', 120)->nullable();
            $table->unsignedBigInteger('data_limit_bytes')->nullable();
            $table->unsignedInteger('usage_time_limit_seconds')->nullable();
            $table->unsignedInteger('max_devices')->default(1);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('validity_mode', 20)->default('fixed');
            $table->unsignedInteger('validity_duration_minutes')->nullable();
            $table->unsignedInteger('session_timeout_seconds')->nullable();
            $table->string('status', 20)->default('available');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('first_access_at')->nullable();
            $table->timestamp('last_access_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('device_identifier', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'code']);
            $table->index(['company_id', 'status', 'expires_at']);
        });

        Schema::create('mikrotik_connection_logs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignUuid('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('mikrotik_device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('operation', 80);
            $table->string('result', 20);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('remote_address', 255)->nullable();
            $table->string('fingerprint', 255)->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['mikrotik_device_id', 'created_at']);
        });

        Schema::create('mikrotik_command_logs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignUuid('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('mikrotik_device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('command_key', 100);
            $table->text('command_preview');
            $table->string('result', 20);
            $table->integer('exit_status')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->longText('output_excerpt')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['mikrotik_device_id', 'created_at']);
        });

        $companyTables = [
            'subscribers', 'internet_plans', 'mikrotik_devices', 'network_accesses',
            'service_contracts', 'invoices', 'payment_gateway_configs',
            'radius_auth_attempts', 'radius_accounting', 'coa_requests', 'audit_logs',
        ];

        foreach ($companyTables as $name) {
            if (Schema::hasTable($name) && ! Schema::hasColumn($name, 'company_id')) {
                Schema::table($name, function (Blueprint $table): void {
                    $table->foreignUuid('company_id')->nullable()->constrained('companies')->nullOnDelete();
                });
            }
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('result', 20)->default('success');
            $table->json('metadata')->nullable();
            $table->string('request_id', 80)->nullable()->index();
        });

        Schema::table('internet_plans', function (Blueprint $table): void {
            $table->foreignUuid('network_profile_id')->nullable()->constrained('network_profiles')->nullOnDelete();
        });

        Schema::table('network_accesses', function (Blueprint $table): void {
            $table->foreignUuid('network_profile_id')->nullable()->constrained('network_profiles')->nullOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->unsignedInteger('connection_limit')->nullable();
        });

        Schema::table('mikrotik_devices', function (Blueprint $table): void {
            $table->string('connection_method', 20)->default('ssh');
            $table->unsignedInteger('ssh_port')->default(22);
            $table->string('ssh_username', 120)->nullable();
            $table->longText('ssh_private_key_ciphertext')->nullable();
            $table->longText('ssh_public_key')->nullable();
            $table->text('ssh_passphrase_ciphertext')->nullable();
            $table->text('ssh_password_ciphertext')->nullable();
            $table->boolean('ssh_password_fallback_enabled')->default(false);
            $table->string('ssh_host_fingerprint', 255)->nullable();
            $table->unsignedInteger('ssh_connection_timeout')->default(10);
            $table->unsignedInteger('ssh_command_timeout')->default(30);
            $table->string('router_identity', 180)->nullable();
            $table->string('router_model', 120)->nullable();
            $table->string('routerboard_name', 120)->nullable();
            $table->string('routeros_version', 80)->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
        });

        Schema::table('mikrotik_devices', function (Blueprint $table): void {
            $table->string('api_username', 120)->nullable()->change();
            $table->text('api_password')->nullable()->change();
        });

        DB::table('tenants')->where('active', false)->update(['status' => 'suspended']);

        // Instalações existentes recebem uma empresa padrão, sem perder registros.
        foreach (DB::table('tenants')->orderBy('created_at')->get() as $tenant) {
            $companyId = (string) Str::uuid();

            DB::table('companies')->insert([
                'id' => $companyId,
                'tenant_id' => $tenant->id,
                'legal_name' => $tenant->name,
                'trade_name' => $tenant->name,
                'document' => $tenant->document,
                'email' => $tenant->email,
                'phone' => $tenant->phone,
                'status' => $tenant->active ? 'active' : 'suspended',
                'active' => (bool) $tenant->active,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($companyTables as $name) {
                if (Schema::hasTable($name)) {
                    DB::table($name)
                        ->where('tenant_id', $tenant->id)
                        ->whereNull('company_id')
                        ->update(['company_id' => $companyId]);
                }
            }
        }

        $this->reconcileCompanyScopedIndexes();
    }

    private function partialMigrationCanBeResumed(): bool
    {
        foreach (['companies', 'roles', 'permissions', 'company_user', 'network_profiles', 'voucher_batches', 'vouchers', 'mikrotik_connection_logs', 'mikrotik_command_logs'] as $table) {
            if (! Schema::hasTable($table)) {
                return false;
            }
        }

        foreach (['subscribers', 'internet_plans', 'mikrotik_devices', 'network_accesses', 'service_contracts', 'invoices', 'payment_gateway_configs', 'audit_logs'] as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'company_id')) {
                return false;
            }
        }

        return Schema::hasColumn('mikrotik_devices', 'ssh_private_key_ciphertext')
            && Schema::hasColumn('network_accesses', 'network_profile_id')
            && Schema::hasColumn('internet_plans', 'network_profile_id');
    }

    private function reconcileCompanyScopedIndexes(): void
    {
        $definitions = [
            'subscribers' => [
                ['new', 'subscribers_company_document_unique', ['tenant_id', 'company_id', 'document']],
                ['old', 'subscribers_tenant_id_document_unique'],
            ],
            'internet_plans' => [
                ['new', 'internet_plans_company_name_unique', ['tenant_id', 'company_id', 'name']],
                ['old', 'internet_plans_tenant_id_name_unique'],
            ],
            'mikrotik_devices' => [
                ['new', 'mikrotik_devices_company_name_unique', ['tenant_id', 'company_id', 'name']],
                ['old', 'mikrotik_devices_tenant_id_name_unique'],
            ],
            'network_accesses' => [
                ['new', 'network_accesses_company_username_unique', ['tenant_id', 'company_id', 'username']],
                ['old', 'network_accesses_tenant_id_username_unique'],
            ],
            'service_contracts' => [
                ['new', 'service_contracts_company_number_unique', ['tenant_id', 'company_id', 'number']],
                ['old', 'service_contracts_tenant_id_number_unique'],
            ],
            'invoices' => [
                ['new', 'invoices_company_number_unique', ['tenant_id', 'company_id', 'number']],
                ['new', 'invoices_company_contract_issue_unique', ['tenant_id', 'company_id', 'service_contract_id', 'issue_date']],
                ['old', 'invoices_tenant_id_number_unique'],
                ['old', 'invoices_tenant_id_service_contract_id_issue_date_unique'],
            ],
            'payment_gateway_configs' => [
                ['new', 'gateway_configs_company_driver_unique', ['tenant_id', 'company_id', 'driver']],
                ['old', 'payment_gateway_configs_tenant_id_driver_unique'],
            ],
            'vouchers' => [
                ['new', 'vouchers_company_code_unique', ['tenant_id', 'company_id', 'code']],
                ['old', 'vouchers_tenant_id_code_unique'],
            ],
        ];

        foreach ($definitions as $tableName => $operations) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($operations as $operation) {
                if ($operation[0] !== 'new' || $this->indexExists($tableName, $operation[1])) {
                    continue;
                }

                Schema::table($tableName, function (Blueprint $table) use ($operation): void {
                    $table->unique($operation[2], $operation[1]);
                });
            }

            foreach ($operations as $operation) {
                if ($operation[0] !== 'old' || ! $this->indexExists($tableName, $operation[1])) {
                    continue;
                }

                Schema::table($tableName, function (Blueprint $table) use ($operation): void {
                    $table->dropUnique($operation[1]);
                });
            }
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

    public function down(): void
    {
        // Restaura primeiro os índices antigos para manter as FKs válidas também no MySQL.
        Schema::table('subscribers', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'document'], 'subscribers_tenant_id_document_unique');
        });
        Schema::table('internet_plans', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'name'], 'internet_plans_tenant_id_name_unique');
        });
        Schema::table('mikrotik_devices', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'name'], 'mikrotik_devices_tenant_id_name_unique');
        });
        Schema::table('network_accesses', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'username'], 'network_accesses_tenant_id_username_unique');
        });
        Schema::table('service_contracts', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'number'], 'service_contracts_tenant_id_number_unique');
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'number'], 'invoices_tenant_id_number_unique');
            $table->unique(['tenant_id', 'service_contract_id', 'issue_date'], 'invoices_tenant_id_service_contract_id_issue_date_unique');
        });
        Schema::table('payment_gateway_configs', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'driver'], 'payment_gateway_configs_tenant_id_driver_unique');
        });
        Schema::table('vouchers', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'code'], 'vouchers_tenant_id_code_unique');
        });

        Schema::table('vouchers', function (Blueprint $table): void {
            $table->dropUnique('vouchers_company_code_unique');
        });
        Schema::table('payment_gateway_configs', function (Blueprint $table): void {
            $table->dropUnique('gateway_configs_company_driver_unique');
        });
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique('invoices_company_number_unique');
            $table->dropUnique('invoices_company_contract_issue_unique');
        });
        Schema::table('service_contracts', function (Blueprint $table): void {
            $table->dropUnique('service_contracts_company_number_unique');
        });
        Schema::table('network_accesses', function (Blueprint $table): void {
            $table->dropUnique('network_accesses_company_username_unique');
        });
        Schema::table('mikrotik_devices', function (Blueprint $table): void {
            $table->dropUnique('mikrotik_devices_company_name_unique');
        });
        Schema::table('internet_plans', function (Blueprint $table): void {
            $table->dropUnique('internet_plans_company_name_unique');
        });
        Schema::table('subscribers', function (Blueprint $table): void {
            $table->dropUnique('subscribers_company_document_unique');
        });

        Schema::table('mikrotik_devices', function (Blueprint $table): void {
            $table->dropColumn([
                'connection_method', 'ssh_port', 'ssh_username', 'ssh_private_key_ciphertext',
                'ssh_public_key', 'ssh_passphrase_ciphertext', 'ssh_password_ciphertext',
                'ssh_password_fallback_enabled', 'ssh_host_fingerprint', 'ssh_connection_timeout', 'ssh_command_timeout',
                'router_identity', 'router_model', 'routerboard_name', 'routeros_version',
                'last_connected_at', 'last_sync_at',
            ]);
        });
        Schema::table('network_accesses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('network_profile_id');
            $table->dropColumn(['starts_at', 'connection_limit']);
        });
        Schema::table('internet_plans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('network_profile_id');
        });
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex(['request_id']);
            $table->dropColumn(['result', 'metadata', 'request_id']);
        });

        foreach ([
            'subscribers', 'internet_plans', 'mikrotik_devices', 'network_accesses',
            'service_contracts', 'invoices', 'payment_gateway_configs',
            'radius_auth_attempts', 'radius_accounting', 'coa_requests', 'audit_logs',
        ] as $name) {
            if (Schema::hasTable($name) && Schema::hasColumn($name, 'company_id')) {
                Schema::table($name, function (Blueprint $table): void {
                    $table->dropConstrainedForeignId('company_id');
                });
            }
        }

        Schema::dropIfExists('mikrotik_command_logs');
        Schema::dropIfExists('mikrotik_connection_logs');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('voucher_batches');
        Schema::dropIfExists('network_profiles');
        Schema::dropIfExists('company_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('companies');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['login']);
            $table->dropColumn([
                'login', 'must_change_password', 'two_factor_secret', 'two_factor_recovery_codes',
                'two_factor_confirmed_at', 'last_login_at', 'last_login_ip',
            ]);
        });

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['subscription_plan', 'usage_limits', 'status']);
        });
    }
};
