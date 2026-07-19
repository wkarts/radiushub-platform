<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileRequest;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit');
    }

    public function update(ProfileRequest $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        $old = ['name' => $user->name, 'email' => $user->email];
        $data = $request->safe()->only(['name', 'email']);

        if ($request->filled('password')) {
            $data['password'] = $request->string('password')->toString();
        }

        $user->update($data);
        $audit->record('profile.updated', $user, $old, ['name' => $user->name, 'email' => $user->email]);

        return back()->with('success', 'Perfil atualizado com segurança.');
    }
}
