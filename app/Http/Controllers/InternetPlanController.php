<?php
namespace App\Http\Controllers;
use App\Http\Requests\InternetPlanRequest;
use App\Models\InternetPlan;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
class InternetPlanController extends Controller { public function index(): View { return view('plans.index',['plans'=>InternetPlan::query()->orderBy('name')->paginate(25)]); } public function store(InternetPlanRequest $request,AuditLogger $audit): RedirectResponse { $plan=InternetPlan::query()->create($request->validated()+['active'=>$request->boolean('active')]); $audit->record('plan.created',$plan,[],$plan->toArray()); return back()->with('success','Plano cadastrado.'); } public function update(InternetPlanRequest $request,InternetPlan $plan,AuditLogger $audit): RedirectResponse { $old=$plan->toArray(); $plan->update($request->validated()+['active'=>$request->boolean('active')]); $audit->record('plan.updated',$plan,$old,$plan->fresh()->toArray()); return back()->with('success','Plano atualizado.'); } public function destroy(InternetPlan $plan,AuditLogger $audit): RedirectResponse { abort_if($plan->contracts()->exists(),422,'Plano possui contratos vinculados.'); $audit->record('plan.deleted',$plan,$plan->toArray(),[]); $plan->delete(); return back()->with('success','Plano removido.'); } }
