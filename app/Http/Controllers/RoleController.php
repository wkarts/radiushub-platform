<?php

namespace App\Http\Controllers;

use App\Http\Requests\RoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Audit\AuditLogger;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(TenantContext $tenant): View
    {
        $this->ensureTenantAdministrator($tenant);
        $roles = Role::query()->with('permissions')->where('tenant_id', $tenant->requireId())->orderBy('name')->paginate(25);
        $permissions = Permission::query()->orderBy('module')->orderBy('name')->get()->groupBy('module');
        return view('roles.index', compact('roles', 'permissions'));
    }

    public function store(RoleRequest $request, TenantContext $tenant, AuditLogger $audit): RedirectResponse
    {
        $this->ensureTenantAdministrator($tenant);
        $role = Role::query()->create($request->safe()->except('permissions') + [
            'tenant_id' => $tenant->requireId(), 'active' => $request->boolean('active'),
        ]);
        $ids = Permission::query()->whereIn('slug', $request->input('permissions', []))->pluck('id');
        $role->permissions()->sync($ids);
        $audit->record('role.created', $role, [], $role->load('permissions')->toArray());

        return back()->with('success', 'Perfil criado.');
    }

    public function update(RoleRequest $request, Role $role, AuditLogger $audit, TenantContext $tenant): RedirectResponse
    {
        $this->ensureTenantAdministrator($tenant);
        abort_unless($role->tenant_id === $tenant->requireId(), 404);
        abort_if($role->is_system, 422, 'Perfis de sistema não podem ser alterados por esta tela.');
        $old = $role->load('permissions')->toArray();
        $role->update($request->safe()->except('permissions') + ['active' => $request->boolean('active')]);
        $ids = Permission::query()->whereIn('slug', $request->input('permissions', []))->pluck('id');
        $role->permissions()->sync($ids);
        $audit->record('role.updated', $role, $old, $role->fresh()->load('permissions')->toArray());

        return back()->with('success', 'Perfil atualizado.');
    }

    public function destroy(Role $role, AuditLogger $audit, TenantContext $tenant): RedirectResponse
    {
        $this->ensureTenantAdministrator($tenant);
        abort_unless($role->tenant_id === $tenant->requireId(), 404);
        abort_if($role->is_system, 422, 'Perfil de sistema não pode ser excluído.');
        abort_if(DB::table('company_user')->where('role_id', $role->id)->exists(), 422, 'Perfil está vinculado a usuários.');
        $audit->record('role.deleted', $role, $role->toArray(), []);
        $role->delete();

        return back()->with('success', 'Perfil excluído.');
    }
    private function ensureTenantAdministrator(TenantContext $tenant): void
    {
        $user = auth()->user();
        abort_unless($user?->is_super_admin || $user?->roleForTenant($tenant->requireId()) === 'tenant_admin', 403);
    }

}
