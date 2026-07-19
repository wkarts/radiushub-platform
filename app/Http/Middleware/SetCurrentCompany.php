<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentCompany
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly CompanyContext $company,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        $requestedId = $request->header(config('tenancy.company_header'))
            ?: $request->session()->get(config('tenancy.company_session_key'));

        $base = Company::query()->where('tenant_id', $this->tenant->requireId())->where('active', true);

        $tenantRole = $user->roleForTenant($this->tenant->id());
        if (! $user->is_super_admin && $tenantRole !== 'tenant_admin') {
            $allowedIds = $user->companies()->wherePivot('active', true)->pluck('companies.id');
            $base->whereIn('id', $allowedIds);
        }

        $company = $requestedId ? (clone $base)->whereKey($requestedId)->first() : null;
        $company ??= $base->orderBy('legal_name')->first();

        abort_unless($company, 403, 'O usuário não possui empresa ativa vinculada.');

        $request->session()->put(config('tenancy.company_session_key'), $company->getKey());
        $this->company->set($company);

        $available = Company::query()
            ->where('tenant_id', $this->tenant->requireId())
            ->where('active', true)
            ->when(! $user->is_super_admin && $tenantRole !== 'tenant_admin', function ($query) use ($user): void {
                $query->whereIn('id', $user->companies()->wherePivot('active', true)->pluck('companies.id'));
            })
            ->orderBy('legal_name')
            ->get();

        View::share('currentCompany', $company);
        View::share('availableCompanies', $available);

        try {
            return $next($request);
        } finally {
            $this->company->clear();
        }
    }
}
