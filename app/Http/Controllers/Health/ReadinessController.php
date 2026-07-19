<?php

namespace App\Http\Controllers\Health;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ReadinessController
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => fn (): bool => (int) DB::selectOne('SELECT 1 AS ok')->ok === 1,
            'cache' => function (): bool {
                $key = 'radiushub:health:'.bin2hex(random_bytes(8));
                Cache::put($key, 'ok', 10);
                $ok = Cache::get($key) === 'ok';
                Cache::forget($key);

                return $ok;
            },
            'storage' => function (): bool {
                $directory = storage_path('framework/cache/data');

                return is_dir($directory) && is_writable($directory);
            },
        ];

        $result = [];
        $ready = true;

        foreach ($checks as $name => $check) {
            try {
                $ok = (bool) $check();
                $result[$name] = $ok ? 'ok' : 'failed';
                $ready = $ready && $ok;
            } catch (Throwable) {
                $result[$name] = 'failed';
                $ready = false;
            }
        }

        return response()->json([
            'status' => $ready ? 'ready' : 'unavailable',
            'service' => 'radiushub',
            'version' => (string) config('app.version'),
            'checks' => $result,
            'timestamp' => now()->toIso8601String(),
        ], $ready ? 200 : 503)->header('Cache-Control', 'no-store');
    }
}
