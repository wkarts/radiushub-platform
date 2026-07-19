<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Billing\BillingGateway;
use App\Exceptions\BillingGatewayException;
use App\Models\BillingCustomerLink;
use App\Models\Invoice;
use App\Models\PaymentGatewayConfig;
use App\Models\Subscriber;
use App\Services\Billing\Asaas\AsaasExceptionMapper;
use App\Services\Billing\Asaas\AsaasPayloadFactory;
use App\Services\Billing\Asaas\AsaasSdkFactory;
use Asaas\Sdk\AsaasSdk;
use Asaas\Sdk\Exception\ApiException;
use Illuminate\Support\Facades\Cache;
use Throwable;

final class AsaasGateway implements BillingGateway
{
    public function __construct(
        private readonly PaymentGatewayConfig $gateway,
        private readonly AsaasSdkFactory $factory,
        private readonly AsaasPayloadFactory $payloads,
    ) {
    }

    public function ensureCustomer(Subscriber $subscriber): string
    {
        try {
            return Cache::lock(
                'billing:asaas:customer:'.$this->gateway->id.':'.$subscriber->id,
                30,
            )->block(10, fn (): string => $this->ensureCustomerUnlocked($subscriber));
        } catch (Throwable $exception) {
            AsaasExceptionMapper::rethrow($exception, 'sincronização do cliente');
        }
    }

    private function ensureCustomerUnlocked(Subscriber $subscriber): string
    {
        $link = BillingCustomerLink::query()
            ->where('subscriber_id', $subscriber->id)
            ->where('payment_gateway_config_id', $this->gateway->id)
            ->first();

        if ($link && data_get($link->payload, '_radiushub.gateway_fingerprint') === $this->gatewayFingerprint()) {
            return $link->external_customer_id;
        }

        if ($link) {
            $link->delete();
            $subscriber->forceFill(['gateway_customer_id' => null])->save();
        }

        $externalReference = 'RADIUSHUB:subscriber:'.$subscriber->id;
        $query = ['externalReference' => $externalReference, 'limit' => 1];
        $document = preg_replace('/\D+/', '', (string) $subscriber->document);

        if ($document !== '') {
            $query = ['cpfCnpj' => $document, 'limit' => 1];
        }

        $found = $this->sdk()->customer->listCustomers(query: $query);
        $customerId = (string) data_get($found, 'data.0.id', '');

        if ($customerId === '') {
            $created = $this->sdk()->customer->createNewCustomer(
                payload: $this->payloads->customer($subscriber, $this->gateway)
            );
            $customerId = (string) ($created['id'] ?? '');

            if ($customerId === '') {
                throw new BillingGatewayException('Asaas não retornou o identificador do cliente criado.');
            }

            return $this->persistCustomerLink($subscriber, $customerId, $created);
        }

        return $this->persistCustomerLink($subscriber, $customerId, (array) data_get($found, 'data.0', []));
    }

    public function syncCustomer(Subscriber $subscriber): array
    {
        try {
            $customerId = $this->ensureCustomer($subscriber);
            $updated = $this->sdk()->customer->updateExistingCustomer(
                pathParams: ['id' => $customerId],
                payload: $this->payloads->customer($subscriber, $this->gateway),
            );

            $this->persistCustomerLink($subscriber, $customerId, (array) $updated);

            return (array) $updated;
        } catch (Throwable $exception) {
            AsaasExceptionMapper::rethrow($exception, 'atualização do cliente');
        }
    }

    public function createOrUpdateCharge(Invoice $invoice, string $billingType = 'UNDEFINED'): array
    {
        try {
            return Cache::lock(
                'billing:asaas:invoice:'.$this->gateway->id.':'.$invoice->id,
                60,
            )->block(15, fn (): array => $this->createOrUpdateChargeUnlocked($invoice, $billingType));
        } catch (Throwable $exception) {
            AsaasExceptionMapper::rethrow($exception, 'emissão ou atualização da cobrança');
        }
    }

    /** @return array<string, mixed> */
    private function createOrUpdateChargeUnlocked(Invoice $invoice, string $billingType): array
    {
        $invoice->refresh()->loadMissing('subscriber');
        $customer = $this->syncCustomer($invoice->subscriber);
        $customerId = (string) ($customer['id'] ?? '');

        if ($customerId === '') {
            $customerId = $this->ensureCustomer($invoice->subscriber);
        }

        $payload = $this->payloads->payment($invoice, $customerId, $billingType);
        $paymentId = (string) $invoice->external_id;
        $storedFingerprint = trim((string) data_get($invoice->metadata, 'gateway_fingerprint', ''));

        if ($storedFingerprint !== '' && ! hash_equals($storedFingerprint, $this->gatewayFingerprint())) {
            $paymentId = '';
        }

        if ($paymentId === '') {
            $existing = $this->sdk()->payment->listPayments(query: [
                'externalReference' => 'RADIUSHUB:invoice:'.$invoice->id,
                'limit' => 1,
            ]);
            $paymentId = (string) data_get($existing, 'data.0.id', '');
        }

        if ($paymentId !== '') {
            $updatePayload = $payload;
            unset($updatePayload['customer']);

            $payment = $this->sdk()->payment->updateExistingPayment(
                pathParams: ['id' => $paymentId],
                payload: $updatePayload,
            );
        } else {
            $payment = $this->sdk()->payment->createNewPayment(payload: $payload);
        }

        return $this->withPaymentArtifacts((array) $payment);
    }

    public function retrieveCharge(Invoice $invoice): array
    {
        $this->assertInvoiceGatewayFingerprint($invoice);

        $paymentId = trim((string) $invoice->external_id);

        if ($paymentId === '') {
            throw new BillingGatewayException('A fatura ainda não possui cobrança Asaas vinculada.');
        }

        try {
            $payment = $this->sdk()->payment->retrieveASinglePayment(
                pathParams: ['id' => $paymentId],
            );

            return $this->withPaymentArtifacts((array) $payment);
        } catch (Throwable $exception) {
            AsaasExceptionMapper::rethrow($exception, 'consulta da cobrança');
        }
    }

    public function cancelCharge(Invoice $invoice): void
    {
        $this->assertInvoiceGatewayFingerprint($invoice);

        if (! $invoice->external_id) {
            return;
        }

        try {
            $this->sdk()->payment->deletePayment(pathParams: ['id' => $invoice->external_id]);
        } catch (Throwable $exception) {
            AsaasExceptionMapper::rethrow($exception, 'cancelamento da cobrança');
        }
    }

    public function refundCharge(Invoice $invoice, float $amount, ?string $description = null): array
    {
        $this->assertInvoiceGatewayFingerprint($invoice);

        if (! $invoice->external_id) {
            throw new BillingGatewayException('A fatura não possui cobrança Asaas para estorno.');
        }

        $alreadyRequested = (float) $invoice->refunds()
            ->where('status', '!=', 'PAYMENT_REFUND_DENIED')
            ->sum('amount');
        $remaining = max(0, (float) $invoice->paid_amount - $alreadyRequested);

        if ($amount <= 0 || $amount > $remaining) {
            throw new BillingGatewayException('O valor do estorno deve ser positivo e não pode ultrapassar o saldo disponível para estorno.');
        }

        try {
            $payload = ['value' => $amount];

            if ($description !== null && trim($description) !== '') {
                $payload['description'] = trim($description);
            }

            return (array) $this->sdk()->payment->refundPayment(
                pathParams: ['id' => $invoice->external_id],
                payload: $payload,
            );
        } catch (Throwable $exception) {
            AsaasExceptionMapper::rethrow($exception, 'estorno da cobrança');
        }
    }

    public function testConnection(): array
    {
        try {
            $payments = (array) $this->sdk()->payment->listPayments(query: ['limit' => 1]);

            return [
                'ok' => true,
                'environment' => $this->gateway->environment,
                'sample_count' => count((array) ($payments['data'] ?? [])),
                'has_more' => (bool) ($payments['hasMore'] ?? false),
            ];
        } catch (Throwable $exception) {
            AsaasExceptionMapper::rethrow($exception, 'teste de conexão');
        }
    }

    public function synchronizeWebhook(PaymentGatewayConfig $gateway, string $url): array
    {
        try {
            $gateway->loadMissing('tenant');
            $settings = $gateway->settings ?? [];
            $webhookId = trim((string) ($settings['webhook_external_id'] ?? ''));
            $payload = [
                'name' => (string) config('asaas.webhook.name', 'RadiusHub - Pagamentos'),
                'url' => $url,
                'email' => (string) ($settings['webhook_email'] ?? $gateway->tenant?->email ?? ''),
                'enabled' => $gateway->active,
                'interrupted' => false,
                'apiVersion' => 3,
                'authToken' => (string) $gateway->webhook_token,
                'sendType' => (string) config('asaas.webhook.send_type', 'SEQUENTIALLY'),
                'events' => (array) config('asaas.webhook.events', []),
            ];

            if ($payload['email'] === '') {
                throw new BillingGatewayException('Informe um e-mail no tenant ou nas configurações do gateway para cadastrar o webhook.');
            }

            if ($webhookId === '') {
                $list = (array) $this->sdk()->webhook->listWebhooks();
                $existing = collect((array) ($list['data'] ?? []))
                    ->first(fn (array $item): bool => ($item['url'] ?? null) === $url);
                $webhookId = (string) ($existing['id'] ?? '');
            }

            if ($webhookId !== '') {
                $updatePayload = $payload;
                unset($updatePayload['email'], $updatePayload['apiVersion']);

                try {
                    $webhook = $this->sdk()->webhook->updateExistingWebhook(
                        pathParams: ['id' => $webhookId],
                        payload: $updatePayload,
                    );
                } catch (ApiException $exception) {
                    if ($exception->getStatusCode() !== 404) {
                        throw $exception;
                    }

                    $webhook = $this->sdk()->webhook->createNewWebhook(payload: $payload);
                }
            } else {
                $webhook = $this->sdk()->webhook->createNewWebhook(payload: $payload);
            }

            return (array) $webhook;
        } catch (Throwable $exception) {
            AsaasExceptionMapper::rethrow($exception, 'sincronização do webhook');
        }
    }

    public function parseWebhook(array $payload): array
    {
        return [
            'event_id' => (string) ($payload['id'] ?? ''),
            'event' => (string) ($payload['event'] ?? ''),
            'payment' => (array) ($payload['payment'] ?? []),
            'raw' => $payload,
        ];
    }

    private function sdk(): AsaasSdk
    {
        return $this->factory->make($this->gateway);
    }

    /** @param array<string, mixed> $payment
     *  @return array<string, mixed>
     */
    private function withPaymentArtifacts(array $payment): array
    {
        $payment['_radiushubGatewayFingerprint'] = $this->gatewayFingerprint();
        $paymentId = (string) ($payment['id'] ?? '');
        $billingType = strtoupper((string) ($payment['billingType'] ?? 'UNDEFINED'));

        if ($paymentId === '') {
            return $payment;
        }

        if (in_array($billingType, ['PIX', 'UNDEFINED'], true)) {
            try {
                $pix = (array) $this->sdk()->payment->getQrCodeForPixPayments(
                    pathParams: ['id' => $paymentId],
                );
                $payment['pixCopyPaste'] = $pix['payload'] ?? null;
                $payment['pixEncodedImage'] = $pix['encodedImage'] ?? null;
                $payment['pixExpirationDate'] = $pix['expirationDate'] ?? null;
            } catch (Throwable) {
                // Cobranças ainda não elegíveis para Pix continuam válidas.
            }
        }

        if (in_array($billingType, ['BOLETO', 'UNDEFINED'], true)) {
            try {
                $bill = (array) $this->sdk()->payment->getDigitableBillLine(
                    pathParams: ['id' => $paymentId],
                );
                $payment['bankSlipIdentificationField'] = $bill['identificationField'] ?? null;
                $payment['bankSlipBarCode'] = $bill['barCode'] ?? null;
            } catch (Throwable) {
                // Cobranças ainda não elegíveis para boleto continuam válidas.
            }
        }

        return $payment;
    }


    private function assertInvoiceGatewayFingerprint(Invoice $invoice): void
    {
        $storedFingerprint = trim((string) data_get($invoice->metadata, 'gateway_fingerprint', ''));

        if ($storedFingerprint !== '' && ! hash_equals($storedFingerprint, $this->gatewayFingerprint())) {
            throw new BillingGatewayException(
                'A cobrança pertence a outra conta ou ambiente Asaas. Reemita a cobrança pendente no gateway atual.',
            );
        }
    }

    private function gatewayFingerprint(): string
    {
        $apiKey = (string) data_get($this->gateway->credentials, 'api_key', '');

        return hash('sha256', implode('|', [
            (string) $this->gateway->id,
            $this->gateway->environment,
            $apiKey,
        ]));
    }

    /** @param array<string, mixed> $payload */
    private function persistCustomerLink(Subscriber $subscriber, string $customerId, array $payload): string
    {
        BillingCustomerLink::query()->updateOrCreate(
            [
                'subscriber_id' => $subscriber->id,
                'payment_gateway_config_id' => $this->gateway->id,
            ],
            [
                'external_customer_id' => $customerId,
                'payload' => [
                    '_radiushub' => [
                        'environment' => $this->gateway->environment,
                        'gateway_fingerprint' => $this->gatewayFingerprint(),
                    ],
                    'remote' => $payload,
                ],
                'last_synced_at' => now(),
            ],
        );

        if ($subscriber->gateway_customer_id !== $customerId) {
            $subscriber->forceFill(['gateway_customer_id' => $customerId])->save();
        }

        return $customerId;
    }
}
