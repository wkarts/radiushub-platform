<?php

namespace Tests\Unit;

use App\Services\Security\SensitiveDataSanitizer;
use PHPUnit\Framework\TestCase;

class SensitiveDataSanitizerTest extends TestCase
{
    public function test_secrets_and_private_keys_are_redacted(): void
    {
        $sanitizer = new SensitiveDataSanitizer();
        $value = "password=Segredo123 secret=radius-token\n-----BEGIN PRIVATE KEY-----\nABC\n-----END PRIVATE KEY-----";
        $clean = $sanitizer->sanitize($value);

        $this->assertStringNotContainsString('Segredo123', $clean);
        $this->assertStringNotContainsString('radius-token', $clean);
        $this->assertStringNotContainsString('ABC', $clean);
    }
}
