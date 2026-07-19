<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        if ($user->is_super_admin) {
            return $next($request);
        }

        $tenantId = $request->session()->get(config('tenancy.session_key'));
        $role = $user->roleForTenant($tenantId);

        abort_unless(in_array($role, $roles, true), 403, 'Você não possui permissão para esta operação.');

        return $next($request);
    }
}
