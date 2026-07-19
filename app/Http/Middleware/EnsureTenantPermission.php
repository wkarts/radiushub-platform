<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantPermission
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly CompanyContext $company,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 401);
        if ($user->is_super_admin) return $next($request);

        $route = (string) $request->route()?->getName();
        $module = explode('.', $route)[0] ?: 'dashboard';
        $action = $request->isMethodSafe() ? 'view' : 'manage';

        if (str_contains($route, '.test') || str_contains($route, '.execute') || str_contains($route, '.sync')) {
            $action = $module === 'mikrotiks' ? 'execute' : 'manage';
        }

        $permission = $module === 'dashboard' ? 'dashboard.view' : $module.'.'.$action;

        abort_unless(
            $user->hasPermission($permission, $this->tenant->id(), $this->company->id()),
            403,
            'Seu perfil não autoriza esta operação na empresa atual.'
        );

        return $next($request);
    }
}
