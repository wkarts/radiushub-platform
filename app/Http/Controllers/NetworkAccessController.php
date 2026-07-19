<?php
namespace App\Http\Controllers;
use App\Http\Requests\NetworkAccessRequest;
use App\Jobs\Mikrotik\SynchronizeMikrotikResource;
use App\Models\InternetPlan;
use App\Models\MikrotikDevice;
use App\Models\NetworkAccess;
use App\Models\NetworkProfile;
use App\Models\Subscriber;
use App\Services\Audit\AuditLogger;
use App\Services\Limits\UsageLimitService;
use App\Services\Tenancy\CompanyContext;
use App\Services\Mikrotik\MikrotikSyncService;
use App\Services\Security\RadiusCredentialVault;
use App\Support\Search;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
class NetworkAccessController extends Controller { public function index(Request $request): View { $accesses=NetworkAccess::query()->with(['subscriber','plan','profile','mikrotik'])->when($request->filled('q'), fn ($q) => Search::contains($q, 'username', (string) $request->string('q')))->orderBy('username')->paginate(25)->withQueryString(); return view('accesses.index',['accesses'=>$accesses,'subscribers'=>Subscriber::query()->where('status','active')->orderBy('name')->get(),'plans'=>InternetPlan::query()->where('active',true)->orderBy('name')->get(),'devices'=>MikrotikDevice::query()->where('active',true)->orderBy('name')->get(),'profiles'=>NetworkProfile::query()->where('active',true)->orderBy('name')->get()]); } public function store(NetworkAccessRequest $request,RadiusCredentialVault $vault,AuditLogger $audit,UsageLimitService $limits,CompanyContext $company): RedirectResponse { $limits->assertCompany($company->company(), 'accesses'); $data=$request->safe()->except(['password']); $data['password_ciphertext']=$vault->encrypt((string)$request->password); $access=NetworkAccess::query()->create($data); $audit->record('access.created',$access,[],$access->makeHidden('password_ciphertext')->toArray()); if(config('mikrotik.auto_sync_on_change') && $access->mikrotik_device_id){ SynchronizeMikrotikResource::dispatch('access',$access->id); $audit->record('access.sync-queued',$access); } return back()->with('success','Credencial de acesso cadastrada.'); } public function update(NetworkAccessRequest $request,NetworkAccess $access,RadiusCredentialVault $vault,AuditLogger $audit): RedirectResponse { $old=$access->makeHidden('password_ciphertext')->toArray(); $data=$request->safe()->except(['password']); if($request->filled('password'))$data['password_ciphertext']=$vault->encrypt((string)$request->password); $access->update($data); $audit->record('access.updated',$access,$old,$access->fresh()->makeHidden('password_ciphertext')->toArray()); if(config('mikrotik.auto_sync_on_change') && $access->mikrotik_device_id){ SynchronizeMikrotikResource::dispatch('access',$access->id); $audit->record('access.sync-queued',$access); } return back()->with('success','Credencial atualizada.'); } public function sync(NetworkAccess $access,MikrotikSyncService $service,AuditLogger $audit): RedirectResponse { $result=$service->syncAccess($access); $audit->record('access.synchronized',$access,[],['ok'=>$result['ok']??false],($result['ok']??false)?'success':'failed'); return back()->with(($result['ok']??false)?'success':'error',($result['ok']??false)?'Acesso sincronizado.':'Falha: '.($result['error']??'erro desconhecido')); } public function destroy(NetworkAccess $access,AuditLogger $audit): RedirectResponse { abort_if($access->contracts()->whereNotIn('status',['cancelled'])->exists(),422,'Acesso possui contrato ativo.'); $audit->record('access.deleted',$access,$access->makeHidden('password_ciphertext')->toArray(),[]); $access->delete(); return back()->with('success','Credencial removida.'); } }
