<?php

namespace Tests\Unit;

use App\Services\Mikrotik\MikrotikSimulatorService;
use App\Services\Mikrotik\MikrotikSshService;
use ReflectionProperty;
use Tests\TestCase;

final class DeploymentRegressionTest extends TestCase
{
    public function test_mikrotik_simulator_is_injected_by_the_service_container(): void
    {
        $service = app(MikrotikSshService::class);
        $property = new ReflectionProperty(MikrotikSshService::class, 'simulator');

        self::assertInstanceOf(MikrotikSimulatorService::class, $property->getValue($service));
    }


    public function test_health_command_uses_symfony_global_quiet_option_without_redeclaring_it(): void
    {
        $root = dirname(__DIR__, 2);
        $command = (string) file_get_contents($root.'/app/Console/Commands/HealthCheckCommand.php');

        self::assertStringNotContainsString('{--quiet', $command);
        self::assertStringContainsString('$this->output->isQuiet()', $command);

        $this->artisan('radiushub:health', ['--quiet' => true])
            ->assertExitCode(0);
    }

    public function test_php_fpm_master_keeps_required_container_privileges_and_cli_drops_to_www_data(): void
    {
        $root = dirname(__DIR__, 2);
        $entrypoint = (string) file_get_contents($root.'/docker/app/entrypoint.sh');
        $dockerfile = (string) file_get_contents($root.'/docker/app/Dockerfile');

        self::assertStringContainsString('runtime_command="$(basename "${1:-}")"', $entrypoint);
        self::assertStringContainsString('if [ "$runtime_command" = "php-fpm" ]; then', $entrypoint);
        self::assertStringContainsString('exec "$@"', $entrypoint);
        self::assertStringContainsString('exec gosu www-data "$@"', $entrypoint);
        self::assertStringContainsString('USER root', $dockerfile);
    }

    public function test_cloudpanel_install_uses_safe_cache_clear_before_migrations(): void
    {
        $root = dirname(__DIR__, 2);
        $installer = (string) file_get_contents($root.'/scripts/install-cloudpanel.sh');
        $library = (string) file_get_contents($root.'/scripts/lib.sh');

        self::assertStringContainsString('artisan_optimize_clear_safe "$PHP_BIN"', $installer);
        self::assertStringContainsString('CACHE_STORE=array', $library);
        self::assertStringContainsString('QUEUE_CONNECTION=sync', $library);
        self::assertLessThan(
            strpos($installer, 'artisan migrate --force'),
            strpos($installer, 'artisan_optimize_clear_safe'),
        );
    }
}
