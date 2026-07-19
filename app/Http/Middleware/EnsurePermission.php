<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly CompanyContext $company,
    ) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        abort_unless(
            $user->hasPermission($permission, $this->tenant->id(), $this->company->id()),
            403,
            'Você não possui permissão para esta operação.'
        );

        return $next($request);
    }
}
