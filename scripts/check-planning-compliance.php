<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];

$requireFile = static function (string $path) use ($root, &$errors): string {
    $full = $root.'/'.$path;
    if (! is_file($full)) {
        $errors[] = "Arquivo obrigatório ausente: {$path}";
        return '';
    }

    $contents = file_get_contents($full);
    if ($contents === false) {
        $errors[] = "Não foi possível ler: {$path}";
        return '';
    }

    return $contents;
};

$contains = static function (string $contents, string $needle, string $context) use (&$errors): void {
    if (! str_contains($contents, $needle)) {
        $errors[] = "Contrato ausente em {$context}: {$needle}";
    }
};

$notContains = static function (string $contents, string $needle, string $context) use (&$errors): void {
    if (str_contains($contents, $needle)) {
        $errors[] = "Contrato proibido encontrado em {$context}: {$needle}";
    }
};

foreach ([
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
    'app/Services/Platform/PlatformBootstrapService.php',
    'app/Console/Commands/BootstrapPlatform.php',
    'database/seeders/PlatformBootstrapSeeder.php',
    'app/Http/Middleware/EnsureBoundModelsBelongToContext.php',
    'database/seeders/RolePermissionSeeder.php',
    'database/seeders/PlaygroundSeeder.php',
    'docker-compose.yml',
    'docker-compose.playground.yml',
    'scripts/playground.sh',
    'scripts/install-cloudpanel.sh',
    'scripts/install-cloudpanel-docker.sh',
    'scripts/repair-cloudpanel-bootstrap.sh',
    'docs/FIRST_ACCESS.md',
    'scripts/smoke-http.sh',
    'scripts/smoke-radius.sh',
    'docs/PLANNING_COMPLIANCE.md',
] as $file) {
    $requireFile($file);
}

$web = $requireFile('routes/web.php');
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
] as $route) {
    $contains($web, $route, 'routes/web.php');
}

$webhooks = $requireFile('routes/webhooks.php');
$contains($webhooks, '/{token}', 'routes/webhooks.php');
$notContains($webhooks, '{tenant}', 'routes/webhooks.php');

$ssh = $requireFile('app/Services/Mikrotik/MikrotikSshService.php');
foreach (['validatePrivateKey', 'assertHostFingerprint', 'executeApproved', 'ssh_password_fallback_enabled'] as $contract) {
    $contains($ssh, $contract, 'MikrotikSshService');
}

$layout = $requireFile('resources/views/layouts/app.blade.php');
$css = $requireFile('public/css/app.css');
$js = $requireFile('public/js/app.js');
foreach (['data-sidebar-collapse', 'nav-submenu'] as $contract) {
    $contains($layout, $contract, 'layout principal');
}
$contains($layout.$js, 'radiushub-sidebar-collapsed', 'sidebar persistente');
$contains($css, '@media(max-width:900px)', 'CSS responsivo');
$contains($js, 'closeMobileMenu', 'JavaScript mobile');

$compose = $requireFile('docker-compose.yml').$requireFile('docker-compose.playground.yml');
foreach (['app:', 'web:', 'worker:', 'scheduler:', 'freeradius:', 'redis:', 'postgres:', 'mysql:'] as $service) {
    $contains($compose, $service, 'Docker Compose');
}

$playground = $requireFile('scripts/playground.sh');
$http = $requireFile('scripts/smoke-http.sh');
$radius = $requireFile('scripts/smoke-radius.sh');
foreach (['/health/ready', 'radiushub:playground:verify', 'smoke-http.sh', 'smoke-radius.sh'] as $contract) {
    $contains($playground, $contract, 'playground Docker');
}
$contains($http, 'Token CSRF', 'smoke HTTP');
$contains($radius, 'Access-Accept', 'smoke RADIUS');
$contains($radius, 'Accounting-Response', 'smoke accounting');


$bootstrap = $requireFile('app/Services/Platform/PlatformBootstrapService.php');
foreach (['ensureAdmin', 'ensureTenant', 'ensureCompany', 'attachTenantAdmin', 'attachCompanyAdmin'] as $contract) {
    $contains($bootstrap, $contract, 'bootstrap inicial da plataforma');
}
$contains($requireFile('app/Console/Commands/BootstrapPlatform.php'), 'radiushub:bootstrap-platform', 'comando de bootstrap');
$contains($requireFile('scripts/upgrade-1.3.5-to-1.4.0.sh'), 'radiushub:bootstrap-platform', 'upgrade CloudPanel 1.3.5 → 1.4.0');
$contains($requireFile('scripts/repair-cloudpanel-bootstrap.sh'), 'SEED_DEFAULT_TENANT', 'reparo CloudPanel');
$contains($requireFile('scripts/repair-cloudpanel-bootstrap.sh'), 'SEED_DEFAULT_COMPANY', 'reparo CloudPanel');
$contains($requireFile('scripts/repair-cloudpanel-bootstrap.sh'), 'set_env APP_VERSION', 'reparo CloudPanel');
$contains($requireFile('scripts/repair-cloudpanel-bootstrap.sh'), 'REDIS_HOST', 'reparo CloudPanel');
$contains($requireFile('app/Http/Controllers/Auth/AuthenticatedSessionController.php'), "forget([
                'url.intended'", 'redirecionamento seguro do Superadministrador');
$contains($requireFile('app/Http/Middleware/SetCurrentTenant.php'), "redirect()->route('platform.dashboard')", 'fallback sem tenant');

$gitignore = $requireFile('.gitignore');
$contains($gitignore, '!/.env.playground.example', 'distribuição do playground');
$contains($gitignore, '!/.env.cloudpanel.playground.example', 'distribuição CloudPanel playground');
$ci = $requireFile('.github/workflows/ci.yml');
$contains($ci, 'test -f .env.playground.example', 'CI de distribuição');
$contains($ci, 'chmod +x scripts/*.sh artisan', 'CI de permissões');

$rbac = $requireFile('database/seeders/RolePermissionSeeder.php');
$contains($rbac, "pivot?->role ?? null) !== 'tenant_admin'", 'backfill RBAC');

foreach (['.env.example', '.env.cloudpanel.example', '.env.docker.mysql.example', '.env.docker.postgres.example'] as $envFile) {
    $env = $requireFile($envFile);
    foreach (['PLAYGROUND_MODE=false', 'PLAYGROUND_MIKROTIK_SIMULATOR=false', 'PLAYGROUND_ALLOW_PRODUCTION=false'] as $flag) {
        $contains($env, $flag, $envFile);
    }
}

if ($errors !== []) {
    fwrite(STDERR, "Falha na conformidade estática do planejamento:\n- ".implode("\n- ", $errors)."\n");
    exit(1);
}

printf(
    "Conformidade estática do planejamento validada para RadiusHub %s.\n".
    "Observação: RouterOS, Asaas Sandbox, SMTP e dispositivos físicos exigem homologação externa.\n",
    trim((string) file_get_contents($root.'/VERSION')),
);
