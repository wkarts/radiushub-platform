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

}
