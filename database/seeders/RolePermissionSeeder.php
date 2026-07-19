<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            'dashboard.view' => ['Dashboard', 'dashboard'],
            'companies.view' => ['Consultar empresas', 'companies'],
            'companies.manage' => ['Gerenciar empresas', 'companies'],
            'users.view' => ['Consultar usuários', 'users'],
            'users.manage' => ['Gerenciar usuários', 'users'],
            'roles.view' => ['Consultar perfis e permissões', 'roles'],
            'roles.manage' => ['Gerenciar perfis e permissões', 'roles'],
            'subscribers.view' => ['Consultar clientes', 'subscribers'],
            'subscribers.manage' => ['Gerenciar clientes', 'subscribers'],
            'contracts.view' => ['Consultar contratos', 'contracts'],
            'contracts.manage' => ['Gerenciar contratos', 'contracts'],
            'plans.view' => ['Consultar planos', 'plans'],
            'plans.manage' => ['Gerenciar planos', 'plans'],
            'profiles.view' => ['Consultar perfis de rede', 'profiles'],
            'profiles.manage' => ['Gerenciar perfis de rede', 'profiles'],
            'mikrotiks.view' => ['Consultar MikroTiks', 'mikrotiks'],
            'mikrotiks.manage' => ['Gerenciar MikroTiks', 'mikrotiks'],
            'mikrotiks.execute' => ['Executar comandos controlados', 'mikrotiks'],
            'accesses.view' => ['Consultar acessos', 'accesses'],
            'accesses.manage' => ['Gerenciar acessos', 'accesses'],
            'vouchers.view' => ['Consultar vouchers', 'vouchers'],
            'vouchers.manage' => ['Gerenciar vouchers', 'vouchers'],
            'vouchers.export' => ['Exportar e imprimir credenciais de vouchers', 'vouchers'],
            'sessions.view' => ['Consultar sessões', 'sessions'],
            'sessions.manage' => ['Derrubar e alterar sessões', 'sessions'],
            'invoices.view' => ['Consultar financeiro', 'invoices'],
            'invoices.manage' => ['Gerenciar financeiro', 'invoices'],
            'gateways.view' => ['Consultar gateways', 'gateways'],
            'gateways.manage' => ['Gerenciar gateways', 'gateways'],
            'audit.view' => ['Consultar auditoria', 'audit'],
            'settings.manage' => ['Gerenciar configurações', 'settings'],
            'system.view' => ['Consultar saúde do sistema', 'system'],
        ];

        foreach ($definitions as $slug => [$name, $module]) {
            Permission::query()->firstOrCreate(['slug' => $slug], ['name' => $name, 'module' => $module]);
        }

        $all = array_keys($definitions);
        $roleMap = [
            'company_admin' => $all,
            'operator' => [
                'dashboard.view', 'subscribers.view', 'subscribers.manage', 'contracts.view',
                'accesses.view', 'accesses.manage', 'vouchers.view', 'vouchers.manage', 'vouchers.export',
                'sessions.view', 'sessions.manage',
            ],
            'attendant' => [
                'dashboard.view', 'subscribers.view', 'subscribers.manage', 'contracts.view',
                'vouchers.view', 'vouchers.manage', 'sessions.view',
            ],
            'technician' => [
                'dashboard.view', 'plans.view', 'profiles.view', 'mikrotiks.view',
                'mikrotiks.manage', 'mikrotiks.execute', 'accesses.view', 'accesses.manage',
                'sessions.view', 'sessions.manage', 'audit.view',
            ],
            'billing' => [
                'dashboard.view', 'subscribers.view', 'contracts.view', 'contracts.manage',
                'invoices.view', 'invoices.manage', 'gateways.view', 'gateways.manage',
            ],
            'viewer' => array_values(array_filter($all, fn (string $slug): bool => str_ends_with($slug, '.view'))),
        ];

        foreach ($roleMap as $slug => $permissionSlugs) {
            $role = Role::query()->firstOrCreate(
                ['tenant_id' => null, 'slug' => $slug],
                ['name' => match ($slug) {
                    'company_admin' => 'Administrador da empresa',
                    'operator' => 'Operador',
                    'attendant' => 'Atendente',
                    'technician' => 'Técnico',
                    'billing' => 'Financeiro',
                    default => 'Consulta',
                }, 'scope' => 'company', 'is_system' => true, 'active' => true]
            );

            $role->permissions()->sync(Permission::query()->whereIn('slug', $permissionSlugs)->pluck('id'));
        }

        $adminRole = Role::query()->whereNull('tenant_id')->where('slug', 'company_admin')->first();

        if ($adminRole) {
            foreach (Tenant::query()->with(['users', 'companies'])->get() as $tenant) {
                $company = $tenant->companies->first();
                if (! $company) continue;

                foreach ($tenant->users as $user) {
                    // Backfill legado somente para administradores do tenant. Vincular
                    // todos os membros como company_admin violaria o isolamento RBAC.
                    if (($user->pivot?->role ?? null) !== 'tenant_admin') {
                        continue;
                    }

                    DB::table('company_user')->updateOrInsert(
                        ['company_id' => $company->id, 'user_id' => $user->id],
                        [
                            'role_id' => $adminRole->id,
                            'is_primary' => true,
                            'active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }
    }
}
