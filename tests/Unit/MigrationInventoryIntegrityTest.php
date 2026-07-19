<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class MigrationInventoryIntegrityTest extends TestCase
{
    public function test_migration_sequences_are_unique_and_obsolete_webhook_migration_is_absent(): void
    {
        $directory = dirname(__DIR__, 2) . '/database/migrations';
        $files = glob($directory . '/*.php') ?: [];
        sort($files, SORT_STRING);

        $sequences = [];

        foreach ($files as $file) {
            $name = basename($file);
            self::assertMatchesRegularExpression(
                '/^(\d{4}_\d{2}_\d{2}_\d{6})_.+\.php$/',
                $name,
                "Nome de migration inválido: {$name}",
            );

            preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})_/', $name, $matches);
            $sequences[$matches[1]][] = $name;
        }

        $duplicates = array_filter($sequences, static fn (array $group): bool => count($group) > 1);

        self::assertSame([], $duplicates, 'Existem sequências de migration duplicadas: ' . json_encode($duplicates));
        self::assertFileDoesNotExist(
            $directory . '/2026_07_19_000800_secure_asaas_webhooks_by_gateway.php',
        );
        self::assertFileExists(
            $directory . '/2026_07_19_000800_secure_asaas_webhook_per_gateway.php',
        );
    }
}
