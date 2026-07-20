<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class FreeRadiusVirtualServerContractTest extends TestCase
{
    public function test_named_authentication_blocks_use_parser_safe_multiline_syntax(): void
    {
        $root = dirname(__DIR__, 2);
        $default = file_get_contents($root.'/resources/freeradius/common/default');

        self::assertIsString($default);
        self::assertDoesNotMatchRegularExpression(
            '/^\s*(Auth-Type|Post-Auth-Type|Autz-Type|Acct-Type)\s+[A-Za-z0-9_-]+\s*\{[^\r\n]*\S/m',
            $default,
        );

        foreach ([
            'PAP' => 'pap',
            'CHAP' => 'chap',
            'MS-CHAP' => 'mschap',
        ] as $authType => $module) {
            self::assertMatchesRegularExpression(
                sprintf(
                    '/^\s*Auth-Type\s+%s\s*\{\s*^\s*%s\s*$\s*^\s*}\s*$/m',
                    preg_quote($authType, '/'),
                    preg_quote($module, '/'),
                ),
                $default,
                "Auth-Type {$authType} deve manter o módulo {$module} em bloco multilinha.",
            );
        }
    }

    public function test_build_still_runs_the_real_freeradius_parser_for_both_dialects(): void
    {
        $root = dirname(__DIR__, 2);
        $dockerfile = file_get_contents($root.'/docker/freeradius/Dockerfile');
        $validator = file_get_contents($root.'/docker/freeradius/validate-templates.sh');

        self::assertIsString($dockerfile);
        self::assertIsString($validator);
        self::assertStringContainsString('radiushub-radius-validate-templates', $dockerfile);
        self::assertStringContainsString('freeradius -d "$config_root" -XC', $validator);
        self::assertStringContainsString('nl -ba "$config_root/sites-enabled/default"', $validator);
        self::assertStringContainsString('validate_dialect "$base_root" postgresql 5432', $validator);
        self::assertStringContainsString('validate_dialect "$base_root" mysql 3306', $validator);
    }
}
