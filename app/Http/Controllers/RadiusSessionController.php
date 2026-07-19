<?php
namespace App\Http\Controllers;
use App\Models\RadiusAccounting;
use App\Services\Radius\CoaService;
use App\Support\Search;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
class RadiusSessionController extends Controller { public function index(Request $request): View { $sessions=RadiusAccounting::query()->with(['access','mikrotik'])->when($request->boolean('online',true),fn($q)=>$q->whereNull('acct_stop_time'))->when($request->filled('q'), fn ($q) => Search::contains($q, 'username', (string) $request->string('q')))->latest('acct_start_time')->paginate(30)->withQueryString(); return view('sessions.index',compact('sessions')); } public function disconnect(RadiusAccounting $session,CoaService $coa): RedirectResponse { $coa->disconnect($session); return back()->with('success','Solicitação de desconexão enviada.'); } public function rateLimit(Request $request,RadiusAccounting $session,CoaService $coa): RedirectResponse { $data=$request->validate(['rate_limit'=>['required','string','max:120']]); $coa->changeRateLimit($session,$data['rate_limit']); return back()->with('success','CoA de velocidade enviado.'); } }
