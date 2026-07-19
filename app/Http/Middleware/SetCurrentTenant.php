<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentTenant
{
    public function __construct(private readonly TenantContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        $requestedId = $request->header(config('tenancy.header'))
            ?: $request->session()->get(config('tenancy.session_key'));

        $tenants = $user->is_super_admin
            ? Tenant::query()->where('active', true)
            : $user->tenants()->where('tenants.active', true);

        $tenant = $requestedId ? (clone $tenants)->whereKey($requestedId)->first() : null;
        $tenant ??= $tenants->first();

        abort_unless($tenant, 403, 'O usuário não possui tenant ativo vinculado.');

        $request->session()->put(config('tenancy.session_key'), $tenant->getKey());
        $this->context->set($tenant);

        View::share('currentTenant', $tenant);
        View::share('availableTenants', $user->is_super_admin
            ? Tenant::query()->where('active', true)->orderBy('name')->get()
            : $user->tenants()->where('tenants.active', true)->orderBy('name')->get());

        try {
            return $next($request);
        } finally {
            $this->context->clear();
        }
    }
}
