<?php

namespace App\Http\Controllers;

use App\Http\Requests\InternetPlanRequest;
use App\Jobs\Mikrotik\SynchronizeMikrotikResource;
use App\Models\InternetPlan;
use App\Models\NetworkProfile;
use App\Services\Audit\AuditLogger;
use App\Services\Limits\UsageLimitService;
use App\Services\Tenancy\CompanyContext;
use App\Services\Mikrotik\MikrotikSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InternetPlanController extends Controller
{
    public function index(): View
    {
        return view('plans.index', [
            'plans' => InternetPlan::query()->with('networkProfile')->orderBy('name')->paginate(25),
            'profiles' => NetworkProfile::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(InternetPlanRequest $request, AuditLogger $audit, UsageLimitService $limits, CompanyContext $company): RedirectResponse
    {
        $limits->assertCompany($company->company(), 'plans');
        $plan = InternetPlan::query()->create($request->validated() + ['active' => $request->boolean('active')]);
        $audit->record('plan.created', $plan, [], $plan->toArray());
        if (config('mikrotik.auto_sync_on_change')) {
            SynchronizeMikrotikResource::dispatch('plan', $plan->id);
            $audit->record('plan.sync-queued', $plan);
        }
        return back()->with('success', 'Plano cadastrado.');
    }

    public function update(InternetPlanRequest $request, InternetPlan $plan, AuditLogger $audit): RedirectResponse
    {
        $old = $plan->toArray();
        $plan->update($request->validated() + ['active' => $request->boolean('active')]);
        $audit->record('plan.updated', $plan, $old, $plan->fresh()->toArray());
        if (config('mikrotik.auto_sync_on_change')) {
            SynchronizeMikrotikResource::dispatch('plan', $plan->id);
            $audit->record('plan.sync-queued', $plan);
        }
        return back()->with('success', 'Plano atualizado.');
    }

    public function destroy(InternetPlan $plan, AuditLogger $audit): RedirectResponse
    {
        abort_if($plan->contracts()->exists(), 422, 'Plano possui contratos vinculados.');
        $audit->record('plan.deleted', $plan, $plan->toArray(), []);
        $plan->delete();
        return back()->with('success', 'Plano removido.');
    }

    public function sync(InternetPlan $plan, MikrotikSyncService $service, AuditLogger $audit): RedirectResponse
    {
        $result = $service->syncPlan($plan);
        $audit->record('plan.synchronized', $plan, [], ['ok' => $result['ok'] ?? false], ($result['ok'] ?? false) ? 'success' : 'failed');
        return back()->with(($result['ok'] ?? false) ? 'success' : 'error', ($result['ok'] ?? false) ? 'Plano sincronizado.' : 'Falha: '.($result['error'] ?? 'erro desconhecido'));
    }
}
