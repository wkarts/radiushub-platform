<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompanyRequest;
use App\Models\Company;
use App\Models\Role;
use App\Services\Audit\AuditLogger;
use App\Services\Companies\CompanyProvisioningService;
use App\Services\Limits\UsageLimitService;
use App\Services\Tenancy\TenantContext;
use App\Support\Search;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(Request $request, TenantContext $tenant): View
    {
        $this->authorize('viewAny', Company::class);

        $sort = in_array($request->string('sort')->toString(), ['legal_name', 'trade_name', 'document', 'status', 'created_at'], true)
            ? $request->string('sort')->toString()
            : 'legal_name';
        $direction = $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc';

        $companies = Company::query()
            ->where('tenant_id', $tenant->requireId())
            ->when($request->filled('q'), fn ($q) => Search::contains($q, 'legal_name', (string) $request->string('q')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy($sort, $direction)
            ->paginate(25)
            ->withQueryString();

        $roles = Role::query()
            ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id()))
            ->where('scope', 'company')->where('active', true)->orderBy('name')->get();

        return view('companies.index', compact('companies', 'roles'));
    }

    public function store(CompanyRequest $request, TenantContext $tenant, CompanyProvisioningService $service, UsageLimitService $limits): RedirectResponse
    {
        $this->authorize('create', Company::class);
        $limits->assertTenant($tenant->tenant(), 'companies');
        $data = $request->validated();
        $data['tenant_id'] = $tenant->requireId();
        $data['active'] = $request->boolean('active') && $data['status'] === 'active';

        $company = $service->create($data);

        return back()->with('success', "Empresa {$company->legal_name} criada com sucesso.");
    }

    public function update(CompanyRequest $request, Company $company, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $company);
        $old = $company->toArray();
        $data = collect($request->validated())->except([
            'create_admin', 'admin_name', 'admin_email', 'admin_login',
            'admin_password', 'admin_password_confirmation', 'admin_role_id', 'send_password_link', 'usage_limits_json',
        ])->all();
        $data['active'] = $request->boolean('active') && $data['status'] === 'active';
        $company->update($data);
        $audit->record('company.updated', $company, $old, $company->fresh()->toArray());

        return back()->with('success', 'Empresa atualizada.');
    }

    public function destroy(Company $company, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('delete', $company);
        abort_if($company->users()->exists() || $company->mikrotiks()->exists(), 422, 'A empresa possui usuários ou equipamentos vinculados.');
        $audit->record('company.deleted', $company, $company->toArray(), []);
        $company->delete();

        return back()->with('success', 'Empresa removida.');
    }
}
