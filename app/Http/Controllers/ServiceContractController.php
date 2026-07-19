<?php
namespace App\Http\Controllers;
use App\Http\Requests\ServiceContractRequest;
use App\Models\InternetPlan;
use App\Models\NetworkAccess;
use App\Models\ServiceContract;
use App\Models\Subscriber;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
class ServiceContractController extends Controller { public function index(): View { return view('contracts.index',['contracts'=>ServiceContract::query()->with(['subscriber','plan','access'])->latest()->paginate(25),'subscribers'=>Subscriber::query()->orderBy('name')->get(),'plans'=>InternetPlan::query()->where('active',true)->orderBy('name')->get(),'accesses'=>NetworkAccess::query()->orderBy('username')->get()]); } public function store(ServiceContractRequest $request,AuditLogger $audit): RedirectResponse { $contract=ServiceContract::query()->create($request->validated()); $audit->record('contract.created',$contract,[],$contract->toArray()); return back()->with('success','Contrato cadastrado.'); } public function update(ServiceContractRequest $request,ServiceContract $contract,AuditLogger $audit): RedirectResponse { $old=$contract->toArray(); $contract->update($request->validated()); $audit->record('contract.updated',$contract,$old,$contract->fresh()->toArray()); return back()->with('success','Contrato atualizado.'); } public function destroy(ServiceContract $contract,AuditLogger $audit): RedirectResponse { abort_if($contract->status->value!=='cancelled',422,'Cancele o contrato antes de excluí-lo.'); $audit->record('contract.deleted',$contract,$contract->toArray(),[]); $contract->delete(); return back()->with('success','Contrato removido.'); } }
