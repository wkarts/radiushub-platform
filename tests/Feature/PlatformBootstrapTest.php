<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Platform\PlatformBootstrapService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class PlatformBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_creates_super_admin_tenant_company_and_links(): void
    {
        $this->configureBootstrap();
        $this->seed(RolePermissionSeeder::class);

        $result = app(PlatformBootstrapService::class)->ensure();

        $admin = $result['admin'];
        $tenant = $result['tenant'];
        $company = $result['company'];

        self::assertTrue($admin->is_super_admin);
        self::assertTrue($admin->active);
        self::assertSame('master', $admin->login);
        self::assertSame('principal', $tenant->slug);
        self::assertSame('Empresa Principal', $company->legal_name);

        $this->assertDatabaseHas('tenant_user', [
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'role' => 'tenant_admin',
        ]);
        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'active' => true,
        ]);
    }

    public function test_bootstrap_repairs_existing_super_admin_without_resetting_password(): void
    {
        $this->configureBootstrap();
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create([
            'email' => 'master@example.test',
            'login' => null,
            'password' => Hash::make('ExistingPassword@123'),
            'is_super_admin' => false,
            'active' => false,
        ]);

        app(PlatformBootstrapService::class)->ensure();
        $admin->refresh();

        self::assertTrue($admin->is_super_admin);
        self::assertTrue($admin->active);
        self::assertSame('master', $admin->login);
        self::assertTrue(Hash::check('ExistingPassword@123', $admin->password));
        self::assertFalse(Hash::check('BootstrapPassword@123', $admin->password));
    }

    public function test_super_admin_login_ignores_stale_tenant_intended_url(): void
    {
        $admin = User::factory()->create([
            'email' => 'master@example.test',
            'login' => 'master',
            'is_super_admin' => true,
            'active' => true,
        ]);

        $this->withSession(['url.intended' => '/'])
            ->post('/login', ['login' => 'master', 'password' => 'password'])
            ->assertRedirect(route('platform.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_super_admin_without_tenant_is_redirected_to_platform_dashboard_instead_of_403(): void
    {
        $admin = User::factory()->create([
            'is_super_admin' => true,
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->get('/')
            ->assertRedirect(route('platform.dashboard'));
    }

    private function configureBootstrap(): void
    {
        config()->set('platform.bootstrap.enabled', true);
        config()->set('platform.bootstrap.prefer_existing_context', true);
        config()->set('platform.bootstrap.attach_all_super_admins', true);
        config()->set('platform.bootstrap.admin', [
            'name' => 'Administrador Master',
            'email' => 'master@example.test',
            'login' => 'master',
            'password' => 'BootstrapPassword@123',
            'force_password' => false,
            'must_change_password' => true,
        ]);
        config()->set('platform.bootstrap.tenant', [
            'enabled' => true,
            'name' => 'RadiusHub Principal',
            'slug' => 'principal',
            'document' => null,
            'email' => 'master@example.test',
            'phone' => null,
            'timezone' => 'America/Bahia',
            'subscription_plan' => 'platform',
        ]);
        config()->set('platform.bootstrap.company', [
            'enabled' => true,
            'legal_name' => 'Empresa Principal',
            'trade_name' => 'Empresa Principal',
            'document' => null,
            'email' => 'master@example.test',
            'phone' => null,
            'subscription_plan' => 'platform',
        ]);
    }
}
