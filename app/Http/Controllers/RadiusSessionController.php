<?php

namespace App\Http\Controllers;

use App\Models\RadiusAccounting;
use App\Services\Mikrotik\MikrotikSessionControlService;
use App\Support\Search;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class RadiusSessionController extends Controller
{
    public function index(Request $request): View
    {
        $sessions = RadiusAccounting::query()
            ->with(['access', 'mikrotik'])
            ->when($request->boolean('online', true), fn ($query) => $query->whereNull('acct_stop_time'))
            ->when($request->filled('q'), fn ($query) => Search::contains($query, 'username', (string) $request->string('q')))
            ->latest('acct_start_time')
            ->paginate(30)
            ->withQueryString();

        return view('sessions.index', compact('sessions'));
    }

    public function disconnect(
        RadiusAccounting $session,
        MikrotikSessionControlService $sessions,
    ): RedirectResponse {
        try {
            $sessions->disconnect($session);
            return back()->with('success', 'Sessão desconectada pelo canal administrativo SSH.');
        } catch (Throwable $exception) {
            report($exception);
            return back()->with('error', 'Não foi possível desconectar a sessão: '.$exception->getMessage());
        }
    }

    public function rateLimit(
        Request $request,
        RadiusAccounting $session,
        MikrotikSessionControlService $sessions,
    ): RedirectResponse {
        $data = $request->validate([
            'rate_limit' => ['required', 'string', 'max:120', 'regex:/^\d+(?:[kKmMgG])?(?:\/\d+(?:[kKmMgG])?)?$/'],
        ], [
            'rate_limit.regex' => 'Use um limite válido, por exemplo 10M ou 10M/50M.',
        ]);

        try {
            $sessions->changeRateLimit($session, $data['rate_limit']);
            return back()->with('success', 'Limite temporário aplicado à sessão por SSH.');
        } catch (Throwable $exception) {
            report($exception);
            return back()->with('error', 'Não foi possível alterar o limite: '.$exception->getMessage());
        }
    }
}
