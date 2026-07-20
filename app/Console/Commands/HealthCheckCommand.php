<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class HealthCheckCommand extends Command
{
    protected $signature = 'radiushub:health {--ready : Verifica dependências necessárias para receber tráfego}';
    protected $description = 'Executa verificações de liveness/readiness para deploy e orquestração.';

    public function handle(): int
    {
        if (! $this->option('ready')) {
            if (! $this->output->isQuiet()) {
                $this->info('RadiusHub está ativo.');
            }

            return self::SUCCESS;
        }

        $checks = [
            'database' => fn (): bool => (int) DB::selectOne('SELECT 1 AS ok')->ok === 1,
            'cache' => function (): bool {
                $key = 'radiushub:cli-health:'.bin2hex(random_bytes(6));
                Cache::put($key, 'ok', 10);
                $ok = Cache::get($key) === 'ok';
                Cache::forget($key);

                return $ok;
            },
            'storage' => fn (): bool => is_dir(storage_path('framework/cache/data')) && is_writable(storage_path('framework/cache/data')),
        ];

        foreach ($checks as $name => $check) {
            try {
                if (! $check()) {
                    throw new \RuntimeException('retornou estado inválido');
                }

                if (! $this->output->isQuiet()) {
                    $this->line("[OK] {$name}");
                }
            } catch (Throwable $exception) {
                $this->error("[FALHA] {$name}: {$exception->getMessage()}");

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
