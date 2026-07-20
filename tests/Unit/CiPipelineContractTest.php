<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CiPipelineContractTest extends TestCase
{
    public function test_pull_requests_do_not_trigger_duplicate_push_workflows_or_release_builds(): void
    {
        $root = dirname(__DIR__, 2);
        $ci = file_get_contents($root.'/.github/workflows/ci.yml');
        $dockerPublish = file_get_contents($root.'/.github/workflows/docker-publish.yml');

        self::assertStringContainsString("pull_request:\n    branches: [main]", $ci);
        self::assertStringContainsString("push:\n    branches: [main]", $ci);
        self::assertStringNotContainsString("branches: ['**']", $ci);
        self::assertStringNotContainsString('docker-build:', $ci);
        self::assertStringContainsString("contains(github.event.pull_request.labels.*.name, 'full-validation')", $ci);
        self::assertStringContainsString('container-contracts:', $ci);

        self::assertStringContainsString('workflow_dispatch:', $dockerPublish);
        self::assertStringNotContainsString("push:\n", $dockerPublish);
    }

    public function test_full_smokes_remain_available_for_main_manual_runs_and_labeled_pull_requests(): void
    {
        $root = dirname(__DIR__, 2);
        $ci = file_get_contents($root.'/.github/workflows/ci.yml');

        self::assertStringContainsString('docker-playground-smoke:', $ci);
        self::assertStringContainsString('cloudpanel-native-smoke:', $ci);
        self::assertStringContainsString("github.event_name == 'push'", $ci);
        self::assertStringContainsString('inputs.full_validation', $ci);
        self::assertStringContainsString("'full-validation'", $ci);
    }
}
