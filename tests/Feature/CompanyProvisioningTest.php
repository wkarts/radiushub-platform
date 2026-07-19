<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CompanyProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_administrator_can_create_company_and_its_initial_administrator(): void
    {
        Notification::fake();
        $this->seed(RolePermissionSeeder::class);

        $tenant = Tenant::query()->create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'status' => 'active',
            'active' => true,
        ]);
        $tenantAdmin = User::factory()->create(['is_super_admin' => false, 'active' => true]);
        $tenant->users()->attach($tenantAdmin->id, ['role' => 'tenant_admin']);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'company_admin')->firstOrFail();

        $response = $this->actingAs($tenantAdmin)
            ->withSession([config('tenancy.session_key') => $tenant->id])
            ->post(route('companies.store'), [
                'legal_name' => 'Empresa Operadora LTDA',
                'trade_name' => 'Empresa Operadora',
                'document' => '12345678000199',
                'status' => 'active',
                'active' => '1',
                'create_admin' => '1',
                'admin_name' => 'Administrador da Empresa',
                'admin_email' => 'empresa-admin@example.com',
                'admin_login' => 'empresa-admin',
                'admin_password' => 'SenhaInicial123',
                'admin_password_confirmation' => 'SenhaInicial123',
                'admin_role_id' => $role->id,
            ]);

        $response->assertSessionHasNoErrors();
        $company = Company::query()->where('tenant_id', $tenant->id)->where('document', '12345678000199')->firstOrFail();
        $administrator = User::query()->where('email', 'empresa-admin@example.com')->firstOrFail();

        $this->assertTrue($administrator->must_change_password);
        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id' => $administrator->id,
            'role_id' => $role->id,
            'active' => true,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'company.created', 'company_id' => $company->id]);
    }
    public function test_tenant_administrator_cannot_link_user_from_another_tenant_by_email(): void
    {
        Notification::fake();
        $this->seed(RolePermissionSeeder::class);

        $tenantA = Tenant::query()->create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'active' => true]);
        $tenantB = Tenant::query()->create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'active' => true]);
        $tenantAdmin = User::factory()->create(['active' => true]);
        $externalUser = User::factory()->create(['email' => 'external@example.com', 'active' => true]);
        $tenantA->users()->attach($tenantAdmin->id, ['role' => 'tenant_admin']);
        $tenantB->users()->attach($externalUser->id, ['role' => 'operator']);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'company_admin')->firstOrFail();

        $this->actingAs($tenantAdmin)
            ->withSession([config('tenancy.session_key') => $tenantA->id])
            ->post(route('companies.store'), [
                'legal_name' => 'Empresa Isolada',
                'status' => 'active',
                'active' => '1',
                'create_admin' => '1',
                'admin_name' => 'Usuário Externo',
                'admin_email' => 'external@example.com',
                'admin_password' => 'SenhaInicial123',
                'admin_password_confirmation' => 'SenhaInicial123',
                'admin_role_id' => $role->id,
            ])
            ->assertSessionHasErrors('admin_email');

        $this->assertDatabaseMissing('companies', [
            'tenant_id' => $tenantA->id,
            'legal_name' => 'Empresa Isolada',
        ]);
    }

}
