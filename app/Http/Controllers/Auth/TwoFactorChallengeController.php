<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Security\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): View
    {
        abort_unless($request->session()->has('2fa_pending_user_id'), 403);
        return view('auth.two-factor-challenge');
    }

    public function store(Request $request, TwoFactorService $twoFactor, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:20']]);
        $user = User::query()->findOrFail($request->session()->get('2fa_pending_user_id'));
        $rateKey = '2fa|'.$user->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            throw ValidationException::withMessages([
                'code' => 'Muitas tentativas de segundo fator. Aguarde '.RateLimiter::availableIn($rateKey).' segundos.',
            ]);
        }

        $secret = (string) $user->two_factor_secret;

        $recoveryCodes = $user->two_factor_recovery_codes ?: [];
        $normalized = strtoupper(str_replace(['-', ' '], '', $data['code']));
        $recoveryIndex = collect($recoveryCodes)->search(fn ($code) => hash_equals(str_replace('-', '', strtoupper($code)), $normalized));

        $valid = $twoFactor->verify($secret, $data['code']) || $recoveryIndex !== false;
        if (! $valid) {
            RateLimiter::hit($rateKey, 60);
            $audit->record('auth.2fa-failed', $user, [], [], 'failed');
            throw ValidationException::withMessages(['code' => 'Código de autenticação inválido.']);
        }

        if ($recoveryIndex !== false) {
            unset($recoveryCodes[$recoveryIndex]);
            $user->two_factor_recovery_codes = array_values($recoveryCodes);
            $user->save();
        }

        RateLimiter::clear($rateKey);
        Auth::login($user, (bool) $request->session()->pull('2fa_remember', false));
        $request->session()->forget('2fa_pending_user_id');
        $request->session()->regenerate();
        $audit->record('auth.2fa-confirmed', $user);

        if ($user->is_super_admin) {
            return redirect()->intended(route('platform.dashboard'));
        }

        return redirect()->intended($user->must_change_password ? route('profile.edit') : route('dashboard'));
    }
}
