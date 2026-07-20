<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DeploymentPlaygroundContractTest extends TestCase
{
    public function test_docker_playground_contains_required_services_and_safety_defaults(): void
    {
        $root = dirname(__DIR__, 2);
        $base = file_get_contents($root.'/docker-compose.yml');
        $overlay = file_get_contents($root.'/docker-compose.playground.yml');
        $env = file_get_contents($root.'/.env.playground.example');
        $script = file_get_contents($root.'/scripts/playground.sh');

        foreach (['app:', 'web:', 'worker:', 'scheduler:', 'freeradius:', 'redis:', 'postgres:'] as $service) {
            self::assertStringContainsString($service, $base.$overlay);
        }

        self::assertStringContainsString('127.0.0.1', $overlay);
        self::assertStringContainsString('PLAYGROUND_MODE=true', $env);
        self::assertStringContainsString('PLAYGROUND_MIKROTIK_SIMULATOR=true', $env);
        self::assertStringContainsString('radiushub:playground:verify', $script);
        self::assertStringContainsString('/health/ready', $script);
        self::assertStringContainsString('smoke-http.sh', $script);
        self::assertStringContainsString('smoke-radius.sh', $script);
    }

    public function test_cloudpanel_playground_installer_preserves_explicit_opt_in(): void
    {
        $root = dirname(__DIR__, 2);
        $script = file_get_contents($root.'/scripts/install-cloudpanel-playground.sh');

        self::assertStringContainsString('PLAYGROUND_MODE true', $script);
        self::assertStringContainsString('--reuse-env', $script);
        self::assertStringContainsString('radiushub:playground:verify', $script);
    }
    public function test_production_examples_keep_playground_disabled(): void
    {
        $root = dirname(__DIR__, 2);

        foreach (['.env.example', '.env.cloudpanel.example', '.env.docker.mysql.example', '.env.docker.postgres.example'] as $file) {
            $contents = file_get_contents($root.'/'.$file);
            self::assertStringContainsString('PLAYGROUND_MODE=false', $contents, $file);
            self::assertStringContainsString('PLAYGROUND_MIKROTIK_SIMULATOR=false', $contents, $file);
            self::assertStringContainsString('PLAYGROUND_ALLOW_PRODUCTION=false', $contents, $file);
        }

        foreach (['.env.cloudpanel.example', '.env.docker.mysql.example', '.env.docker.postgres.example'] as $file) {
            self::assertStringContainsString('SESSION_SECURE_COOKIE=true', file_get_contents($root.'/'.$file), $file);
        }
    }

    public function test_radius_smoke_requires_authentication_and_accounting_responses(): void
    {
        $root = dirname(__DIR__, 2);
        $script = file_get_contents($root.'/scripts/smoke-radius.sh');

        self::assertStringContainsString('Access-Accept', $script);
        self::assertStringContainsString('Accounting-Response', $script);
        self::assertStringContainsString('--accounting-session=', $script);
        self::assertStringContainsString('--radius', $script);
        self::assertStringContainsString('radclient -x -r 1 -t 1', $script);
        self::assertStringContainsString('RADIUS_SMOKE_ATTEMPTS', $script);
    }

    public function test_cloudpanel_docker_installer_generates_proxy_and_defers_public_https_smoke(): void
    {
        $root = dirname(__DIR__, 2);
        $installer = file_get_contents($root.'/scripts/install-cloudpanel-docker.sh');
        $playground = file_get_contents($root.'/scripts/playground.sh');
        $ci = file_get_contents($root.'/.github/workflows/ci.yml');

        self::assertStringContainsString('nginx-docker-reverse-proxy.conf', $installer);
        self::assertStringContainsString('--skip-http-smoke', $installer);
        self::assertStringContainsString('--smoke-url', $installer);
        self::assertStringContainsString('local_base_url', $playground);
        self::assertStringContainsString('install-cloudpanel-docker.sh --playground', $ci);
        self::assertStringContainsString('validate-deployment.sh --http --login', $ci);
    }

    public function test_playground_environment_examples_are_distributed_and_scripts_are_normalized_in_ci(): void
    {
        $root = dirname(__DIR__, 2);
        $gitignore = file_get_contents($root.'/.gitignore');
        $dockerignore = file_get_contents($root.'/.dockerignore');
        $ci = file_get_contents($root.'/.github/workflows/ci.yml');

        self::assertFileExists($root.'/.env.playground.example');
        self::assertFileExists($root.'/.env.cloudpanel.playground.example');
        self::assertStringContainsString('!/.env.playground.example', $gitignore);
        self::assertStringContainsString('!/.env.cloudpanel.playground.example', $gitignore);
        self::assertStringContainsString('!.env.playground.example', $dockerignore);
        self::assertStringContainsString('!.env.cloudpanel.playground.example', $dockerignore);
        self::assertStringContainsString('test -f .env.playground.example', $ci);
        self::assertStringContainsString('test -f .env.cloudpanel.playground.example', $ci);
        self::assertStringContainsString('chmod +x scripts/*.sh artisan', $ci);
        self::assertStringContainsString('bash ./scripts/playground.sh', $ci);
    }

    public function test_cloudpanel_upgrade_reconciles_master_tenant_and_default_company(): void
    {
        $root = dirname(__DIR__, 2);
        $upgrade = file_get_contents($root.'/scripts/upgrade-1.3.5-to-1.4.0.sh');
        $repair = file_get_contents($root.'/scripts/repair-cloudpanel-bootstrap.sh');

        self::assertStringContainsString('radiushub:bootstrap-platform', $upgrade);
        self::assertStringContainsString('radiushub:bootstrap-platform', $repair);
        self::assertStringContainsString('SEED_DEFAULT_TENANT', $repair);
        self::assertStringContainsString('SEED_DEFAULT_COMPANY', $repair);
        self::assertStringContainsString('set_env APP_VERSION', $repair);
        self::assertStringContainsString('REDIS_HOST', $repair);
    }

    public function test_playground_disables_radius_client_reload_and_production_uses_stable_fingerprint(): void
    {
        $root = dirname(__DIR__, 2);
        $overlay = file_get_contents($root.'/docker-compose.playground.yml');
        $entrypoint = file_get_contents($root.'/docker/freeradius/entrypoint.sh');

        self::assertStringContainsString('RADIUS_CLIENT_RELOAD_SECONDS: "0"', $overlay);
        self::assertStringContainsString('radius_clients_fingerprint', $entrypoint);
        self::assertStringContainsString('radius_secret_ciphertext', $entrypoint);
        self::assertStringNotContainsString('MAX(updated_at)', $entrypoint);
    }

}
