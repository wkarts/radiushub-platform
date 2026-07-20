<?php

use App\Http\Middleware\EnsureBoundModelsBelongToContext;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureTenantPermission;
use App\Http\Middleware\SetCurrentCompany;
use App\Http\Middleware\SetCurrentTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')->prefix('api')->name('api.')->group(base_path('routes/api.php'));
            Route::middleware('api')->prefix('webhooks')->name('webhooks.')->group(base_path('routes/webhooks.php'));
            Route::middleware('api')->group(base_path('routes/health.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $trusted = trim((string) env('TRUSTED_PROXIES', ''));
        if ($trusted !== '') {
            $middleware->trustProxies(
                at: $trusted === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $trusted)))),
                headers: Request::HEADER_X_FORWARDED_FOR
                    | Request::HEADER_X_FORWARDED_HOST
                    | Request::HEADER_X_FORWARDED_PORT
                    | Request::HEADER_X_FORWARDED_PROTO
                    | Request::HEADER_X_FORWARDED_PREFIX,
            );
        }

        $middleware->alias([
            'tenant' => SetCurrentTenant::class,
            'company' => SetCurrentCompany::class,
            'permission' => EnsurePermission::class,
            'password.changed' => EnsurePasswordChanged::class,
            'role' => EnsureRole::class,
            'tenant.permission' => EnsureTenantPermission::class,
            'scope.bindings' => EnsureBoundModelsBelongToContext::class,
        ]);

        $middleware->validateCsrfTokens(except: ['webhooks/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Tratamento central pode ser expandido sem alterar controllers.
    })
    ->create();
