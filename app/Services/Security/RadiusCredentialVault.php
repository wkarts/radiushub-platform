<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class RadiusCredentialVault
{
    public function encrypt(string $plainText): string
    {
        $driver = DB::connection()->getDriverName();
        $key = $this->key();

        if ($driver === 'pgsql') {
            $row = DB::selectOne(
                "SELECT encode(pgp_sym_encrypt(?, ?, 'cipher-algo=aes256, compress-algo=1'), 'base64') AS value",
                [$plainText, $key],
            );

            return 'pgp:'.(string) $row->value;
        }

        if ($driver === 'mysql') {
            $row = DB::selectOne(
                'SELECT TO_BASE64(AES_ENCRYPT(?, UNHEX(SHA2(?, 256)))) AS value',
                [$plainText, $key],
            );

            return 'mysql:'.preg_replace('/\s+/', '', (string) $row->value);
        }

        return 'local:'.$this->encryptLocal($plainText, $key);
    }

    public function decrypt(string $cipherText): string
    {
        $driver = DB::connection()->getDriverName();
        $key = $this->key();

        if (str_starts_with($cipherText, 'pgp:')) {
            return $this->decryptPostgres(substr($cipherText, 4), $key);
        }

        if (str_starts_with($cipherText, 'mysql:')) {
            return $this->decryptMySql(substr($cipherText, 6), $key);
        }

        if (str_starts_with($cipherText, 'local:')) {
            return $this->decryptLocal(substr($cipherText, 6), $key);
        }

        // Compatibilidade com a versão 1.1.0.
        if ($driver === 'pgsql') {
            try {
                return $this->decryptPostgres($cipherText, $key);
            } catch (Throwable) {
                return $this->decryptLegacyLocal($cipherText, $key);
            }
        }

        return $this->decryptLegacyLocal($cipherText, $key);
    }

    public function isRadiusReadable(string $cipherText): bool
    {
        $driver = DB::connection()->getDriverName();

        return ($driver === 'pgsql' && str_starts_with($cipherText, 'pgp:'))
            || ($driver === 'mysql' && str_starts_with($cipherText, 'mysql:'));
    }

    private function decryptPostgres(string $payload, string $key): string
    {
        $row = DB::selectOne(
            "SELECT pgp_sym_decrypt(decode(?, 'base64'), ?) AS value",
            [$payload, $key],
        );

        return (string) $row->value;
    }

    private function decryptMySql(string $payload, string $key): string
    {
        $row = DB::selectOne(
            "SELECT CAST(AES_DECRYPT(FROM_BASE64(?), UNHEX(SHA2(?, 256))) AS CHAR CHARACTER SET utf8mb4) AS value",
            [$payload, $key],
        );

        if (! isset($row->value) || $row->value === null) {
            throw new RuntimeException('Falha ao descriptografar credencial MySQL.');
        }

        return (string) $row->value;
    }

    private function encryptLocal(string $plainText, string $key): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plainText, 'aes-256-gcm', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) {
            throw new RuntimeException('Falha ao criptografar credencial local.');
        }

        return base64_encode($iv.$tag.$cipher);
    }

    private function decryptLocal(string $payload, string $key): string
    {
        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < 28) {
            throw new RuntimeException('Credencial local inválida.');
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv, $tag);

        if ($plain === false) {
            throw new RuntimeException('Falha ao descriptografar credencial local.');
        }

        return $plain;
    }

    private function decryptLegacyLocal(string $cipherText, string $key): string
    {
        $decoded = base64_decode($cipherText, true);
        $value = $decoded === false ? false : openssl_decrypt(
            $decoded,
            'aes-256-cbc',
            hash('sha256', $key, true),
            OPENSSL_RAW_DATA,
            substr(hash('sha256', 'radius-iv'.$key, true), 0, 16),
        );

        if ($value === false) {
            throw new RuntimeException('Credencial legada inválida ou chave RADIUS incorreta.');
        }

        return $value;
    }

    private function key(): string
    {
        $key = (string) config('radius.credential_key');
        if (strlen($key) < 32 || str_starts_with($key, 'change-this')) {
            throw new RuntimeException('RADIUS_CREDENTIAL_KEY deve ser uma chave aleatória com pelo menos 32 caracteres.');
        }

        return $key;
    }
}
