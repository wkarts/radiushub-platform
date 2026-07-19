<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureBoundModelsBelongToContext
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly CompanyContext $company,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = $this->tenant->id();
        $companyId = $this->company->id();

        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if (! $parameter instanceof Model) {
                continue;
            }

            if ($tenantId !== null && $parameter->getAttribute('tenant_id') !== null) {
                abort_unless(
                    hash_equals((string) $tenantId, (string) $parameter->getAttribute('tenant_id')),
                    404,
                );
            }

            if ($companyId !== null && $parameter->getAttribute('company_id') !== null) {
                abort_unless(
                    hash_equals((string) $companyId, (string) $parameter->getAttribute('company_id')),
                    404,
                );
            }
        }

        return $next($request);
    }
}
