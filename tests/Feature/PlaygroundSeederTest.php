<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\MikrotikDevice;
use App\Models\NetworkAccess;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Mikrotik\MikrotikSshService;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\PlaygroundSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PlaygroundSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_playground_seeder_is_idempotent_and_simulator_is_operational(): void
    {
        config()->set('playground.enabled', true);
        config()->set('playground.mikrotik_simulator', true);
        config()->set('radius.credential_key', 'test-radius-credential-key-with-at-least-32-characters');

        $this->seed(PlaygroundSeeder::class);
        $this->seed(PlaygroundSeeder::class);

        $tenant = Tenant::query()->where('slug', 'playground')->firstOrFail();
        $company = Company::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        app(TenantContext::class)->set($tenant);
        app(CompanyContext::class)->set($company);

        $this->assertSame(1, NetworkAccess::query()->count());
        $this->assertSame(6, Voucher::query()->count());

        $operator = User::query()->where('email', 'operador@playground.local')->firstOrFail();
        $technician = User::query()->where('email', 'tecnico@playground.local')->firstOrFail();
        $operatorRole = Role::query()->where('slug', 'operator')->firstOrFail();
        $technicianRole = Role::query()->where('slug', 'technician')->firstOrFail();
        $this->assertSame($operatorRole->id, $operator->companies()->whereKey($company->id)->firstOrFail()->pivot->role_id);
        $this->assertSame($technicianRole->id, $technician->companies()->whereKey($company->id)->firstOrFail()->pivot->role_id);
        $this->assertNotNull(User::query()->where('email', 'admin@playground.local')->firstOrFail()->email_verified_at);

        $device = MikrotikDevice::query()->where('connection_method', 'simulator')->firstOrFail();
        $result = app(MikrotikSshService::class)->test($device);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['simulated']);
        $this->assertSame('RadiusHub-Playground', $result['identity']['identity']);
    }
}
