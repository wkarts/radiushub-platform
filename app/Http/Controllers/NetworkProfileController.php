<?php

namespace App\Http\Controllers;

use App\Http\Requests\NetworkProfileRequest;
use App\Models\NetworkProfile;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NetworkProfileController extends Controller
{
    public function index(Request $request): View
    {
        $profiles = NetworkProfile::query()
            ->when($request->filled('q'), fn ($q) => \App\Support\Search::contains($q, 'name', (string) $request->string('q')))
            ->orderBy('name')->paginate(25)->withQueryString();
        return view('profiles-network.index', compact('profiles'));
    }

    public function store(NetworkProfileRequest $request, AuditLogger $audit): RedirectResponse
    {
        $profile = NetworkProfile::query()->create($request->validated() + ['active' => $request->boolean('active')]);
        $audit->record('network-profile.created', $profile, [], $profile->toArray());
        return back()->with('success', 'Perfil de conexão criado.');
    }

    public function update(NetworkProfileRequest $request, NetworkProfile $profile, AuditLogger $audit): RedirectResponse
    {
        $old = $profile->toArray();
        $profile->update($request->validated() + ['active' => $request->boolean('active')]);
        $audit->record('network-profile.updated', $profile, $old, $profile->fresh()->toArray());
        return back()->with('success', 'Perfil atualizado.');
    }

    public function destroy(NetworkProfile $profile, AuditLogger $audit): RedirectResponse
    {
        abort_if($profile->vouchers()->exists(), 422, 'Perfil possui vouchers vinculados.');
        $audit->record('network-profile.deleted', $profile, $profile->toArray(), []);
        $profile->delete();
        return back()->with('success', 'Perfil removido.');
    }
}
