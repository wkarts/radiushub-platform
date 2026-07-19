<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\BillingGatewayException;
use App\Http\Requests\GatewayRequest;
use App\Models\Invoice;
use App\Models\PaymentGatewayConfig;
use App\Models\Subscriber;
use App\Services\Audit\AuditLogger;
use App\Services\Billing\BillingManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

final class PaymentGatewayController extends Controller
{
    public function index(): View
    {
        return view('gateways.index', [
            'gateways' => PaymentGatewayConfig::query()->orderBy('name')->get(),
        ]);
    }

    public function store(GatewayRequest $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();

        $gateway = PaymentGatewayConfig::query()->create([
            'driver' => $data['driver'],
            'name' => $data['name'],
            'environment' => $data['environment'],
            'active' => $request->boolean('active'),
            'credentials' => array_filter([
                'api_key' => $data['api_key'] ?? null,
            ]),
            'settings' => [
                'notification_disabled' => $request->boolean('notification_disabled'),
                'webhook_email' => $data['webhook_email'] ?? null,
            ],
            'webhook_token' => $data['webhook_token'] ?? null,
        ]);

        $audit->record('payment_gateway.created', $gateway, [], [
            'driver' => $gateway->driver,
            'environment' => $gateway->environment,
            'active' => $gateway->active,
        ]);

        return back()->with('success', 'Gateway cadastrado. Teste a conexão e sincronize o webhook.');
    }

    public function update(
        GatewayRequest $request,
        PaymentGatewayConfig $gateway,
        AuditLogger $audit,
    ): RedirectResponse {
        $data = $request->validated();
        $old = $gateway->only(['name', 'environment', 'active', 'settings']);
        $credentials = $gateway->credentials ?? [];
        $currentApiKey = (string) ($credentials['api_key'] ?? '');
        $newApiKey = trim((string) ($data['api_key'] ?? ''));
        $apiKeyChanged = $newApiKey !== '' && ! hash_equals($currentApiKey, $newApiKey);
        $environmentChanged = $gateway->environment !== $data['environment'];
        $accountChanged = $apiKeyChanged || $environmentChanged;

        if ($newApiKey !== '') {
            $credentials['api_key'] = $newApiKey;
        }

        $settings = array_replace($gateway->settings ?? [], [
            'notification_disabled' => $request->boolean('notification_disabled'),
            'webhook_email' => $data['webhook_email'] ?? null,
        ]);

        if ($accountChanged) {
            unset(
                $settings['webhook_external_id'],
                $settings['webhook_synced_at'],
                $settings['webhook_sync_status'],
            );
        }

        DB::transaction(function () use ($gateway, $data, $request, $credentials, $settings, $accountChanged): void {
            if ($accountChanged) {
                $subscriberIds = $gateway->customerLinks()->pluck('subscriber_id');
                $gateway->customerLinks()->delete();

                if ($subscriberIds->isNotEmpty()) {
                    Subscriber::query()
                        ->whereKey($subscriberIds)
                        ->update(['gateway_customer_id' => null]);
                }

                Invoice::query()
                    ->where('payment_gateway_config_id', $gateway->id)
                    ->whereIn('status', ['pending', 'overdue'])
                    ->update([
                        'external_id' => null,
                        'gateway_status' => null,
                        'payment_url' => null,
                        'bank_slip_url' => null,
                        'bank_slip_line' => null,
                        'pix_copy_paste' => null,
                        'pix_qr_code' => null,
                        'pix_expiration_at' => null,
                        'last_synced_at' => null,
                    ]);
            }

            $gateway->update([
                'driver' => $data['driver'],
                'name' => $data['name'],
                'environment' => $data['environment'],
                'active' => $request->boolean('active'),
                'credentials' => $credentials,
                'settings' => $settings,
                'webhook_token' => ! empty($data['webhook_token'])
                    ? $data['webhook_token']
                    : $gateway->webhook_token,
            ]);
        });

        $audit->record('payment_gateway.updated', $gateway, $old, [
            'name' => $gateway->name,
            'environment' => $gateway->environment,
            'active' => $gateway->active,
            'account_changed' => $accountChanged,
        ]);

        return back()->with('success', $accountChanged
            ? 'Gateway atualizado. Os vínculos pendentes serão recriados com segurança na conta atual.'
            : 'Gateway atualizado.');
    }

    public function test(PaymentGatewayConfig $gateway, BillingManager $billing): RedirectResponse
    {
        try {
            $result = $billing->forGateway($gateway)->testConnection();
            $gateway->mergeSettings([
                'last_tested_at' => now()->toIso8601String(),
                'last_test_status' => 'success',
                'last_test_message' => 'Conexão validada com a API Asaas.',
                'last_test_response' => $result,
            ]);

            return back()->with('success', 'Conexão com o Asaas validada com sucesso.');
        } catch (BillingGatewayException $exception) {
            $gateway->mergeSettings([
                'last_tested_at' => now()->toIso8601String(),
                'last_test_status' => 'failed',
                'last_test_message' => $exception->getMessage(),
            ]);

            return back()->withErrors(['gateway' => $exception->getMessage()]);
        }
    }

    public function synchronizeWebhook(
        PaymentGatewayConfig $gateway,
        BillingManager $billing,
        AuditLogger $audit,
    ): RedirectResponse {
        try {
            $this->synchronizeGatewayWebhook($gateway, $billing, $gateway->webhookUrl());
            $audit->record('payment_gateway.webhook_synchronized', $gateway, [], [
                'webhook_url_hash' => hash('sha256', $gateway->webhookUrl()),
            ]);

            return back()->with('success', 'Webhook do Asaas sincronizado para a empresa e gateway atuais.');
        } catch (BillingGatewayException $exception) {
            $gateway->mergeSettings([
                'webhook_sync_status' => 'failed',
                'webhook_sync_message' => $exception->getMessage(),
            ]);

            return back()->withErrors(['gateway' => $exception->getMessage()]);
        }
    }

    public function rotateWebhookEndpoint(
        PaymentGatewayConfig $gateway,
        BillingManager $billing,
        AuditLogger $audit,
    ): RedirectResponse {
        abort_unless($gateway->driver === 'asaas', 422, 'Somente gateways Asaas possuem endpoint de webhook.');

        $candidate = PaymentGatewayConfig::generateWebhookPublicToken();
        $url = rtrim((string) config('app.url'), '/')
            .route('webhooks.asaas', ['token' => $candidate], false);

        try {
            $result = $billing->forGateway($gateway)->synchronizeWebhook($gateway, $url);
            $webhookId = (string) ($result['id'] ?? $gateway->setting('webhook_external_id', ''));

            DB::transaction(function () use ($gateway, $candidate, $url, $webhookId): void {
                $gateway->setWebhookPublicToken($candidate);
                $gateway->settings = array_replace($gateway->settings ?? [], [
                    'webhook_external_id' => $webhookId,
                    'webhook_url' => $url,
                    'webhook_synced_at' => now()->toIso8601String(),
                    'webhook_sync_status' => 'success',
                    'webhook_sync_message' => null,
                ]);
                $gateway->save();
            });

            $audit->record('payment_gateway.webhook_endpoint_rotated', $gateway, [], [
                'webhook_url_hash' => hash('sha256', $url),
            ]);

            return back()->with('success', 'URL secreta do webhook regenerada e sincronizada no Asaas. A URL anterior deixou de funcionar.');
        } catch (BillingGatewayException $exception) {
            return back()->withErrors([
                'gateway' => 'Não foi possível regenerar o endpoint. A URL atual foi preservada. '.$exception->getMessage(),
            ]);
        }
    }

    public function destroy(PaymentGatewayConfig $gateway, AuditLogger $audit): RedirectResponse
    {
        try {
            $snapshot = $gateway->only(['id', 'driver', 'name', 'environment', 'active']);
            $gateway->delete();
            $audit->record('payment_gateway.deleted', $gateway, $snapshot);
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'gateway' => 'O gateway não pode ser removido porque possui vínculos financeiros.',
            ]);
        }

        return back()->with('success', 'Gateway removido.');
    }

    private function synchronizeGatewayWebhook(
        PaymentGatewayConfig $gateway,
        BillingManager $billing,
        string $url,
    ): array {
        $result = $billing->forGateway($gateway)->synchronizeWebhook($gateway, $url);
        $webhookId = (string) ($result['id'] ?? $gateway->setting('webhook_external_id', ''));

        $gateway->mergeSettings([
            'webhook_external_id' => $webhookId,
            'webhook_url' => $url,
            'webhook_synced_at' => now()->toIso8601String(),
            'webhook_sync_status' => 'success',
            'webhook_sync_message' => null,
        ]);

        return $result;
    }
}
