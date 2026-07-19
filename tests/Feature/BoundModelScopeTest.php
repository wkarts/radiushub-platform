<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoundModelScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_bound_resource_from_another_company_is_not_accessible(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Tenant', 'slug' => 'tenant', 'active' => true]);
        $companyA = Company::query()->create(['tenant_id' => $tenant->id, 'legal_name' => 'Empresa A']);
        $companyB = Company::query()->create(['tenant_id' => $tenant->id, 'legal_name' => 'Empresa B']);

        $view = Permission::query()->create(['name' => 'Ver vouchers', 'slug' => 'vouchers.view', 'module' => 'vouchers']);
        $manage = Permission::query()->create(['name' => 'Gerenciar vouchers', 'slug' => 'vouchers.manage', 'module' => 'vouchers']);
        $role = Role::query()->create([
            'name' => 'Operador', 'slug' => 'operador', 'scope' => 'company', 'active' => true,
        ]);
        $role->permissions()->attach([$view->id, $manage->id]);

        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => 'operator']);
        $companyA->users()->attach($user->id, ['role_id' => $role->id, 'active' => true]);

        app(TenantContext::class)->set($tenant);
        app(CompanyContext::class)->set($companyB);
        $voucher = Voucher::query()->create([
            'code' => 'OUTRA-EMPRESA',
            'password_ciphertext' => 'ciphertext-for-test',
            'status' => 'available',
        ]);
        app(CompanyContext::class)->clear();
        app(TenantContext::class)->clear();

        $this->actingAs($user)
            ->withSession([
                config('tenancy.session_key') => $tenant->id,
                config('tenancy.company_session_key') => $companyA->id,
            ])
            ->post(route('vouchers.block', $voucher))
            ->assertNotFound();
    }
}
