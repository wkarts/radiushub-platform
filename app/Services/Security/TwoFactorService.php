<?php

namespace App\Services\Security;

use PragmaRX\Google2FA\Google2FA;

final class TwoFactorService
{
    public function __construct(private readonly Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function verify(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, preg_replace('/\D+/', '', $code), 1);
    }

    public function otpauthUri(string $email, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(config('app.name'), $email, $secret);
    }

    public function recoveryCodes(): array
    {
        return collect(range(1, 8))->map(fn () => strtoupper(bin2hex(random_bytes(5))))->all();
    }
}
