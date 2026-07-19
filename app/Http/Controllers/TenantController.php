<?php
namespace App\Http\Controllers;
use App\Http\Requests\TenantRequest;
use App\Models\Tenant;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
class TenantController extends Controller { public function index(): View { return view('tenants.index',['tenants'=>Tenant::query()->orderBy('name')->paginate(25)]); } public function store(TenantRequest $request,AuditLogger $audit): RedirectResponse { $tenant=Tenant::query()->create($request->validated()+['active'=>$request->boolean('active')]); $audit->record('tenant.created',$tenant,[],$tenant->toArray()); return back()->with('success','Empresa cadastrada.'); } public function update(TenantRequest $request,Tenant $tenant,AuditLogger $audit): RedirectResponse { $old=$tenant->toArray(); $tenant->update($request->validated()+['active'=>$request->boolean('active')]); $audit->record('tenant.updated',$tenant,$old,$tenant->fresh()->toArray()); return back()->with('success','Empresa atualizada.'); } public function destroy(Tenant $tenant,AuditLogger $audit): RedirectResponse { abort_if($tenant->users()->exists()||$tenant->subscribers()->exists(),422,'A empresa possui vínculos e não pode ser excluída.'); $audit->record('tenant.deleted',$tenant,$tenant->toArray(),[]); $tenant->delete(); return back()->with('success','Empresa excluída.'); } }
