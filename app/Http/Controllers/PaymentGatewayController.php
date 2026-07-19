<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\BillingGatewayException;
use App\Http\Requests\GatewayRequest;
use App\Models\Invoice;
use App\Models\PaymentGatewayConfig;
use App\Models\Subscriber;
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

    public function store(GatewayRequest $request): RedirectResponse
    {
        $data = $request->validated();

        PaymentGatewayConfig::query()->create([
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
            'webhook_token' => $data['webhook_token'] ?? bin2hex(random_bytes(32)),
        ]);

        return back()->with('success', 'Gateway cadastrado. Teste a conexão e sincronize o webhook.');
    }

    public function update(GatewayRequest $request, PaymentGatewayConfig $gateway): RedirectResponse
    {
        $data = $request->validated();
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

    public function synchronizeWebhook(PaymentGatewayConfig $gateway, BillingManager $billing): RedirectResponse
    {
        try {
            $url = rtrim((string) config('app.url'), '/').route('webhooks.asaas', ['tenant' => $gateway->tenant->slug], false);
            $result = $billing->forGateway($gateway)->synchronizeWebhook($gateway, $url);
            $webhookId = (string) ($result['id'] ?? $gateway->setting('webhook_external_id', ''));

            $gateway->mergeSettings([
                'webhook_external_id' => $webhookId,
                'webhook_url' => $url,
                'webhook_synced_at' => now()->toIso8601String(),
                'webhook_sync_status' => 'success',
            ]);

            return back()->with('success', 'Webhook do Asaas sincronizado.');
        } catch (BillingGatewayException $exception) {
            $gateway->mergeSettings([
                'webhook_sync_status' => 'failed',
                'webhook_sync_message' => $exception->getMessage(),
            ]);

            return back()->withErrors(['gateway' => $exception->getMessage()]);
        }
    }

    public function destroy(PaymentGatewayConfig $gateway): RedirectResponse
    {
        try {
            $gateway->delete();
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'gateway' => 'O gateway não pode ser removido porque possui vínculos financeiros.',
            ]);
        }

        return back()->with('success', 'Gateway removido.');
    }
}
