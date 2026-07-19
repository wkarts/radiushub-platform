<?php

namespace App\Services\Platform;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

final class PlatformBootstrapService
{
    /**
     * Garante um Superadministrador utilizável e, quando habilitado, um contexto
     * inicial tenant/empresa. O processo é idempotente e não redefine a senha de
     * usuários existentes sem solicitação explícita.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function ensure(array $overrides = []): array
    {
        if (! (bool) config('platform.bootstrap.enabled', true)) {
            return ['enabled' => false];
        }

        foreach (['users', 'tenants', 'tenant_user', 'companies', 'roles', 'company_user'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("A tabela obrigatória {$table} ainda não existe. Execute as migrations antes do bootstrap.");
            }
        }

        return DB::transaction(function () use ($overrides): array {
            $admin = $this->ensureAdmin($overrides);
            $tenant = $this->ensureTenant();
            $company = $tenant ? $this->ensureCompany($tenant) : null;

            if ($tenant) {
                $this->attachTenantAdmin($tenant, $admin);
            }

            if ($tenant && $company) {
                $this->attachCompanyAdmin($company, $admin, true);
            }

            if ((bool) config('platform.bootstrap.attach_all_super_admins', true) && $tenant) {
                User::query()
                    ->where('is_super_admin', true)
                    ->where('active', true)
                    ->each(function (User $superAdmin) use ($tenant, $company, $admin): void {
                        $this->attachTenantAdmin($tenant, $superAdmin);

                        if ($company) {
                            $this->attachCompanyAdmin($company, $superAdmin, $superAdmin->is($admin));
                        }
                    });
            }

            $this->audit($admin, $tenant, $company);

            return [
                'enabled' => true,
                'admin' => $admin,
                'tenant' => $tenant,
                'company' => $company,
                'super_admins' => User::query()->where('is_super_admin', true)->where('active', true)->count(),
            ];
        }, 3);
    }

    /** @param array<string, mixed> $overrides */
    private function ensureAdmin(array $overrides): User
    {
        $email = mb_strtolower(trim((string) ($overrides['admin_email'] ?? config('platform.bootstrap.admin.email'))));
        $login = mb_strtolower(trim((string) ($overrides['admin_login'] ?? config('platform.bootstrap.admin.login'))));
        $name = trim((string) ($overrides['admin_name'] ?? config('platform.bootstrap.admin.name')));
        $password = (string) ($overrides['admin_password'] ?? config('platform.bootstrap.admin.password'));
        $forcePassword = (bool) ($overrides['force_password'] ?? config('platform.bootstrap.admin.force_password', false));
        $mustChangePassword = (bool) config('platform.bootstrap.admin.must_change_password', true);

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('SEED_ADMIN_EMAIL deve conter um e-mail válido.');
        }

        if ($login === '' || ! preg_match('/^[a-z0-9._-]{3,80}$/', $login)) {
            throw new RuntimeException('SEED_ADMIN_LOGIN deve possuir de 3 a 80 caracteres: letras, números, ponto, hífen ou sublinhado.');
        }

        $byEmail = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        $byLogin = User::query()->whereRaw('LOWER(login) = ?', [$login])->first();

        if ($byEmail && $byLogin && ! $byEmail->is($byLogin)) {
            throw new RuntimeException('SEED_ADMIN_EMAIL e SEED_ADMIN_LOGIN pertencem a usuários diferentes. Corrija as variáveis antes de continuar.');
        }

        $admin = $byEmail ?? $byLogin ?? new User();
        $created = ! $admin->exists;

        if (($created || $forcePassword) && ($password === '' || mb_strlen($password) < 10)) {
            throw new RuntimeException('SEED_ADMIN_PASSWORD deve possuir ao menos 10 caracteres ao criar ou redefinir a senha do Superadministrador.');
        }

        $admin->name = $name !== '' ? $name : 'Administrador';
        $admin->email = $email;
        $admin->login = $login;
        $admin->is_super_admin = true;
        $admin->active = true;
        $admin->email_verified_at ??= now();

        if ($created || $forcePassword) {
            $admin->password = Hash::make($password);
            $admin->must_change_password = $mustChangePassword;
        }

        $admin->save();

        return $admin;
    }

    private function ensureTenant(): ?Tenant
    {
        if (! (bool) config('platform.bootstrap.tenant.enabled', true)) {
            return null;
        }

        $slug = Str::slug((string) config('platform.bootstrap.tenant.slug', 'principal')) ?: 'principal';
        $tenant = Tenant::query()->where('slug', $slug)->first();

        if (! $tenant && (bool) config('platform.bootstrap.prefer_existing_context', true)) {
            $tenant = Tenant::query()->where('active', true)->orderBy('created_at')->first();
        }

        if ($tenant) {
            return $tenant;
        }

        return Tenant::query()->create([
            'name' => (string) config('platform.bootstrap.tenant.name', 'RadiusHub Principal'),
            'slug' => $slug,
            'document' => $this->nullableString(config('platform.bootstrap.tenant.document')),
            'email' => $this->nullableString(config('platform.bootstrap.tenant.email')),
            'phone' => $this->nullableString(config('platform.bootstrap.tenant.phone')),
            'timezone' => (string) config('platform.bootstrap.tenant.timezone', 'America/Bahia'),
            'subscription_plan' => (string) config('platform.bootstrap.tenant.subscription_plan', 'platform'),
            'usage_limits' => [
                'companies' => 100,
                'users' => 1000,
                'mikrotiks' => 1000,
                'subscribers' => 100000,
                'accesses' => 100000,
                'vouchers' => 1000000,
            ],
            'status' => 'active',
            'active' => true,
        ]);
    }

    private function ensureCompany(Tenant $tenant): ?Company
    {
        if (! (bool) config('platform.bootstrap.company.enabled', true)) {
            return null;
        }

        $document = $this->nullableString(config('platform.bootstrap.company.document'));
        $legalName = trim((string) config('platform.bootstrap.company.legal_name', 'Empresa Principal')) ?: 'Empresa Principal';
        $query = Company::withoutGlobalScopes()->where('tenant_id', $tenant->id);
        $company = null;

        if ($document !== null) {
            $company = (clone $query)->where('document', $document)->first();
        }

        $company ??= (clone $query)->where('legal_name', $legalName)->first();

        if (! $company && (bool) config('platform.bootstrap.prefer_existing_context', true)) {
            $company = (clone $query)->where('active', true)->orderBy('created_at')->first();
        }

        if ($company) {
            return $company;
        }

        return Company::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'legal_name' => $legalName,
            'trade_name' => $this->nullableString(config('platform.bootstrap.company.trade_name')),
            'document' => $document,
            'email' => $this->nullableString(config('platform.bootstrap.company.email')),
            'phone' => $this->nullableString(config('platform.bootstrap.company.phone')),
            'subscription_plan' => (string) config('platform.bootstrap.company.subscription_plan', 'platform'),
            'usage_limits' => [
                'users' => 1000,
                'mikrotiks' => 1000,
                'subscribers' => 100000,
                'accesses' => 100000,
                'vouchers' => 1000000,
            ],
            'status' => 'active',
            'active' => true,
        ]);
    }

    private function attachTenantAdmin(Tenant $tenant, User $user): void
    {
        $tenant->users()->syncWithoutDetaching([
            $user->id => ['role' => 'tenant_admin'],
        ]);
    }

    private function attachCompanyAdmin(Company $company, User $user, bool $primary): void
    {
        $role = Role::query()
            ->withoutGlobalScopes()
            ->whereNull('tenant_id')
            ->where('slug', 'company_admin')
            ->where('active', true)
            ->first();

        if (! $role) {
            throw new RuntimeException('O papel de sistema company_admin não existe. Execute RolePermissionSeeder antes do bootstrap.');
        }

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

    private function audit(User $admin, ?Tenant $tenant, ?Company $company): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        AuditLog::query()->firstOrCreate(
            [
                'request_id' => 'platform-bootstrap',
                'action' => 'platform.bootstrap',
                'auditable_type' => User::class,
                'auditable_id' => $admin->id,
            ],
            [
                'tenant_id' => $tenant?->id,
                'company_id' => $company?->id,
                'user_id' => $admin->id,
                'result' => 'success',
                'new_values' => [
                    'super_admin' => true,
                    'tenant_id' => $tenant?->id,
                    'company_id' => $company?->id,
                ],
                'metadata' => ['source' => 'bootstrap', 'version' => config('app.version')],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'RadiusHub Platform Bootstrap',
                'created_at' => now(),
            ],
        );
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
