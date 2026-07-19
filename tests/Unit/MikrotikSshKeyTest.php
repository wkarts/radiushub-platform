<?php

namespace Tests\Unit;

use App\Services\Mikrotik\MikrotikSshService;
use App\Services\Mikrotik\RouterOsCommandBuilder;
use App\Services\Security\SensitiveDataSanitizer;
use App\Services\Security\SshKeyVault;
use PHPUnit\Framework\TestCase;

class MikrotikSshKeyTest extends TestCase
{
    public function test_generated_private_key_is_valid_and_has_public_fingerprint(): void
    {
        $service = new MikrotikSshService(new SshKeyVault(), new RouterOsCommandBuilder(), new SensitiveDataSanitizer());
        $pair = $service->generateKeyPair(2048);
        $validation = $service->validatePrivateKey($pair['private_key']);

        $this->assertTrue($validation['valid']);
        $this->assertStringStartsWith('ssh-rsa ', $validation['public_key']);
        $this->assertStringStartsWith('SHA256:', $validation['fingerprint']);
    }

    public function test_invalid_private_key_is_rejected(): void
    {
        $service = new MikrotikSshService(new SshKeyVault(), new RouterOsCommandBuilder(), new SensitiveDataSanitizer());
        $this->assertFalse($service->validatePrivateKey('not-a-private-key')['valid']);
    }
}
