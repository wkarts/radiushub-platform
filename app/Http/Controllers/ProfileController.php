<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileRequest;
use App\Services\Audit\AuditLogger;
use App\Services\Security\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit', [
            'pendingTwoFactorSecret' => session('two_factor_secret'),
            'pendingTwoFactorUri' => session('two_factor_uri'),
            'recoveryCodes' => session('two_factor_recovery_codes', []),
        ]);
    }

    public function update(ProfileRequest $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        $old = ['name' => $user->name, 'email' => $user->email, 'login' => $user->login];
        $data = $request->safe()->only(['name', 'email', 'login']);

        if ($request->filled('password')) {
            $data['password'] = $request->string('password')->toString();
            $data['must_change_password'] = false;
        }

        $user->update($data);
        $audit->record('profile.updated', $user, $old, ['name' => $user->name, 'email' => $user->email, 'login' => $user->login]);

        return back()->with('success', 'Perfil atualizado com segurança.');
    }

    public function beginTwoFactor(Request $request, TwoFactorService $twoFactor, AuditLogger $audit): RedirectResponse
    {
        $secret = $twoFactor->generateSecret();
        $codes = $twoFactor->recoveryCodes();
        $user = $request->user();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => null,
        ])->save();

        $audit->record('profile.2fa-started', $user);

        return back()->with([
            'success' => 'Informe o código atual do autenticador para concluir a ativação.',
            'two_factor_secret' => $secret,
            'two_factor_uri' => $twoFactor->otpauthUri($user->email, $secret),
            'two_factor_recovery_codes' => $codes,
        ]);
    }

    public function confirmTwoFactor(Request $request, TwoFactorService $twoFactor, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $user = $request->user();

        abort_unless($user->two_factor_secret, 422, 'Inicie a configuração do segundo fator antes de confirmar.');

        if (! $twoFactor->verify((string) $user->two_factor_secret, $data['code'])) {
            return back()->withErrors(['code' => 'Código TOTP inválido.']);
        }

        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $audit->record('profile.2fa-enabled', $user);

        return back()->with('success', 'Autenticação em dois fatores ativada.');
    }

    public function disableTwoFactor(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['current_password' => ['required', 'string']]);
        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Senha atual incorreta.']);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
        $audit->record('profile.2fa-disabled', $user);

        return back()->with('success', 'Autenticação em dois fatores desativada.');
    }
}
