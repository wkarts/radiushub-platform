<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\InternetPlan;
use App\Models\Invoice;
use App\Models\MikrotikDevice;
use App\Models\NetworkAccess;
use App\Models\NetworkProfile;
use App\Models\PaymentGatewayConfig;
use App\Models\RadiusAccounting;
use App\Models\Role;
use App\Models\ServiceContract;
use App\Models\Subscriber;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use App\Services\Security\RadiusCredentialVault;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class PlaygroundSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('playground.enabled')) {
            $this->command?->warn('PLAYGROUND_MODE não está habilitado; dados de demonstração não foram criados.');

            return;
        }

        $this->call(RolePermissionSeeder::class);

        $tenant = Tenant::query()->updateOrCreate(
            ['slug' => (string) config('playground.seed.tenant_slug', 'playground')],
            [
                'name' => 'RadiusHub Playground',
                'document' => '99999999000100',
                'email' => 'tenant@playground.local',
                'phone' => '75999990000',
                'timezone' => 'America/Bahia',
                'subscription_plan' => 'playground',
                'usage_limits' => [
                    'companies' => 5,
                    'users' => 25,
                    'mikrotiks' => 10,
                    'subscribers' => 250,
                    'accesses' => 250,
                    'vouchers' => 10000,
                ],
                'status' => 'active',
                'active' => true,
            ],
        );

        $company = Company::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'document' => (string) config('playground.seed.company_document', '99999999000199'),
            ],
            [
                'legal_name' => 'RadiusHub Playground LTDA',
                'trade_name' => 'RadiusHub Playground',
                'email' => 'empresa@playground.local',
                'phone' => '75999990001',
                'zip_code' => '44000000',
                'street' => 'Avenida de Testes',
                'number' => '1400',
                'district' => 'Homologação',
                'city' => 'Feira de Santana',
                'state' => 'BA',
                'subscription_plan' => 'playground',
                'usage_limits' => [
                    'users' => 20,
                    'mikrotiks' => 5,
                    'subscribers' => 200,
                    'accesses' => 200,
                    'vouchers' => 5000,
                ],
                'status' => 'active',
                'active' => true,
            ],
        );

        $admin = $this->user(
            (string) config('playground.seed.admin_email'),
            'Administrador Playground',
            'playground.admin',
            (string) config('playground.seed.admin_password'),
            true,
        );
        $operator = $this->user(
            (string) config('playground.seed.operator_email'),
            'Operador Playground',
            'playground.operator',
            (string) config('playground.seed.operator_password'),
        );
        $technician = $this->user(
            (string) config('playground.seed.technician_email'),
            'Técnico Playground',
            'playground.technician',
            (string) config('playground.seed.technician_password'),
        );

        foreach ([$admin, $operator, $technician] as $user) {
            $tenant->users()->syncWithoutDetaching([$user->id => ['role' => $user->is_super_admin ? 'tenant_admin' : 'operator']]);
        }

        $this->attachCompanyRole($company, $admin, 'company_admin', true);
        $this->attachCompanyRole($company, $operator, 'operator');
        $this->attachCompanyRole($company, $technician, 'technician');

        app(TenantContext::class)->set($tenant);
        app(CompanyContext::class)->set($company);

        try {
            $profile = NetworkProfile::query()->updateOrCreate(
                ['name' => 'Playground 100M'],
                [
                    'service_type' => 'both',
                    'rate_limit' => '50M/100M',
                    'data_limit_bytes' => 50 * 1024 * 1024 * 1024,
                    'usage_time_limit_seconds' => 30 * 24 * 3600,
                    'session_timeout_seconds' => 8 * 3600,
                    'idle_timeout_seconds' => 900,
                    'max_devices' => 2,
                    'radius_attributes' => ['Mikrotik-Rate-Limit' => '50M/100M'],
                    'active' => true,
                ],
            );

            $plan = InternetPlan::query()->updateOrCreate(
                ['name' => 'Playground Fibra 100 Mega'],
                [
                    'network_profile_id' => $profile->id,
                    'service_type' => 'both',
                    'download_bps' => 100000000,
                    'upload_bps' => 50000000,
                    'rate_limit' => '50M/100M',
                    'session_timeout' => 28800,
                    'idle_timeout' => 900,
                    'simultaneous_use' => 2,
                    'address_pool' => 'playground-pool',
                    'monthly_price' => 99.90,
                    'active' => true,
                    'radius_reply_attributes' => ['Mikrotik-Rate-Limit' => '50M/100M'],
                ],
            );

            $radiusVault = app(RadiusCredentialVault::class);
            $device = MikrotikDevice::query()->updateOrCreate(
                ['name' => 'MikroTik CHR Simulado'],
                [
                    'site_name' => 'Playground',
                    'description' => 'Equipamento simulado. Nenhum comando é enviado para uma rede externa.',
                    'management_host' => 'playground-router',
                    'connection_method' => 'simulator',
                    'ssh_port' => 22,
                    'ssh_username' => 'radiushub',
                    'ssh_public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIRadiusHubPlaygroundOnly radiushub@playground',
                    'ssh_password_fallback_enabled' => false,
                    'ssh_connection_timeout' => 5,
                    'ssh_command_timeout' => 10,
                    'api_username' => null,
                    'api_password' => null,
                    'radius_source_ip' => (string) config('playground.seed.nas_ip_address'),
                    'radius_secret_ciphertext' => $radiusVault->encrypt('playground-radius-secret'),
                    'coa_port' => 3799,
                    'hotspot_enabled' => true,
                    'pppoe_enabled' => true,
                    'active' => true,
                    'status' => 'online',
                    'last_seen_at' => now(),
                    'last_connected_at' => now(),
                    'router_identity' => 'RadiusHub-Playground',
                    'router_model' => 'CHR-SIMULATOR',
                    'routerboard_name' => 'Cloud Hosted Router',
                    'routeros_version' => '7.16-playground',
                ],
            );

            $subscriber = Subscriber::query()->updateOrCreate(
                ['document' => '12345678909'],
                [
                    'type' => 'person',
                    'name' => 'Cliente Demonstração',
                    'email' => 'cliente@playground.local',
                    'phone' => '75999990002',
                    'whatsapp' => '75999990002',
                    'zip_code' => '44000000',
                    'street' => 'Rua do Cliente',
                    'number' => '100',
                    'district' => 'Centro',
                    'city' => 'Feira de Santana',
                    'state' => 'BA',
                    'notes' => 'Cadastro gerado para validação do playground.',
                    'status' => 'active',
                ],
            );

            $access = NetworkAccess::query()->updateOrCreate(
                ['username' => (string) config('playground.seed.network_username')],
                [
                    'subscriber_id' => $subscriber->id,
                    'internet_plan_id' => $plan->id,
                    'network_profile_id' => $profile->id,
                    'mikrotik_device_id' => $device->id,
                    'service_type' => 'both',
                    'password_ciphertext' => $radiusVault->encrypt((string) config('playground.seed.network_password')),
                    'caller_id' => '02:00:00:00:00:01',
                    'simultaneous_use' => 2,
                    'pool_name' => 'playground-pool',
                    'starts_at' => now()->subDays(30),
                    'expires_at' => now()->addYear(),
                    'connection_limit' => 2,
                    'status' => 'active',
                    'notes' => 'Acesso Hotspot/PPPoE simulado.',
                ],
            );

            $contract = ServiceContract::query()->updateOrCreate(
                ['number' => 'PG-0001'],
                [
                    'subscriber_id' => $subscriber->id,
                    'network_access_id' => $access->id,
                    'internet_plan_id' => $plan->id,
                    'amount' => 99.90,
                    'billing_day' => 10,
                    'grace_days' => 5,
                    'status' => 'active',
                    'started_at' => now()->subMonths(2)->toDateString(),
                    'next_invoice_at' => now()->addMonth()->startOfMonth()->addDays(9)->toDateString(),
                    'notes' => 'Contrato de demonstração.',
                ],
            );

            PaymentGatewayConfig::query()->updateOrCreate(
                ['driver' => 'manual'],
                [
                    'name' => 'Gateway manual do Playground',
                    'environment' => 'sandbox',
                    'active' => true,
                    'settings' => ['playground' => true],
                ],
            );

            Invoice::query()->updateOrCreate(
                ['number' => 'PG-FAT-0001'],
                [
                    'subscriber_id' => $subscriber->id,
                    'service_contract_id' => $contract->id,
                    'description' => 'Mensalidade de demonstração',
                    'issue_date' => now()->startOfMonth()->toDateString(),
                    'due_date' => now()->addDays(10)->toDateString(),
                    'amount' => 99.90,
                    'paid_amount' => 0,
                    'status' => 'pending',
                    'gateway_driver' => 'manual',
                    'billing_type' => 'UNDEFINED',
                    'metadata' => ['playground' => true],
                ],
            );

            $batch = VoucherBatch::query()->updateOrCreate(
                ['name' => 'Lote Playground'],
                [
                    'generated_by' => $admin->id,
                    'quantity' => 6,
                    'settings' => [
                        'alphabet' => 'readable',
                        'code_length' => 8,
                        'validity_mode' => 'first_access',
                        'validity_duration_minutes' => 1440,
                        'print_title' => 'Wi-Fi Playground',
                    ],
                ],
            );

            $voucherStates = ['available', 'available', 'active', 'used', 'expired', 'blocked'];
            foreach ($voucherStates as $index => $status) {
                $code = 'PLAY'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT);
                Voucher::query()->updateOrCreate(
                    ['code' => $code],
                    [
                        'voucher_batch_id' => $batch->id,
                        'mikrotik_device_id' => $device->id,
                        'internet_plan_id' => $plan->id,
                        'network_profile_id' => $profile->id,
                        'password_ciphertext' => $radiusVault->encrypt('Senha'.($index + 1).'@Play'),
                        'prefix' => 'PLAY',
                        'code_length' => 8,
                        'speed_limit' => '20M/50M',
                        'data_limit_bytes' => 5 * 1024 * 1024 * 1024,
                        'usage_time_limit_seconds' => 86400,
                        'max_devices' => 1,
                        'valid_from' => now()->subDay(),
                        'expires_at' => $status === 'expired' ? now()->subHour() : now()->addDays(30),
                        'validity_mode' => 'first_access',
                        'validity_duration_minutes' => 1440,
                        'session_timeout_seconds' => 14400,
                        'status' => $status,
                        'activated_at' => in_array($status, ['active', 'used'], true) ? now()->subHours(2) : null,
                        'first_access_at' => in_array($status, ['active', 'used'], true) ? now()->subHours(2) : null,
                        'last_access_at' => $status === 'used' ? now()->subHour() : null,
                        'used_at' => $status === 'used' ? now()->subHour() : null,
                        'blocked_at' => $status === 'blocked' ? now()->subMinutes(30) : null,
                        'device_identifier' => in_array($status, ['active', 'used'], true) ? '02:00:00:00:00:0'.($index + 2) : null,
                        'notes' => 'Voucher de demonstração '.$status.'.',
                    ],
                );
            }

            RadiusAccounting::query()->updateOrCreate(
                ['acct_session_id' => 'playground-session-001', 'nas_ip_address' => (string) config('playground.seed.nas_ip_address')],
                [
                    'network_access_id' => $access->id,
                    'mikrotik_device_id' => $device->id,
                    'username' => (string) config('playground.seed.network_username'),
                    'acct_unique_id' => 'playground-unique-001',
                    'nas_port_id' => 'ether2',
                    'nas_port_type' => 'Ethernet',
                    'acct_start_time' => now()->subMinutes(42),
                    'acct_update_time' => now(),
                    'acct_session_time' => 2520,
                    'acct_input_octets' => 128 * 1024 * 1024,
                    'acct_output_octets' => 512 * 1024 * 1024,
                    'called_station_id' => 'RadiusHub-Playground',
                    'calling_station_id' => '02:00:00:00:00:01',
                    'service_type' => 'Framed-User',
                    'framed_protocol' => 'PPP',
                    'framed_ip_address' => '10.10.10.10',
                ],
            );

            AuditLog::query()->firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'company_id' => $company->id,
                    'action' => 'playground.seeded',
                    'auditable_type' => Tenant::class,
                    'auditable_id' => $tenant->id,
                ],
                [
                    'user_id' => $admin->id,
                    'result' => 'success',
                    'new_values' => ['version' => config('app.version')],
                    'metadata' => ['playground' => true, 'simulator' => true],
                    'request_id' => 'playground-seeder',
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'RadiusHub Playground Seeder',
                    'created_at' => now(),
                ],
            );
        } finally {
            app(CompanyContext::class)->clear();
            app(TenantContext::class)->clear();
        }

        $this->command?->info('Playground criado/atualizado com sucesso.');
    }

    private function user(string $email, string $name, string $login, string $password, bool $superAdmin = false): User
    {
        return User::query()->updateOrCreate(
            ['email' => mb_strtolower($email)],
            [
                'name' => $name,
                'login' => $login,
                'password' => Hash::make($password),
                'is_super_admin' => $superAdmin,
                'active' => true,
                'must_change_password' => false,
                'email_verified_at' => now(),
            ],
        );
    }

    private function attachCompanyRole(Company $company, User $user, string $roleSlug, bool $primary = false): void
    {
        $role = Role::query()->withoutGlobalScopes()->whereNull('tenant_id')->where('slug', $roleSlug)->firstOrFail();

        DB::table('company_user')->updateOrInsert(
            ['company_id' => $company->id, 'user_id' => $user->id],
            [
                'role_id' => $role->id,
                'is_primary' => $primary,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
