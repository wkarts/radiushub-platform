<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class MigrationSafetyTest extends TestCase
{
    public function test_webhook_replacement_index_is_created_before_legacy_index_is_removed(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/database/migrations/2026_07_19_000800_secure_asaas_webhook_per_gateway.php');

        self::assertIsString($source);

        $replacement = strpos($source, "if (! \$this->indexExists('webhook_events', 'webhook_events_tenant_company_status_index'))");
        $legacyDrop = strpos($source, "if (\$this->indexExists('webhook_events', 'webhook_events_tenant_id_provider_external_event_id_unique'))");

        self::assertNotFalse($replacement);
        self::assertNotFalse($legacyDrop);
        self::assertLessThan($legacyDrop, $replacement);
    }

    public function test_multi_company_migration_has_partial_ddl_recovery_guard(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/database/migrations/2026_07_19_000700_add_companies_rbac_ssh_vouchers.php');

        self::assertIsString($source);
        self::assertStringContainsString('partialMigrationCanBeResumed', $source);
        self::assertStringContainsString('reconcileCompanyScopedIndexes', $source);
    }
}
