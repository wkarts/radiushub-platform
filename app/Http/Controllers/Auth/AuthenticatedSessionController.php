<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View { return view('auth.login'); }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $credentials = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $login = Str::lower(trim($credentials['login']));
        $key = $login.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            $audit->record('auth.rate-limited', null, [], [], 'failed', ['login_hash' => hash('sha256', $login)]);
            throw ValidationException::withMessages(['login' => "Muitas tentativas. Aguarde {$seconds} segundos."]);
        }

        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'login';

        if (! Auth::attempt([$field => $login, 'password' => $credentials['password'], 'active' => true], (bool) ($credentials['remember'] ?? false))) {
            RateLimiter::hit($key, 60);
            $audit->record('auth.failed', null, [], [], 'failed', ['login_hash' => hash('sha256', $login)]);
            throw ValidationException::withMessages(['login' => 'Credenciais inválidas ou usuário inativo.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $user = $request->user();
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

        if ($user->two_factor_confirmed_at && $user->two_factor_secret) {
            $request->session()->put('2fa_pending_user_id', $user->id);
            $request->session()->put('2fa_remember', (bool) ($credentials['remember'] ?? false));
            Auth::logout();
            return redirect()->route('two-factor.challenge');
        }

        $audit->record('auth.login', $user);

        if ($user->is_super_admin) {
            return redirect()->intended(route('platform.dashboard'));
        }

        return redirect()->intended($user->must_change_password ? route('profile.edit') : route('dashboard'));
    }

    public function destroy(Request $request, AuditLogger $audit): RedirectResponse
    {
        if ($request->user()) $audit->record('auth.logout', $request->user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
