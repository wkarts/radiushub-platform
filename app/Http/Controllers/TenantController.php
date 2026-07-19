<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantRequest;
use App\Models\Tenant;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function index(): View
    {
        return view('tenants.index', [
            'tenants' => Tenant::query()
                ->withCount(['companies', 'users'])
                ->orderBy('name')
                ->paginate(25),
        ]);
    }

    public function store(TenantRequest $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->safe()->except('usage_limits_json');
        $data['active'] = $request->boolean('active') && $data['status'] === 'active';
        $tenant = Tenant::query()->create($data);
        $audit->record('tenant.created', $tenant, [], $tenant->toArray());

        return back()->with('success', 'Tenant cadastrado.');
    }

    public function update(TenantRequest $request, Tenant $tenant, AuditLogger $audit): RedirectResponse
    {
        $old = $tenant->toArray();
        $data = $request->safe()->except('usage_limits_json');
        $data['active'] = $request->boolean('active') && $data['status'] === 'active';
        $tenant->update($data);
        $audit->record('tenant.updated', $tenant, $old, $tenant->fresh()->toArray());

        return back()->with('success', 'Tenant atualizado.');
    }

    public function destroy(Tenant $tenant, AuditLogger $audit): RedirectResponse
    {
        abort_if(
            $tenant->users()->exists() || $tenant->subscribers()->exists() || $tenant->companies()->exists(),
            422,
            'O tenant possui vínculos e não pode ser excluído.',
        );

        $audit->record('tenant.deleted', $tenant, $tenant->toArray(), []);
        $tenant->delete();

        return back()->with('success', 'Tenant excluído.');
    }
}
