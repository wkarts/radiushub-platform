<?php

namespace App\Services\Security;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

final class SshKeyVault
{
    public function encrypt(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : Crypt::encryptString($value);
    }

    public function decrypt(?string $ciphertext): ?string
    {
        if (! $ciphertext) return null;

        try {
            return Crypt::decryptString($ciphertext);
        } catch (DecryptException $exception) {
            throw new RuntimeException('Não foi possível descriptografar a credencial SSH.', previous: $exception);
        }
    }
}
