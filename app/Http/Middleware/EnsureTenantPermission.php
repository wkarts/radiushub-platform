<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantPermission
{
    private const WRITE_MODULES = [
        'tenant_admin' => ['*'],
        'network_admin' => ['plans', 'mikrotiks', 'accesses', 'sessions'],
        'billing' => ['subscribers', 'contracts', 'invoices', 'gateways'],
        'operator' => ['subscribers', 'contracts', 'accesses', 'sessions', 'invoices'],
        'viewer' => [],
    ];

    public function __construct(private readonly TenantContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        if ($user->is_super_admin) {
            return $next($request);
        }

        $role = $user->roleForTenant($this->context->id());
        abort_unless($role, 403, 'O usuário não possui vínculo com a empresa atual.');

        $routeName = (string) $request->route()?->getName();

        if (str_starts_with($routeName, 'users.')) {
            abort_unless($role === 'tenant_admin', 403, 'Somente o administrador da empresa pode gerenciar usuários.');
            return $next($request);
        }

        if ($request->isMethodSafe()) {
            return $next($request);
        }
        $module = explode('.', $routeName)[0] ?? '';
        $allowed = self::WRITE_MODULES[$role] ?? [];

        abort_unless(
            in_array('*', $allowed, true) || in_array($module, $allowed, true),
            403,
            'Seu perfil possui acesso somente de consulta ou não autoriza esta operação.'
        );

        return $next($request);
    }
}
