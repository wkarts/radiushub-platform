<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CiPipelineContractTest extends TestCase
{
    public function test_pull_requests_run_complete_validation_without_duplicate_push_or_release_publication(): void
    {
        $root = dirname(__DIR__, 2);
        $ci = file_get_contents($root.'/.github/workflows/ci.yml');
        $dockerPublish = file_get_contents($root.'/.github/workflows/docker-publish.yml');

        self::assertIsString($ci);
        self::assertStringContainsString("pull_request:\n    branches: [main]", $ci);
        self::assertStringContainsString("push:\n    branches: [main]", $ci);
        self::assertStringNotContainsString("branches: ['**']", $ci);

        self::assertStringContainsString('docker-playground-smoke:', $ci);
        self::assertStringContainsString('cloudpanel-native-smoke:', $ci);
        self::assertStringContainsString('Construir imagem FreeRADIUS', $ci);
        self::assertStringContainsString('Access-Accept', file_get_contents($root.'/scripts/smoke-radius.sh'));
        self::assertStringContainsString('Accounting-Response', file_get_contents($root.'/scripts/smoke-radius.sh'));

        self::assertStringNotContainsString('full-validation', $ci);
        self::assertStringNotContainsString('github.event.pull_request.labels', $ci);
        self::assertStringNotContainsString('inputs.full_validation', $ci);
        self::assertStringNotContainsString('actions/upload-artifact', $ci);
        self::assertStringNotContainsString('gh release create', $ci);

        self::assertStringContainsString('workflow_dispatch:', $dockerPublish);
        self::assertStringNotContainsString("push:\n", $dockerPublish);
    }

    public function test_complete_smokes_are_required_before_merge(): void
    {
        $root = dirname(__DIR__, 2);
        $ci = file_get_contents($root.'/.github/workflows/ci.yml');

        self::assertIsString($ci);
        self::assertMatchesRegularExpression('/docker-playground-smoke:\n(?:.*\n){0,5}\s+runs-on:/', $ci);
        self::assertMatchesRegularExpression('/cloudpanel-native-smoke:\n(?:.*\n){0,5}\s+runs-on:/', $ci);
        self::assertStringContainsString('install-cloudpanel-docker.sh --playground', $ci);
        self::assertStringContainsString('install-cloudpanel-playground.sh --reuse-env', $ci);
        self::assertStringContainsString('validate-deployment.sh --http --login', $ci);
    }
}
