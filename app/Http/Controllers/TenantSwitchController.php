<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantSwitchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'uuid'],
        ]);

        $allowed = $request->user()->is_super_admin
            ? Tenant::query()
                ->whereKey($data['tenant_id'])
                ->where('active', true)
                ->exists()
            : $request->user()->tenants()
                ->whereKey($data['tenant_id'])
                ->where('tenants.active', true)
                ->exists();

        abort_unless($allowed, 403);

        $request->session()->put(config('tenancy.session_key'), $data['tenant_id']);
        $request->session()->forget(config('tenancy.company_session_key'));

        return back()->with('success', 'Tenant alterado com sucesso.');
    }
}
