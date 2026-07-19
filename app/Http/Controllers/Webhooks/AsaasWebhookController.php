<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAsaasWebhook;
use App\Models\PaymentGatewayConfig;
use App\Models\Tenant;
use App\Models\WebhookEvent;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AsaasWebhookController extends Controller
{
    public function __invoke(Request $request, Tenant $tenant, TenantContext $context): JsonResponse
    {
        $context->set($tenant);

        try {
            $gateway = PaymentGatewayConfig::query()
                ->where('driver', 'asaas')
                ->firstOrFail();

            $provided = trim((string) $request->header('asaas-access-token', ''));
            abort_unless(
                $provided !== '' && hash_equals((string) $gateway->webhook_token, $provided),
                401,
                'Token de webhook inválido.',
            );

            if (! $gateway->active) {
                return response()->json([
                    'received' => true,
                    'disabled' => true,
                ]);
            }

            $payload = $request->json()->all();
            abort_unless(is_array($payload) && $payload !== [], 422, 'Payload JSON inválido.');

            $eventId = trim((string) ($payload['id'] ?? ''));
            if ($eventId === '') {
                $eventId = hash('sha256', $request->getContent());
            }

            $event = WebhookEvent::query()->firstOrCreate(
                [
                    'provider' => 'asaas',
                    'external_event_id' => $eventId,
                ],
                [
                    'event_type' => (string) ($payload['event'] ?? 'unknown'),
                    'payload' => $payload,
                    'status' => 'pending',
                ],
            );

            $staleProcessing = $event->status === 'processing'
                && $event->updated_at?->lt(now()->subMinutes(10));

            if ($event->wasRecentlyCreated
                || (! $event->processed_at && in_array($event->status, ['pending', 'failed'], true))
                || $staleProcessing) {
                ProcessAsaasWebhook::dispatch($event->id)->onQueue('webhooks');
            }

            return response()->json([
                'received' => true,
                'duplicate' => ! $event->wasRecentlyCreated,
            ]);
        } finally {
            $context->clear();
        }
    }
}
