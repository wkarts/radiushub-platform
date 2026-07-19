<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantUserPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_open_another_company(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Tenant', 'slug' => 'tenant', 'active' => true]);
        $allowed = Company::query()->create(['tenant_id' => $tenant->id, 'legal_name' => 'Permitida']);
        $blocked = Company::query()->create(['tenant_id' => $tenant->id, 'legal_name' => 'Bloqueada']);
        $permission = Permission::query()->create(['name' => 'Dashboard', 'slug' => 'dashboard.view', 'module' => 'dashboard']);
        $role = Role::query()->create(['name' => 'Consulta', 'slug' => 'consulta', 'scope' => 'company', 'active' => true]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => 'viewer']);
        $allowed->users()->attach($user->id, ['role_id' => $role->id, 'active' => true]);

        $this->actingAs($user)
            ->withSession([config('tenancy.session_key') => $tenant->id, config('tenancy.company_session_key') => $blocked->id])
            ->get(route('dashboard'))
            ->assertOk();

        $this->assertSame($allowed->id, session(config('tenancy.company_session_key')));
    }

    public function test_company_role_grants_only_linked_permissions(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Tenant', 'slug' => 'tenant']);
        $company = Company::query()->create(['tenant_id' => $tenant->id, 'legal_name' => 'Empresa']);
        $permission = Permission::query()->create(['name' => 'Ver vouchers', 'slug' => 'vouchers.view', 'module' => 'vouchers']);
        $role = Role::query()->create(['name' => 'Atendente', 'slug' => 'atendente', 'scope' => 'company', 'active' => true]);
        $role->permissions()->attach($permission);
        $user = User::factory()->create();
        $company->users()->attach($user->id, ['role_id' => $role->id, 'active' => true]);

        $this->assertTrue($user->hasPermission('vouchers.view', $tenant->id, $company->id));
        $this->assertFalse($user->hasPermission('mikrotiks.manage', $tenant->id, $company->id));
    }
}
