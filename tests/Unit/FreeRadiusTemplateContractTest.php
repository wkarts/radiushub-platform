<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class FreeRadiusTemplateContractTest extends TestCase
{
    public function test_sql_pool_directives_are_multiline_and_complete_for_both_dialects(): void
    {
        $root = dirname(__DIR__, 2);
        $required = [
            'start',
            'min',
            'max',
            'spare',
            'uses',
            'max_retries',
            'retry_delay',
            'lifetime',
            'cleanup_interval',
            'idle_timeout',
        ];

        foreach (['mysql', 'postgresql'] as $dialect) {
            $contents = (string) file_get_contents($root."/resources/freeradius/{$dialect}/sql");

            self::assertDoesNotMatchRegularExpression('/^\s*pool\s*\{[^\r\n]*\S/m', $contents);
            self::assertMatchesRegularExpression('/^\s*pool\s*\{\s*$/m', $contents);

            foreach ($required as $option) {
                self::assertMatchesRegularExpression(
                    '/^\s*'.preg_quote($option, '/').'\s*=\s*\d+\s*$/m',
                    $contents,
                    "{$dialect}: {$option}",
                );
            }
        }
    }

    public function test_freeradius_image_validates_mysql_and_postgresql_templates_during_build(): void
    {
        $root = dirname(__DIR__, 2);
        $dockerfile = (string) file_get_contents($root.'/docker/freeradius/Dockerfile');
        $validator = (string) file_get_contents($root.'/docker/freeradius/validate-templates.sh');

        self::assertStringContainsString('radiushub-radius-validate-templates', $dockerfile);
        self::assertStringContainsString('&& /usr/local/bin/radiushub-radius-validate-templates', $dockerfile);
        self::assertStringContainsString('validate_dialect "$base_root" postgresql 5432', $validator);
        self::assertStringContainsString('validate_dialect "$base_root" mysql 3306', $validator);
        self::assertStringContainsString('freeradius -d "$config_root" -XC', $validator);
        self::assertStringContainsString('rlm_sql_null', $validator);
    }

    public function test_runtime_parser_failure_displays_sanitized_sql_configuration(): void
    {
        $root = dirname(__DIR__, 2);
        $entrypoint = (string) file_get_contents($root.'/docker/freeradius/entrypoint.sh');

        self::assertStringContainsString('print_sanitized_sql_config', $entrypoint);
        self::assertStringContainsString('<<< secret >>>', $entrypoint);
        self::assertStringContainsString('| nl -ba >&2', $entrypoint);
        self::assertStringNotContainsString('cat "$CONFIG_ROOT/mods-enabled/sql"', $entrypoint);
    }
}
