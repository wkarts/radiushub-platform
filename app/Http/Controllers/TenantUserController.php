<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantUserRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TenantUserController extends Controller
{
    public function index(TenantContext $context): View
    {
        $users = $context->tenant()
            ->users()
            ->orderBy('name')
            ->paginate(25);

        return view('users.index', compact('users'));
    }

    public function store(TenantUserRequest $request, TenantContext $context, AuditLogger $audit): RedirectResponse
    {
        $user = DB::transaction(function () use ($request, $context): User {
            $user = User::query()->create([
                'name' => $request->string('name')->toString(),
                'email' => $request->string('email')->lower()->toString(),
                'password' => $request->string('password')->toString(),
                'active' => $request->boolean('active'),
                'is_super_admin' => false,
                'email_verified_at' => now(),
            ]);

            $context->tenant()->users()->attach($user->id, ['role' => $request->string('role')->toString()]);

            return $user;
        });

        $audit->record('tenant-user.created', $user, [], [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $request->string('role')->toString(),
        ]);

        return back()->with('success', 'Usuário da empresa cadastrado.');
    }

    public function update(TenantUserRequest $request, User $user, TenantContext $context, AuditLogger $audit): RedirectResponse
    {
        $this->ensureTenantUser($user, $context);
        abort_if($user->is_super_admin && ! $request->user()->is_super_admin, 403, 'Somente outro superadministrador pode alterar esta conta.');

        $old = [
            'name' => $user->name,
            'email' => $user->email,
            'active' => $user->active,
            'role' => $user->roleForTenant($context->id()),
        ];

        DB::transaction(function () use ($request, $user, $context): void {
            $data = [
                'name' => $request->string('name')->toString(),
                'email' => $request->string('email')->lower()->toString(),
                'active' => $request->boolean('active'),
            ];

            if ($request->filled('password')) {
                $data['password'] = $request->string('password')->toString();
            }

            $user->update($data);
            $context->tenant()->users()->updateExistingPivot($user->id, [
                'role' => $request->string('role')->toString(),
            ]);
        });

        $audit->record('tenant-user.updated', $user, $old, [
            'name' => $user->fresh()->name,
            'email' => $user->fresh()->email,
            'active' => $user->fresh()->active,
            'role' => $request->string('role')->toString(),
        ]);

        return back()->with('success', 'Usuário atualizado.');
    }

    public function destroy(User $user, TenantContext $context, AuditLogger $audit): RedirectResponse
    {
        $this->ensureTenantUser($user, $context);
        abort_if($user->is(auth()->user()), 422, 'Você não pode remover o próprio vínculo.');
        abort_if($user->is_super_admin, 422, 'A conta de superadministrador não pode ser removida por esta tela.');

        $old = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->roleForTenant($context->id()),
        ];

        $context->tenant()->users()->detach($user->id);
        $audit->record('tenant-user.detached', $user, $old, []);

        return back()->with('success', 'Usuário removido da empresa.');
    }

    private function ensureTenantUser(User $user, TenantContext $context): void
    {
        abort_unless(
            $context->tenant()->users()->whereKey($user->id)->exists(),
            404
        );
    }
}
