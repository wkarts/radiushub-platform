<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PlanningComplianceContractTest extends TestCase
{
    public function test_required_domain_components_remain_present(): void
    {
        $root = dirname(__DIR__, 2);
        $files = [
            'app/Models/Tenant.php',
            'app/Models/Company.php',
            'app/Models/User.php',
            'app/Models/MikrotikDevice.php',
            'app/Models/NetworkAccess.php',
            'app/Models/Voucher.php',
            'app/Services/Mikrotik/MikrotikSshService.php',
            'app/Services/Mikrotik/MikrotikSyncService.php',
            'app/Services/Mikrotik/MikrotikSimulatorService.php',
            'app/Services/Security/SshKeyVault.php',
            'app/Services/Security/RadiusCredentialVault.php',
            'app/Services/Audit/AuditLogger.php',
            'app/Http/Middleware/EnsureBoundModelsBelongToContext.php',
            'database/seeders/RolePermissionSeeder.php',
            'database/seeders/PlaygroundSeeder.php',
            'scripts/check-planning-compliance.php',
        ];

        foreach ($files as $file) {
            self::assertFileExists($root.'/'.$file, $file);
        }
    }

    public function test_routes_cover_planned_operational_flows(): void
    {
        $root = dirname(__DIR__, 2);
        $web = file_get_contents($root.'/routes/web.php');
        $webhooks = file_get_contents($root.'/routes/webhooks.php');

        foreach ([
            "Route::resource('companies'",
            "Route::resource('users'",
            "Route::resource('mikrotiks'",
            "Route::resource('accesses'",
            "Route::get('vouchers'",
            "Route::post('vouchers/{voucher}/block'",
            "Route::post('vouchers/{voucher}/renew'",
            "Route::get('voucher-batches/{batch}/pdf'",
            "Route::post('sessions/{session}/disconnect'",
            "Route::get('audit'",
        ] as $contract) {
            self::assertStringContainsString($contract, $web, $contract);
        }

        self::assertStringContainsString("/{token}", $webhooks);
        self::assertStringNotContainsString('{tenant}', $webhooks);
    }

    public function test_sidebar_and_mobile_contract_remain_available(): void
    {
        $root = dirname(__DIR__, 2);
        $layout = file_get_contents($root.'/resources/views/layouts/app.blade.php');
        $css = file_get_contents($root.'/public/css/app.css');
        $js = file_get_contents($root.'/public/js/app.js');

        self::assertStringContainsString('data-sidebar-collapse', $layout);
        self::assertStringContainsString('nav-submenu', $layout);
        self::assertStringContainsString('radiushub-sidebar-collapsed', $layout.$js);
        self::assertStringContainsString('sidebar-collapsed', $css);
        self::assertStringContainsString('@media(max-width:900px)', $css);
        self::assertStringContainsString('closeMobileMenu', $js);
    }

    public function test_deploy_contract_contains_real_health_login_and_radius_smoke(): void
    {
        $root = dirname(__DIR__, 2);
        $playground = file_get_contents($root.'/scripts/playground.sh');
        $http = file_get_contents($root.'/scripts/smoke-http.sh');
        $radius = file_get_contents($root.'/scripts/smoke-radius.sh');
        $ci = file_get_contents($root.'/.github/workflows/ci.yml');

        self::assertStringContainsString('/health/ready', $playground.$http);
        self::assertStringContainsString('Token CSRF', $http);
        self::assertStringContainsString('Access-Accept', $radius);
        self::assertStringContainsString('Accounting-Response', $radius);
        self::assertStringContainsString('docker-playground-smoke:', $ci);
        self::assertStringContainsString('cloudpanel-native-smoke:', $ci);
    }

    public function test_legacy_rbac_backfill_does_not_promote_every_tenant_member(): void
    {
        $root = dirname(__DIR__, 2);
        $seeder = file_get_contents($root.'/database/seeders/RolePermissionSeeder.php');

        self::assertStringContainsString("pivot?->role ?? null) !== 'tenant_admin'", $seeder);
    }
}
