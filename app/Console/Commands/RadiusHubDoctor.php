<?php

namespace App\Console\Commands;

use App\Models\MikrotikDevice;
use App\Models\NetworkAccess;
use App\Services\Security\RadiusCredentialVault;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class RadiusHubDoctor extends Command
{
    protected $signature = 'radiushub:doctor {--strict : Retorna erro quando um recurso opcional falhar}';
    protected $description = 'Valida ambiente, banco, cache, fila, credenciais RADIUS, SDK Asaas e binários.';

    public function handle(RadiusCredentialVault $vault): int
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

        $legacyAccesses = NetworkAccess::query()->withoutGlobalScopes()->get(['id', 'password_ciphertext'])->filter(fn ($item) => ! $vault->isRadiusReadable((string) $item->password_ciphertext))->count();
        $legacyDevices = MikrotikDevice::query()->withoutGlobalScopes()->get(['id', 'radius_secret_ciphertext'])->filter(fn ($item) => ! $vault->isRadiusReadable((string) $item->radius_secret_ciphertext))->count();
        if ($legacyAccesses + $legacyDevices > 0) {
            $this->warn("Existem {$legacyAccesses} acessos e {$legacyDevices} NAS com criptografia legada. Execute radiushub:credentials:reencrypt.");
            $warnings++;
        } else {
            $this->components->twoColumnDetail('Credenciais legíveis pelo FreeRADIUS', '<fg=green>OK</>');
        }

        $radclient = (string) config('radius.radclient_binary');
        if (! is_executable($radclient)) {
            $this->warn('radclient não encontrado/executável em '.$radclient.'. CoA e Disconnect não funcionarão.');
            $warnings++;
        } else {
            $this->components->twoColumnDetail('radclient', '<fg=green>'.$radclient.'</>');
        }

        $this->newLine();
        $this->line("Erros: {$errors}; avisos: {$warnings}.");

        return $errors > 0 || ($this->option('strict') && $warnings > 0) ? self::FAILURE : self::SUCCESS;
    }
}
