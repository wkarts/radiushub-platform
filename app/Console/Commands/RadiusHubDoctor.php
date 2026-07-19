<?php

namespace App\Console\Commands;

use App\Models\MikrotikDevice;
use App\Models\NetworkAccess;
use App\Services\Security\RadiusCredentialVault;
use App\Services\Security\SshKeyVault;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class RadiusHubDoctor extends Command
{
    protected $signature = 'radiushub:doctor {--strict : Retorna erro quando um recurso opcional falhar}';
    protected $description = 'Valida ambiente, banco, cache, fila, credenciais RADIUS, SDK Asaas e binários.';

    public function handle(RadiusCredentialVault $vault, SshKeyVault $sshVault): int
    {
        $errors = 0;
        $warnings = 0;
        $this->components->info('RadiusHub Doctor '.config('app.version'));

        $checks = [
            'APP_KEY' => fn () => filled(config('app.key')),
            'RADIUS_CREDENTIAL_KEY' => fn () => strlen((string) config('radius.credential_key')) >= 32 && ! str_starts_with((string) config('radius.credential_key'), 'change-this'),
            'RADIUS_LOCAL_SECRET' => fn () => strlen((string) config('radius.local_secret')) >= 16 && ! str_starts_with((string) config('radius.local_secret'), 'change-this'),
            'Banco de dados' => function (): bool { DB::select('select 1'); return true; },
            'Cache' => function (): bool { Cache::put('radiushub:doctor', 'ok', 30); return Cache::pull('radiushub:doctor') === 'ok'; },
            'SDK Asaas ARGWS' => fn () => class_exists(\Asaas\Sdk\AsaasSdk::class),
            'SSH phpseclib' => fn () => class_exists(\phpseclib3\Net\SSH2::class),
            'PDF DomPDF' => fn () => class_exists(\Barryvdh\DomPDF\Facade\Pdf::class),
            'TOTP Google2FA' => fn () => class_exists(\PragmaRX\Google2FA\Google2FA::class),
            'Diretório storage gravável' => fn () => is_writable(storage_path()),
            'Diretório bootstrap/cache gravável' => fn () => is_writable(base_path('bootstrap/cache')),
        ];

        foreach ($checks as $name => $callback) {
            try {
                $ok = (bool) $callback();
                $ok ? $this->components->twoColumnDetail($name, '<fg=green>OK</>') : $this->components->twoColumnDetail($name, '<fg=red>FALHA</>');
                if (! $ok) $errors++;
            } catch (Throwable $e) {
                $this->components->twoColumnDetail($name, '<fg=red>'.$e->getMessage().'</>');
                $errors++;
            }
        }

        if (config('queue.default') === 'database' && ! Schema::hasTable((string) config('queue.connections.database.table', 'jobs'))) {
            $this->error('A fila usa database, mas a tabela jobs não existe. Execute as migrations.');
            $errors++;
        }

        if (config('session.driver') === 'database' && ! Schema::hasTable('sessions')) {
            $this->error('A sessão usa database, mas a tabela sessions não existe. Execute as migrations.');
            $errors++;
        }

        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['mysql', 'pgsql'], true)) {
            $this->warn('FreeRADIUS em produção exige MySQL 8+ ou PostgreSQL 15+. Driver atual: '.$driver);
            $warnings++;
        }

        $allDevices = MikrotikDevice::query()->withoutGlobalScopes(['tenant', 'company'])->where('active', true)->get();
        $sshDevices = $allDevices->where('connection_method', 'ssh');
        $simulatedDevices = $allDevices->where('connection_method', 'simulator');

        if (config('playground.enabled')) {
            $this->components->twoColumnDetail('Modo playground', '<fg=yellow>habilitado</>');
            if (app()->environment('production') && ! config('playground.allow_production')) {
                $this->error('PLAYGROUND_MODE está habilitado em produção sem autorização explícita.');
                $errors++;
            }
        } elseif ($simulatedDevices->isNotEmpty()) {
            $this->error('Existem equipamentos simulados fora do modo playground.');
            $errors++;
        }

        if ($simulatedDevices->isNotEmpty()) {
            $this->components->twoColumnDetail('MikroTik simulados', '<fg=yellow>'.$simulatedDevices->count().'</>');
        }

        $missingSshKeys = $sshDevices->whereNull('ssh_private_key_ciphertext')->count();
        $invalidSshKeys = $sshDevices->filter(function (MikrotikDevice $device) use ($sshVault): bool {
            if (! $device->ssh_private_key_ciphertext) return false;
            try { return trim((string) $sshVault->decrypt($device->ssh_private_key_ciphertext)) === ''; }
            catch (Throwable) { return true; }
        })->count();
        $missingFingerprints = $sshDevices->whereNull('ssh_host_fingerprint')->count();
        $passwordFallbacks = $sshDevices->where('ssh_password_fallback_enabled', true)->count();

        if ($missingSshKeys > 0 || $invalidSshKeys > 0) {
            $this->warn("MikroTik SSH: {$missingSshKeys} sem chave privada e {$invalidSshKeys} com chave ilegível.");
            $warnings++;
        } else {
            $this->components->twoColumnDetail('Chaves privadas SSH criptografadas', '<fg=green>OK</>');
        }

        if ($missingFingerprints > 0) {
            $this->warn("Existem {$missingFingerprints} MikroTik(s) ativos sem fingerprint do host fixada.");
            $warnings++;
        }

        if ($passwordFallbacks > 0) {
            $this->warn("Existem {$passwordFallbacks} MikroTik(s) com fallback por senha habilitado.");
            $warnings++;
        }

        if ((string) config('mikrotik.session_control.driver', 'ssh') !== 'ssh') {
            $this->error('MIKROTIK_SESSION_CONTROL_DRIVER deve permanecer como ssh nesta versão.');
            $errors++;
        } else {
            $this->components->twoColumnDetail('Controle administrativo de sessões', '<fg=green>SSH</>');
        }

        if ((bool) config('mikrotik.ssh.allow_password_fallback')) {
            $this->warn('MIKROTIK_SSH_ALLOW_PASSWORD_FALLBACK está habilitado globalmente. Use somente em contingência.');
            $warnings++;
        }

        $legacyAccesses = NetworkAccess::query()->withoutGlobalScopes(['tenant', 'company'])->get(['id', 'password_ciphertext'])->filter(fn ($item) => ! $vault->isRadiusReadable((string) $item->password_ciphertext))->count();
        $legacyDevices = MikrotikDevice::query()->withoutGlobalScopes(['tenant', 'company'])->get(['id', 'radius_secret_ciphertext'])->filter(fn ($item) => ! $vault->isRadiusReadable((string) $item->radius_secret_ciphertext))->count();
        if ($legacyAccesses + $legacyDevices > 0) {
            $this->warn("Existem {$legacyAccesses} acessos e {$legacyDevices} NAS com criptografia legada. Execute radiushub:credentials:reencrypt.");
            $warnings++;
        } else {
            $this->components->twoColumnDetail('Credenciais legíveis pelo FreeRADIUS', '<fg=green>OK</>');
        }

        $radclient = (string) config('radius.radclient_binary');
        if ((bool) config('mikrotik.session_control.allow_coa_fallback', false)) {
            if (! is_executable($radclient)) {
                $this->warn('Fallback CoA está habilitado, mas radclient não foi encontrado em '.$radclient.'.');
                $warnings++;
            } else {
                $this->components->twoColumnDetail('Fallback CoA/radclient', '<fg=green>'.$radclient.'</>');
            }
        } else {
            $this->components->twoColumnDetail('Fallback CoA', '<fg=green>desabilitado</>');
        }

        $this->newLine();
        $this->line("Erros: {$errors}; avisos: {$warnings}.");

        return $errors > 0 || ($this->option('strict') && $warnings > 0) ? self::FAILURE : self::SUCCESS;
    }
}
