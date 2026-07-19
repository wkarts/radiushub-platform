<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Limits\UsageLimitService;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\TenantContext;
use App\Support\Search;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TenantUserController extends Controller
{
    public function index(Request $request, CompanyContext $company, TenantContext $tenant): View
    {
        $users = $company->company()->users()
            ->when($request->filled('q'), fn ($q) => Search::contains($q, 'users.name', (string) $request->string('q')))
            ->orderBy('users.name')->paginate(25)->withQueryString();

        $roles = Role::query()
            ->where(fn ($q) => $q->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id()))
            ->where('scope', 'company')->where('active', true)->orderBy('name')->get();

        return view('users.index', compact('users', 'roles'));
    }

    public function store(TenantUserRequest $request, CompanyContext $company, TenantContext $tenant, AuditLogger $audit, UsageLimitService $limits): RedirectResponse
    {
        $limits->assertCompany($company->company(), 'users');
        $user = DB::transaction(function () use ($request, $company, $tenant): User {
            $user = User::query()->create([
                'name' => $request->string('name')->toString(),
                'login' => $request->filled('login') ? $request->string('login')->lower()->toString() : null,
                'email' => $request->string('email')->lower()->toString(),
                'password' => $request->string('password')->toString(),
                'active' => $request->boolean('active'),
                'must_change_password' => $request->boolean('must_change_password', true),
                'is_super_admin' => false,
                'email_verified_at' => now(),
            ]);

            $tenant->tenant()->users()->attach($user->id, ['role' => $request->boolean('tenant_admin') ? 'tenant_admin' : 'operator']);
            $company->company()->users()->attach($user->id, [
                'role_id' => $request->string('role_id')->toString(),
                'is_primary' => true,
                'active' => true,
            ]);

            return $user;
        });

        $audit->record('company-user.created', $user, [], [
            'name' => $user->name, 'email' => $user->email, 'company_id' => $company->id(),
            'role_id' => $request->string('role_id')->toString(),
        ]);

        return back()->with('success', 'Usuário da empresa cadastrado.');
    }

    public function update(TenantUserRequest $request, User $user, CompanyContext $company, TenantContext $tenant, AuditLogger $audit): RedirectResponse
    {
        $this->ensureCompanyUser($user, $company);
        $old = ['name' => $user->name, 'email' => $user->email, 'active' => $user->active];

        DB::transaction(function () use ($request, $user, $company, $tenant): void {
            $data = [
                'name' => $request->string('name')->toString(),
                'login' => $request->filled('login') ? $request->string('login')->lower()->toString() : null,
                'email' => $request->string('email')->lower()->toString(),
                'active' => $request->boolean('active'),
                'must_change_password' => $request->boolean('must_change_password'),
            ];
            if ($request->filled('password')) $data['password'] = $request->string('password')->toString();
            $user->update($data);

            $company->company()->users()->updateExistingPivot($user->id, [
                'role_id' => $request->string('role_id')->toString(), 'active' => true,
            ]);
            $tenant->tenant()->users()->syncWithoutDetaching([$user->id => ['role' => $request->boolean('tenant_admin') ? 'tenant_admin' : 'operator']]);
        });

        $audit->record('company-user.updated', $user, $old, [
            'name' => $user->fresh()->name, 'email' => $user->fresh()->email,
            'active' => $user->fresh()->active, 'role_id' => $request->string('role_id')->toString(),
        ]);

        return back()->with('success', 'Usuário atualizado.');
    }

    public function destroy(User $user, CompanyContext $company, AuditLogger $audit): RedirectResponse
    {
        $this->ensureCompanyUser($user, $company);
        abort_if($user->is(auth()->user()), 422, 'Você não pode remover o próprio vínculo.');
        abort_if($user->is_super_admin, 422, 'Superadministradores não podem ser removidos por esta tela.');

        $company->company()->users()->detach($user->id);
        $audit->record('company-user.detached', $user, ['company_id' => $company->id()], []);

        return back()->with('success', 'Usuário removido da empresa atual.');
    }

    private function ensureCompanyUser(User $user, CompanyContext $company): void
    {
        abort_unless($company->company()->users()->whereKey($user->id)->exists(), 404);
    }
}
