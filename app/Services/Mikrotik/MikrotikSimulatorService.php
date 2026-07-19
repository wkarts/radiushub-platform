<?php

namespace App\Services\Mikrotik;

use App\Models\MikrotikCommandLog;
use App\Models\MikrotikConnectionLog;
use App\Models\MikrotikDevice;
use App\Services\Security\SensitiveDataSanitizer;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

final class MikrotikSimulatorService
{
    public function __construct(
        private readonly RouterOsCommandBuilder $commands,
        private readonly SensitiveDataSanitizer $sanitizer,
    ) {}

    public function test(MikrotikDevice $device): array
    {
        $this->assertEnabled($device);
        $started = microtime(true);
        $fingerprint = 'SHA256:'.rtrim(base64_encode(hash('sha256', 'radiushub-playground-host', true)), '=');
        $identity = [
            'identity' => 'RadiusHub-Playground',
            'model' => 'CHR-SIMULATOR',
            'board_name' => 'Cloud Hosted Router',
            'version' => '7.16-playground',
        ];

        $device->forceFill([
            'status' => 'online',
            'last_seen_at' => now(),
            'last_connected_at' => now(),
            'last_error' => null,
            'ssh_host_fingerprint' => $fingerprint,
            'router_identity' => $identity['identity'],
            'router_model' => $identity['model'],
            'routerboard_name' => $identity['board_name'],
            'routeros_version' => $identity['version'],
        ])->save();

        MikrotikConnectionLog::query()->create([
            'tenant_id' => $device->tenant_id,
            'company_id' => $device->company_id,
            'mikrotik_device_id' => $device->id,
            'user_id' => Auth::id(),
            'operation' => 'test-simulator',
            'result' => 'success',
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'remote_address' => 'simulator://'.$device->management_host,
            'fingerprint' => $fingerprint,
            'metadata' => ['playground' => true, 'identity' => $identity],
            'created_at' => now(),
        ]);

        return [
            'ok' => true,
            'output' => 'name=RadiusHub-Playground version=7.16-playground board-name="Cloud Hosted Router" model=CHR-SIMULATOR',
            'identity' => $identity,
            'fingerprint' => $fingerprint,
            'simulated' => true,
        ];
    }

    public function executeApproved(MikrotikDevice $device, string $commandKey, array $parameters = []): array
    {
        $this->assertEnabled($device);
        $started = microtime(true);
        $command = $this->commands->build($commandKey, $parameters);
        $output = $this->outputFor($commandKey, $parameters);

        MikrotikCommandLog::query()->create([
            'tenant_id' => $device->tenant_id,
            'company_id' => $device->company_id,
            'mikrotik_device_id' => $device->id,
            'user_id' => Auth::id(),
            'command_key' => $commandKey,
            'command_preview' => $this->sanitizer->excerpt($command, 2000),
            'result' => 'success',
            'exit_status' => 0,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'output_excerpt' => $this->sanitizer->excerpt($output),
            'created_at' => now(),
        ]);

        $device->forceFill([
            'status' => 'online',
            'last_seen_at' => now(),
            'last_connected_at' => now(),
            'last_sync_at' => str_starts_with($commandKey, 'sync-') ? now() : $device->last_sync_at,
            'last_error' => null,
        ])->save();

        return ['ok' => true, 'output' => $output, 'exit_status' => 0, 'simulated' => true];
    }

    private function assertEnabled(MikrotikDevice $device): void
    {
        if (! config('playground.enabled') || ! config('playground.mikrotik_simulator')) {
            throw new RuntimeException('O simulador MikroTik está disponível somente no modo playground explicitamente habilitado.');
        }

        if ($device->connection_method !== 'simulator') {
            throw new RuntimeException('O equipamento não está configurado para o transporte simulado.');
        }
    }

    private function outputFor(string $key, array $parameters): string
    {
        return match ($key) {
            'identity' => 'name=RadiusHub-Playground\nversion=7.16-playground board-name="Cloud Hosted Router" model=CHR-SIMULATOR',
            'health' => 'uptime=1d2h3m version=7.16-playground cpu-load=7% free-memory=384MiB total-memory=512MiB',
            'hotspot-users' => '0 name="voucher-demo" profile="Playground 100M" disabled=no',
            'ppp-secrets' => '0 name="cliente.demo" service=pppoe profile="Playground 100M" disabled=no',
            'hotspot-profiles' => '0 name="Playground 100M" rate-limit="50M/100M" shared-users=2',
            'ppp-profiles' => '0 name="Playground 100M" rate-limit="50M/100M"',
            'active-sessions' => '0 user="cliente.demo" address=10.10.10.10 uptime=00:42:00',
            default => 'ok command='.$key.' parameters='.$this->sanitizer->sanitize((string) json_encode($parameters, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
        };
    }
}
