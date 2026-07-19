<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanySwitchController extends Controller
{
    public function __invoke(Request $request, TenantContext $tenant): RedirectResponse
    {
        $data = $request->validate(['company_id' => ['required', 'uuid']]);
        $company = Company::query()->where('tenant_id', $tenant->requireId())->whereKey($data['company_id'])->where('active', true)->firstOrFail();

        $role = $request->user()->roleForTenant($tenant->id());
        abort_unless(
            $request->user()->is_super_admin
            || $role === 'tenant_admin'
            || $request->user()->companies()->whereKey($company->id)->wherePivot('active', true)->exists(),
            403
        );

        $request->session()->put(config('tenancy.company_session_key'), $company->id);

        return back();
    }
}
