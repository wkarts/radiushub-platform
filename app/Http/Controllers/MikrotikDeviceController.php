<?php

namespace App\Http\Controllers;

use App\Http\Requests\MikrotikDeviceRequest;
use App\Models\MikrotikDevice;
use App\Services\Audit\AuditLogger;
use App\Services\Limits\UsageLimitService;
use App\Services\Mikrotik\MikrotikSshService;
use App\Services\Mikrotik\MikrotikSyncService;
use App\Services\Security\RadiusCredentialVault;
use App\Services\Security\SshKeyVault;
use App\Support\Search;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MikrotikDeviceController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', MikrotikDevice::class);

        $devices = MikrotikDevice::query()
            ->when($request->filled('q'), fn ($q) => Search::contains($q, 'name', (string) $request->string('q')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('mikrotiks.index', compact('devices'));
    }

    public function store(
        MikrotikDeviceRequest $request,
        SshKeyVault $sshVault,
        RadiusCredentialVault $radiusVault,
        MikrotikSshService $ssh,
        AuditLogger $audit,
        UsageLimitService $limits,
    ): RedirectResponse {
        $limits->assertCompany(app(\App\Services\Tenancy\CompanyContext::class)->company(), 'mikrotiks');
        $data = $request->safe()->except([
            'ssh_private_key', 'ssh_passphrase', 'ssh_password', 'radius_secret',
        ]);

        if ($request->filled('ssh_private_key')) {
            $validation = $ssh->validatePrivateKey((string) $request->ssh_private_key, $request->ssh_passphrase);
            if (! $validation['valid']) return back()->withErrors(['ssh_private_key' => $validation['error']])->withInput();
            $data['ssh_private_key_ciphertext'] = $sshVault->encrypt((string) $request->ssh_private_key);
            $data['ssh_public_key'] = $validation['public_key'];
        }

        $data['ssh_passphrase_ciphertext'] = $sshVault->encrypt($request->ssh_passphrase);
        $data['ssh_password_ciphertext'] = $sshVault->encrypt($request->ssh_password);
        $data['radius_secret_ciphertext'] = $radiusVault->encrypt((string) $request->radius_secret);
        $data['connection_method'] = 'ssh';

        foreach (['ssh_password_fallback_enabled', 'hotspot_enabled', 'pppoe_enabled', 'active'] as $field) $data[$field] = $request->boolean($field);

        $device = MikrotikDevice::query()->create($data);
        $audit->record('mikrotik.created', $device, [], $device->toArray());

        return back()->with('success', 'MikroTik cadastrado com autenticação SSH.');
    }

    public function update(
        MikrotikDeviceRequest $request,
        MikrotikDevice $mikrotik,
        SshKeyVault $sshVault,
        RadiusCredentialVault $radiusVault,
        MikrotikSshService $ssh,
        AuditLogger $audit,
    ): RedirectResponse {
        $this->authorize('update', $mikrotik);
        $old = $mikrotik->toArray();

        $data = $request->safe()->except([
            'ssh_private_key', 'ssh_passphrase', 'ssh_password', 'radius_secret',
        ]);

        if ($request->filled('ssh_private_key')) {
            $validation = $ssh->validatePrivateKey((string) $request->ssh_private_key, $request->ssh_passphrase);
            if (! $validation['valid']) return back()->withErrors(['ssh_private_key' => $validation['error']])->withInput();
            $data['ssh_private_key_ciphertext'] = $sshVault->encrypt((string) $request->ssh_private_key);
            $data['ssh_public_key'] = $validation['public_key'];
        }

        if ($request->filled('ssh_passphrase')) $data['ssh_passphrase_ciphertext'] = $sshVault->encrypt($request->ssh_passphrase);
        if ($request->filled('ssh_password')) $data['ssh_password_ciphertext'] = $sshVault->encrypt($request->ssh_password);
        if ($request->filled('radius_secret')) $data['radius_secret_ciphertext'] = $radiusVault->encrypt((string) $request->radius_secret);

        foreach (['ssh_password_fallback_enabled', 'hotspot_enabled', 'pppoe_enabled', 'active'] as $field) $data[$field] = $request->boolean($field);

        $mikrotik->update($data);
        $audit->record('mikrotik.updated', $mikrotik, $old, $mikrotik->fresh()->toArray());

        return back()->with('success', 'MikroTik atualizado.');
    }

    public function destroy(MikrotikDevice $mikrotik, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('delete', $mikrotik);
        abort_if($mikrotik->accesses()->exists() || $mikrotik->vouchers()->exists(), 422, 'MikroTik possui acessos ou vouchers vinculados.');
        $audit->record('mikrotik.deleted', $mikrotik, $mikrotik->toArray(), []);
        $mikrotik->delete();

        return back()->with('success', 'MikroTik removido.');
    }

    public function test(MikrotikDevice $mikrotik, MikrotikSshService $service, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('execute', $mikrotik);
        $result = $service->test($mikrotik);
        $audit->record('mikrotik.connection-tested', $mikrotik, [], ['ok' => $result['ok'], 'identity' => $result['identity'] ?? null], $result['ok'] ? 'success' : 'failed');

        return back()->with($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Conexão SSH e identidade do RouterOS confirmadas.' : 'Falha: '.$result['error']);
    }

    public function execute(Request $request, MikrotikDevice $mikrotik, MikrotikSshService $service, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('execute', $mikrotik);
        $data = $request->validate([
            'command_key' => ['required', Rule::in($service->safeReadCommandKeys())],
        ]);
        $result = $service->executeApproved($mikrotik, $data['command_key']);
        $audit->record('mikrotik.command-executed', $mikrotik, [], ['command_key' => $data['command_key']], $result['ok'] ? 'success' : 'failed');

        return back()->with($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Comando controlado executado.' : 'Falha: '.$result['error']);
    }


    public function sync(MikrotikDevice $mikrotik, MikrotikSyncService $sync, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('execute', $mikrotik);

        try {
            $result = $sync->syncDevice($mikrotik);
            $audit->record('mikrotik.synchronized', $mikrotik, [], $result, $result['ok'] ? 'success' : 'failed');

            $message = sprintf(
                'Sincronização concluída: %d sucesso(s), %d falha(s).',
                $result['total_success'],
                $result['total_failed'],
            );

            return back()->with($result['ok'] ? 'success' : 'error', $message);
        } catch (\Throwable $exception) {
            $audit->record('mikrotik.synchronization-failed', $mikrotik, [], [
                'error' => $exception->getMessage(),
            ], 'failed');

            return back()->with('error', 'Falha de sincronização: '.$exception->getMessage());
        }
    }

    public function logs(MikrotikDevice $mikrotik): View
    {
        $this->authorize('view', $mikrotik);
        return view('mikrotiks.logs', [
            'device' => $mikrotik,
            'connections' => $mikrotik->connectionLogs()->latest('created_at')->paginate(25, ['*'], 'connections'),
            'commands' => $mikrotik->commandLogs()->latest('created_at')->paginate(25, ['*'], 'commands'),
        ]);
    }

    public function generateKeyPair(MikrotikSshService $ssh): View
    {
        abort_unless(auth()->user()->is_super_admin || auth()->user()->hasPermission('mikrotiks.manage', session(config('tenancy.session_key')), session(config('tenancy.company_session_key'))), 403);
        return view('mikrotiks.generated-key', ['keyPair' => $ssh->generateKeyPair()]);
    }
}
