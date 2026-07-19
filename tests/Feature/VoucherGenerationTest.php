<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\TenantContext;
use App\Services\Vouchers\VoucherGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_unique_encrypted_vouchers_for_current_company(): void
    {
        config()->set('radius.credential_key', 'test-radius-credential-key-with-at-least-32-characters');

        $tenant = Tenant::query()->create(['name' => 'Tenant', 'slug' => 'tenant', 'status' => 'active']);
        $company = Company::query()->create(['tenant_id' => $tenant->id, 'legal_name' => 'Empresa']);
        $user = User::factory()->create();
        $this->actingAs($user);
        app(TenantContext::class)->set($tenant);
        app(CompanyContext::class)->set($company);

        $batch = app(VoucherGeneratorService::class)->generate([
            'batch_name' => 'Lote Teste',
            'quantity' => 20,
            'alphabet' => 'readable',
            'code_length' => 8,
            'prefix' => 'RH-',
            'validity_mode' => 'first_access',
            'validity_duration_minutes' => 1440,
            'max_devices' => 1,
        ]);

        $this->assertCount(20, $batch->vouchers);
        $this->assertCount(20, $batch->vouchers->pluck('code')->unique());
        $this->assertTrue($batch->vouchers->every(fn ($voucher) => $voucher->company_id === $company->id));
        $this->assertTrue($batch->vouchers->every(fn ($voucher) => str_starts_with($voucher->password_ciphertext, 'local:')));
    }
}
