<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Subscriber;
use App\Models\Tenant;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscriber_query_is_scoped_to_tenant_and_company(): void
    {
        $tenantA = Tenant::query()->create(['name' => 'Tenant A', 'slug' => 'tenant-a']);
        $tenantB = Tenant::query()->create(['name' => 'Tenant B', 'slug' => 'tenant-b']);
        $companyA = Company::query()->create(['tenant_id' => $tenantA->id, 'legal_name' => 'Empresa A']);
        $companyB = Company::query()->create(['tenant_id' => $tenantB->id, 'legal_name' => 'Empresa B']);

        $tenants = app(TenantContext::class);
        $companies = app(CompanyContext::class);

        $tenants->set($tenantA); $companies->set($companyA);
        Subscriber::query()->create(['name' => 'Cliente A', 'type' => 'person', 'status' => 'active']);
        $tenants->clear(); $companies->clear();

        $tenants->set($tenantB); $companies->set($companyB);
        Subscriber::query()->create(['name' => 'Cliente B', 'type' => 'person', 'status' => 'active']);

        $this->assertSame(['Cliente B'], Subscriber::query()->pluck('name')->all());
    }
}
