<?php

namespace App\Services\Mikrotik;

use App\Models\MikrotikCommandLog;
use App\Models\MikrotikConnectionLog;
use App\Models\MikrotikDevice;
use App\Services\Security\SensitiveDataSanitizer;
use App\Services\Security\SshKeyVault;
use Illuminate\Support\Facades\Auth;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use phpseclib3\Net\SSH2;
use RuntimeException;
use Throwable;

final class MikrotikSshService
{
    public function __construct(
        private readonly SshKeyVault $vault,
        private readonly RouterOsCommandBuilder $commands,
        private readonly SensitiveDataSanitizer $sanitizer,
        private readonly ?MikrotikSimulatorService $simulator = null,
    ) {}

    public function generateKeyPair(?int $bits = null): array
    {
        $bits ??= (int) config('mikrotik.ssh.key_bits', 3072);
        $key = RSA::createKey(max(2048, min($bits, 4096)));
        return [
            'private_key' => $key->toString('PKCS8'),
            'public_key' => $key->getPublicKey()->toString('OpenSSH'),
            'fingerprint' => $this->publicKeyFingerprint($key->getPublicKey()->toString('OpenSSH')),
        ];
    }

    public function validatePrivateKey(string $privateKey, ?string $passphrase = null): array
    {
        try {
            $key = PublicKeyLoader::loadPrivateKey($privateKey, $passphrase ?: false);
            $public = $key->getPublicKey()->toString('OpenSSH');

            return ['valid' => true, 'public_key' => $public, 'fingerprint' => $this->publicKeyFingerprint($public)];
        } catch (Throwable $exception) {
            return ['valid' => false, 'error' => 'Chave privada inválida ou passphrase incorreta.'];
        }
    }

    public function test(MikrotikDevice $device): array
    {
        if ($device->connection_method === 'simulator') {
            try {
                if (! $this->simulator) {
                    throw new RuntimeException('Simulador MikroTik indisponível no container de serviços.');
                }

                return $this->simulator->test($device);
            } catch (Throwable $exception) {
                return ['ok' => false, 'error' => $exception->getMessage()];
            }
        }

        $started = microtime(true);

        try {
            $ssh = $this->connect($device);
            $hostKey = (string) $ssh->getServerPublicHostKey();
            $fingerprint = $this->hostKeyFingerprint($hostKey);
            $this->assertHostFingerprint($device, $fingerprint);

            $output = $ssh->exec($this->commands->build('identity'));
            $identity = $this->parseIdentity($output);

            $device->forceFill([
                'status' => 'online',
                'last_seen_at' => now(),
                'last_connected_at' => now(),
                'last_error' => null,
                'ssh_host_fingerprint' => $device->ssh_host_fingerprint ?: $fingerprint,
                'router_identity' => $identity['identity'] ?? $device->router_identity,
                'router_model' => $identity['model'] ?? $device->router_model,
                'routerboard_name' => $identity['board_name'] ?? $device->routerboard_name,
                'routeros_version' => $identity['version'] ?? $device->routeros_version,
            ])->save();

            $this->logConnection($device, 'test', 'success', $started, $fingerprint, null, $identity);

            return ['ok' => true, 'output' => $this->sanitizer->excerpt($output), 'identity' => $identity, 'fingerprint' => $fingerprint];
        } catch (Throwable $exception) {
            $device->forceFill(['status' => 'offline', 'last_error' => mb_substr($exception->getMessage(), 0, 2000)])->save();
            $this->logConnection($device, 'test', 'failed', $started, null, $exception->getMessage());

            return ['ok' => false, 'error' => $exception->getMessage()];
        }
    }

    public function executeApproved(MikrotikDevice $device, string $commandKey, array $parameters = []): array
    {
        if ($device->connection_method === 'simulator') {
            try {
                if (! $this->simulator) {
                    throw new RuntimeException('Simulador MikroTik indisponível no container de serviços.');
                }

                return $this->simulator->executeApproved($device, $commandKey, $parameters);
            } catch (Throwable $exception) {
                return ['ok' => false, 'error' => $exception->getMessage()];
            }
        }

        $started = microtime(true);
        $command = '';

        try {
            $command = $this->commands->build($commandKey, $parameters);
            $ssh = $this->connect($device);
            $fingerprint = $this->hostKeyFingerprint((string) $ssh->getServerPublicHostKey());
            $this->assertHostFingerprint($device, $fingerprint);
            $ssh->setTimeout($device->ssh_command_timeout ?: 30);
            $output = (string) $ssh->exec($command);
            $status = method_exists($ssh, 'getExitStatus') ? $ssh->getExitStatus() : null;

            $this->logCommand($device, $commandKey, $command, 'success', $started, $status, $output);
            $device->forceFill([
                'status' => 'online',
                'last_seen_at' => now(),
                'last_connected_at' => now(),
                'last_error' => null,
                // TOFU controlado: após autenticação bem-sucedida, fixa a chave para as próximas conexões.
                'ssh_host_fingerprint' => $device->ssh_host_fingerprint ?: $fingerprint,
            ])->save();

            return ['ok' => true, 'output' => $output, 'exit_status' => $status];
        } catch (Throwable $exception) {
            $this->logCommand($device, $commandKey, $command ?: '[comando rejeitado antes da conexão]', 'failed', $started, null, '', $exception->getMessage());
            $device->forceFill(['status' => 'offline', 'last_error' => mb_substr($exception->getMessage(), 0, 2000)])->save();

            return ['ok' => false, 'error' => $exception->getMessage()];
        }
    }

    public function safeReadCommandKeys(): array
    {
        return $this->commands->safeReadKeys();
    }

    private function connect(MikrotikDevice $device): SSH2
    {
        if (! $device->active) throw new RuntimeException('O equipamento está desativado.');
        if (! $device->ssh_username) throw new RuntimeException('Usuário SSH não configurado.');

        $connectionTimeout = $device->ssh_connection_timeout ?: (int) config('mikrotik.ssh.connection_timeout', 10);
        $ssh = new SSH2($device->management_host, $device->ssh_port ?: 22, $connectionTimeout);
        $ssh->setTimeout($device->ssh_command_timeout ?: (int) config('mikrotik.ssh.command_timeout', 30));

        // A chave do host é conferida antes de qualquer credencial ser enviada.
        $hostKey = (string) $ssh->getServerPublicHostKey();
        if ($hostKey === '') {
            throw new RuntimeException('Não foi possível obter a chave pública do host SSH.');
        }
        $this->assertHostFingerprint($device, $this->hostKeyFingerprint($hostKey));

        $authenticated = false;

        $privateKey = $this->vault->decrypt($device->ssh_private_key_ciphertext);
        if ($privateKey) {
            $passphrase = $this->vault->decrypt($device->ssh_passphrase_ciphertext);
            $key = PublicKeyLoader::loadPrivateKey($privateKey, $passphrase ?: false);
            $authenticated = $ssh->login($device->ssh_username, $key);
        }

        $passwordFallbackAllowed = (bool) config('mikrotik.ssh.allow_password_fallback', false)
            && (bool) $device->ssh_password_fallback_enabled;

        if (! $authenticated && $passwordFallbackAllowed && $device->ssh_password_ciphertext) {
            $password = $this->vault->decrypt($device->ssh_password_ciphertext);
            $authenticated = $password ? $ssh->login($device->ssh_username, $password) : false;
        }

        if (! $authenticated) {
            $suffix = $passwordFallbackAllowed
                ? ' O fallback por senha também falhou.'
                : ' O fallback por senha está desativado.';
            throw new RuntimeException('Falha de autenticação SSH por chave. Revise usuário, chave pública no MikroTik e passphrase.'.$suffix);
        }

        return $ssh;
    }

    private function assertHostFingerprint(MikrotikDevice $device, string $actual): void
    {
        $expected = trim((string) $device->ssh_host_fingerprint);
        if ($expected === '' && (bool) config('mikrotik.ssh.require_host_fingerprint', false)) {
            throw new RuntimeException('Fingerprint do host SSH obrigatório. Cadastre-o antes da primeira conexão.');
        }

        if ($expected !== '' && ! hash_equals($expected, $actual)) {
            throw new RuntimeException('A chave do host SSH mudou. Conexão bloqueada para evitar ataque man-in-the-middle.');
        }
    }

    private function publicKeyFingerprint(string $publicKey): string
    {
        $parts = preg_split('/\s+/', trim($publicKey));
        $raw = isset($parts[1]) ? base64_decode($parts[1], true) : false;
        return 'SHA256:'.rtrim(base64_encode(hash('sha256', $raw ?: $publicKey, true)), '=');
    }

    private function hostKeyFingerprint(string $hostKey): string
    {
        $parts = preg_split('/\s+/', trim($hostKey));
        $raw = isset($parts[1]) ? base64_decode($parts[1], true) : false;

        return 'SHA256:'.rtrim(base64_encode(hash('sha256', $raw ?: $hostKey, true)), '=');
    }

    private function parseIdentity(string $output): array
    {
        $result = [];
        foreach (preg_split('/\R+/', $output) as $line) {
            if (preg_match('/name=("[^"]*"|\S+)/', $line, $m) && ! isset($result['identity'])) $result['identity'] = trim($m[1], '"');
            if (preg_match('/version=("[^"]*"|\S+)/', $line, $m)) $result['version'] = trim($m[1], '"');
            if (preg_match('/board-name=("[^"]*"|\S+)/', $line, $m)) $result['board_name'] = trim($m[1], '"');
            if (preg_match('/model=("[^"]*"|\S+)/', $line, $m)) $result['model'] = trim($m[1], '"');
        }
        return $result;
    }

    private function logConnection(MikrotikDevice $device, string $operation, string $result, float $started, ?string $fingerprint = null, ?string $error = null, array $metadata = []): void
    {
        MikrotikConnectionLog::query()->create([
            'tenant_id' => $device->tenant_id, 'company_id' => $device->company_id,
            'mikrotik_device_id' => $device->id, 'user_id' => Auth::id(),
            'operation' => $operation, 'result' => $result,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'remote_address' => $device->management_host.':'.$device->ssh_port,
            'fingerprint' => $fingerprint, 'error_message' => $error ? $this->sanitizer->excerpt($error, 2000) : null,
            'metadata' => $metadata ?: null, 'created_at' => now(),
        ]);
    }

    private function logCommand(MikrotikDevice $device, string $key, string $command, string $result, float $started, ?int $exitStatus, string $output, ?string $error = null): void
    {
        MikrotikCommandLog::query()->create([
            'tenant_id' => $device->tenant_id, 'company_id' => $device->company_id,
            'mikrotik_device_id' => $device->id, 'user_id' => Auth::id(),
            'command_key' => $key, 'command_preview' => $this->sanitizer->excerpt($command, 2000),
            'result' => $result, 'exit_status' => $exitStatus,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'output_excerpt' => $this->sanitizer->excerpt($output),
            'error_message' => $error ? $this->sanitizer->excerpt($error, 2000) : null,
            'created_at' => now(),
        ]);
    }
}
