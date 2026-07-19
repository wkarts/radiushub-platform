<?php

namespace App\Services\Mikrotik;

use App\Models\CoaRequest;
use App\Models\MikrotikDevice;
use App\Models\RadiusAccounting;
use App\Services\Radius\CoaService;
use RuntimeException;
use Throwable;

/**
 * Controle administrativo de sessões por SSH Key.
 *
 * O RADIUS permanece responsável pelo fluxo AAA. As ações administrativas
 * iniciadas pelo painel usam SSH por padrão. O fallback CoA é preservado
 * somente para compatibilidade e precisa ser habilitado explicitamente.
 */
final class MikrotikSessionControlService
{
    public function __construct(
        private readonly MikrotikSshService $ssh,
        private readonly CoaService $coa,
    ) {}

    public function disconnect(RadiusAccounting $session): CoaRequest
    {
        return $this->execute(
            $session,
            'ssh-disconnect',
            'disconnect-session',
            [
                'username' => $session->username,
                'framed_ip_address' => $session->framed_ip_address,
                'session_id' => $session->acct_session_id ?: (string) $session->getKey(),
            ],
            fn (): CoaRequest => $this->coa->disconnect($session),
        );
    }

    public function changeRateLimit(RadiusAccounting $session, string $rateLimit): CoaRequest
    {
        if (! $session->framed_ip_address) {
            throw new RuntimeException('A sessão não possui endereço IP para aplicação do limite por SSH.');
        }

        return $this->execute(
            $session,
            'ssh-rate-limit',
            'set-session-rate-limit',
            [
                'username' => $session->username,
                'framed_ip_address' => $session->framed_ip_address,
                'session_id' => $session->acct_session_id ?: (string) $session->getKey(),
                'rate_limit' => $rateLimit,
            ],
            fn (): CoaRequest => $this->coa->changeRateLimit($session, $rateLimit),
        );
    }

    private function execute(
        RadiusAccounting $session,
        string $type,
        string $commandKey,
        array $parameters,
        callable $coaFallback,
    ): CoaRequest {
        $device = $this->resolveDevice($session);
        $request = CoaRequest::query()->create([
            'tenant_id' => $device->tenant_id,
            'company_id' => $device->company_id,
            'mikrotik_device_id' => $device->id,
            'radius_accounting_id' => $session->id,
            'type' => $type,
            'status' => 'pending',
            'attributes' => $parameters,
            'requested_by' => auth()->id(),
            'requested_at' => now(),
        ]);

        try {
            $result = $this->ssh->executeApproved($device, $commandKey, $parameters);
            if (! ($result['ok'] ?? false)) {
                throw new RuntimeException((string) ($result['error'] ?? 'Falha ao executar comando SSH.'));
            }

            $request->update([
                'status' => 'acknowledged',
                'response' => [
                    'transport' => 'ssh',
                    'exit_status' => $result['exit_status'] ?? null,
                    'output' => mb_substr((string) ($result['output'] ?? ''), 0, 4000),
                ],
                'completed_at' => now(),
            ]);

            return $request;
        } catch (Throwable $exception) {
            $request->update([
                'status' => 'failed',
                'response' => ['transport' => 'ssh', 'error' => $exception->getMessage()],
                'completed_at' => now(),
            ]);

            if ((bool) config('mikrotik.session_control.allow_coa_fallback', false)) {
                return $coaFallback();
            }

            throw $exception;
        }
    }

    private function resolveDevice(RadiusAccounting $session): MikrotikDevice
    {
        return $session->mikrotik
            ?: MikrotikDevice::query()->where('radius_source_ip', $session->nas_ip_address)->firstOrFail();
    }
}
