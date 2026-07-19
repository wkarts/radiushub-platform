<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ReleaseAutomationTest extends TestCase
{
    public function test_release_workflow_is_automatic_idempotent_and_versioned(): void
    {
        $root = dirname(__DIR__, 2);
        $workflow = file_get_contents($root.'/.github/workflows/release.yml');

        self::assertIsString($workflow);
        self::assertStringContainsString('workflow_run:', $workflow);
        self::assertStringContainsString("workflows: ['CI']", $workflow);
        self::assertStringContainsString("head_branch == 'main'", $workflow);
        self::assertStringContainsString('gh release view', $workflow);
        self::assertStringContainsString('gh release create', $workflow);
        self::assertStringContainsString('gh release upload', $workflow);
        self::assertStringContainsString('packages: write', $workflow);
        self::assertStringContainsString('actions/upload-artifact@v7', $workflow);
        self::assertStringContainsString('actions/download-artifact@v8', $workflow);
    }

    public function test_publish_job_has_repository_context_and_post_publish_validation(): void
    {
        $root = dirname(__DIR__, 2);
        $workflow = file_get_contents($root.'/.github/workflows/release.yml');

        self::assertIsString($workflow);
        self::assertStringContainsString('GH_REPO: ${{ github.repository }}', $workflow);
        self::assertStringContainsString('Checkout do commit da release', $workflow);
        self::assertStringContainsString('ref: ${{ needs.prepare.outputs.commit_sha }}', $workflow);
        self::assertStringContainsString('git rev-parse --is-inside-work-tree', $workflow);
        self::assertStringContainsString('--repo "${GH_REPO}"', $workflow);
        self::assertStringContainsString('Verificar release publicada', $workflow);
        self::assertStringContainsString('Artefatos: 4 verificados', $workflow);
    }

    public function test_version_integrity_guard_exists(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileExists($root.'/VERSION');
        self::assertFileExists($root.'/scripts/check-version-integrity.php');
        self::assertSame('1.4.0', trim((string) file_get_contents($root.'/VERSION')));
    }
}
