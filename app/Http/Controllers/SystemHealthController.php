<?php

namespace App\Http\Controllers;

use App\Models\MikrotikDevice;
use App\Models\RadiusAuthAttempt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\View;
use Throwable;

class SystemHealthController extends Controller
{
    public function __invoke(): View
    {
        $checks = [];
        $driver = (string) config('database.default');

        try {
            DB::select('select 1');
            $checks['database'] = ['ok' => true, 'message' => strtoupper($driver).' conectado'];
        } catch (Throwable $e) {
            $checks['database'] = ['ok' => false, 'message' => $this->safeMessage($e)];
        }

        try {
            $key = 'radiushub:health:'.bin2hex(random_bytes(4));
            Cache::put($key, 'ok', 30);
            $ok = Cache::pull($key) === 'ok';
            $checks['cache'] = ['ok' => $ok, 'message' => 'Store: '.config('cache.default')];
        } catch (Throwable $e) {
            $checks['cache'] = ['ok' => false, 'message' => $this->safeMessage($e)];
        }

        $usesRedis = in_array(config('cache.default'), ['redis', 'failover'], true)
            || config('queue.default') === 'redis';
        if ($usesRedis) {
            try {
                Redis::ping();
                $checks['redis'] = ['ok' => true, 'message' => 'Redis conectado'];
            } catch (Throwable $e) {
                $checks['redis'] = ['ok' => false, 'message' => $this->safeMessage($e)];
            }
        } else {
            $checks['redis'] = ['ok' => true, 'message' => 'Opcional; cache/fila usam '.config('cache.default').'/'.config('queue.default')];
        }

        $lastAuth = RadiusAuthAttempt::query()->max('created_at');
        $checks['radius'] = [
            'ok' => $lastAuth !== null,
            'message' => 'Última autenticação: '.($lastAuth ?? 'nenhuma'),
        ];
        $checks['queue'] = ['ok' => true, 'message' => 'Conexão: '.config('queue.default')];
        $checks['asaas_sdk'] = [
            'ok' => class_exists(\Asaas\Sdk\AsaasSdk::class),
            'message' => class_exists(\Asaas\Sdk\AsaasSdk::class) ? 'SDK ARGWS carregado' : 'SDK não carregado',
        ];

        $devices = MikrotikDevice::query()->orderBy('name')->get();

        return view('system.health', compact('checks', 'devices'));
    }

    private function safeMessage(Throwable $e): string
    {
        return app()->isProduction() ? class_basename($e) : $e->getMessage();
    }
}
