<?php

namespace App\Http\Controllers\Health;

use Illuminate\Http\JsonResponse;

final class LivenessController
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'radiushub',
            'version' => (string) config('app.version'),
            'timestamp' => now()->toIso8601String(),
        ])->header('Cache-Control', 'no-store');
    }
}
