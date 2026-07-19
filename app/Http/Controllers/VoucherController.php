<?php

namespace App\Http\Controllers;

use App\Http\Requests\VoucherBatchRequest;
use App\Jobs\Mikrotik\SynchronizeMikrotikResource;
use App\Models\InternetPlan;
use App\Models\MikrotikDevice;
use App\Models\NetworkProfile;
use App\Models\Voucher;
use App\Models\VoucherBatch;
use App\Services\Audit\AuditLogger;
use App\Services\Mikrotik\MikrotikSyncService;
use App\Services\Security\RadiusCredentialVault;
use App\Services\Vouchers\VoucherGeneratorService;
use App\Support\Search;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VoucherController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Voucher::class);

        $vouchers = Voucher::query()
            ->with(['batch', 'plan', 'profile', 'mikrotik'])
            ->when($request->filled('q'), fn ($q) => Search::contains($q, 'code', (string) $request->string('q')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('batch_id'), fn ($q) => $q->where('voucher_batch_id', $request->string('batch_id')))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        return view('vouchers.index', [
            'vouchers' => $vouchers,
            'batches' => VoucherBatch::query()->latest()->limit(100)->get(),
            'plans' => InternetPlan::query()->where('active', true)->orderBy('name')->get(),
            'profiles' => NetworkProfile::query()->where('active', true)->orderBy('name')->get(),
            'devices' => MikrotikDevice::query()->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(VoucherBatchRequest $request, VoucherGeneratorService $generator, MikrotikSyncService $sync, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('create', Voucher::class);
        $data = $request->validated();
        $data['print_show_company'] = $request->boolean('print_show_company');
        $data['print_show_password'] = $request->boolean('print_show_password');
        $data['sync_after_generate'] = $request->boolean('sync_after_generate');
        $batch = $generator->generate($data);

        $queued = 0;
        if ($request->boolean('sync_after_generate') || config('mikrotik.auto_sync_on_change')) {
            foreach ($batch->vouchers as $voucher) {
                if (! $voucher->mikrotik_device_id) continue;
                SynchronizeMikrotikResource::dispatch('voucher', $voucher->id);
                $queued++;
            }
        }

        $audit->record('voucher.batch-generated', $batch, [], [
            'quantity' => $batch->quantity,
            'name' => $batch->name,
            'sync_jobs_queued' => $queued,
        ]);

        return back()->with('success', $queued > 0
            ? "Lote gerado e {$queued} sincronização(ões) enviada(s) para a fila."
            : 'Lote de vouchers gerado com sucesso.');
    }

    public function block(Voucher $voucher, MikrotikSyncService $sync, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $voucher);
        $old = $voucher->toArray();
        $voucher->update(['status' => 'blocked', 'blocked_at' => now()]);
        if ($voucher->mikrotik) $sync->syncVoucher($voucher);
        $audit->record('voucher.blocked', $voucher, $old, $voucher->fresh()->toArray());
        return back()->with('success', 'Voucher bloqueado.');
    }

    public function reactivate(Voucher $voucher, MikrotikSyncService $sync, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $voucher);
        $old = $voucher->toArray();
        $status = $voucher->first_access_at ? 'active' : 'available';
        $voucher->update(['status' => $status, 'blocked_at' => null, 'cancelled_at' => null]);
        if ($voucher->mikrotik) $sync->syncVoucher($voucher);
        $audit->record('voucher.reactivated', $voucher, $old, $voucher->fresh()->toArray());
        return back()->with('success', 'Voucher reativado.');
    }

    public function cancel(Voucher $voucher, MikrotikSyncService $sync, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $voucher);
        $old = $voucher->toArray();
        $voucher->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        if ($voucher->mikrotik) $sync->syncVoucher($voucher);
        $audit->record('voucher.cancelled', $voucher, $old, $voucher->fresh()->toArray());
        return back()->with('success', 'Voucher cancelado.');
    }

    public function renew(Request $request, Voucher $voucher, MikrotikSyncService $sync, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $voucher);
        $data = $request->validate([
            'expires_at' => ['required', 'date', 'after:now'],
            'validity_duration_minutes' => ['nullable', 'integer', 'min:1'],
        ]);
        $old = $voucher->toArray();
        $voucher->update([
            'expires_at' => $data['expires_at'],
            'validity_duration_minutes' => $data['validity_duration_minutes'] ?? $voucher->validity_duration_minutes,
            'status' => $voucher->first_access_at ? 'active' : 'available',
            'blocked_at' => null, 'cancelled_at' => null,
        ]);
        if ($voucher->mikrotik) $sync->syncVoucher($voucher);
        $audit->record('voucher.renewed', $voucher, $old, $voucher->fresh()->toArray());
        return back()->with('success', 'Voucher renovado.');
    }

    public function sync(Voucher $voucher, MikrotikSyncService $sync, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $voucher);
        $result = $sync->syncVoucher($voucher);
        $audit->record('voucher.synchronized', $voucher, [], ['ok' => $result['ok'] ?? false], ($result['ok'] ?? false) ? 'success' : 'failed');
        return back()->with(($result['ok'] ?? false) ? 'success' : 'error', ($result['ok'] ?? false) ? 'Voucher sincronizado.' : 'Falha: '.($result['error'] ?? 'erro desconhecido'));
    }

    public function printBatch(VoucherBatch $batch, VoucherGeneratorService $generator): View
    {
        $this->authorize('export', Voucher::class);
        $batch->load(['vouchers.plan', 'vouchers.profile']);
        $credentials = $batch->vouchers->mapWithKeys(fn (Voucher $voucher) => [$voucher->id => $generator->reveal($voucher)]);
        return view('vouchers.print', compact('batch', 'credentials'));
    }

    public function exportCsv(VoucherBatch $batch, VoucherGeneratorService $generator): StreamedResponse
    {
        $this->authorize('export', Voucher::class);
        $batch->load(['vouchers.plan', 'vouchers.profile', 'vouchers.mikrotik']);

        return response()->streamDownload(function () use ($batch, $generator): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Código', 'Senha', 'Plano', 'Perfil', 'MikroTik', 'Status', 'Validade'], ';');
            foreach ($batch->vouchers as $voucher) {
                $credentials = $generator->reveal($voucher);
                fputcsv($out, [
                    $credentials['code'], $credentials['password'], $voucher->plan?->name,
                    $voucher->profile?->name, $voucher->mikrotik?->name, $voucher->status,
                    optional($voucher->expires_at)->format('d/m/Y H:i:s'),
                ], ';');
            }
            fclose($out);
        }, 'vouchers-'.str($batch->name)->slug().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportPdf(VoucherBatch $batch, VoucherGeneratorService $generator): Response
    {
        $this->authorize('export', Voucher::class);
        $batch->load(['vouchers.plan', 'vouchers.profile']);
        $credentials = $batch->vouchers->mapWithKeys(fn (Voucher $voucher) => [$voucher->id => $generator->reveal($voucher)]);
        return Pdf::loadView('vouchers.pdf', compact('batch', 'credentials'))
            ->setPaper('a4')
            ->download('vouchers-'.str($batch->name)->slug().'.pdf');
    }
}
