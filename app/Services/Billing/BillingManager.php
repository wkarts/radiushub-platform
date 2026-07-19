<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Billing\BillingGateway;
use App\Models\Invoice;
use App\Models\PaymentGatewayConfig;
use App\Services\Billing\Asaas\AsaasPayloadFactory;
use App\Services\Billing\Asaas\AsaasSdkFactory;
use RuntimeException;

final class BillingManager
{
    public function __construct(
        private readonly AsaasSdkFactory $asaasFactory,
        private readonly AsaasPayloadFactory $asaasPayloads,
    ) {
    }

    public function forInvoice(Invoice $invoice): BillingGateway
    {
        if ($invoice->gateway_driver === 'manual') {
            return new ManualGateway();
        }

        $gateway = $invoice->gateway
            ?? PaymentGatewayConfig::query()
                ->where('driver', $invoice->gateway_driver)
                ->where('active', true)
                ->first();

        if (! $gateway) {
            throw new RuntimeException("Gateway {$invoice->gateway_driver} não configurado para a empresa.");
        }

        return $this->forGateway($gateway);
    }

    public function forDriver(string $driver): BillingGateway
    {
        if ($driver === 'manual') {
            return new ManualGateway();
        }

        $gateway = PaymentGatewayConfig::query()
            ->where('driver', $driver)
            ->where('active', true)
            ->first();

        if (! $gateway) {
            throw new RuntimeException("Gateway {$driver} não configurado para a empresa.");
        }

        return $this->forGateway($gateway);
    }

    public function forGateway(PaymentGatewayConfig $gateway): BillingGateway
    {
        return match ($gateway->driver) {
            'manual' => new ManualGateway(),
            'asaas' => new AsaasGateway($gateway, $this->asaasFactory, $this->asaasPayloads),
            default => throw new RuntimeException("Gateway {$gateway->driver} ainda não possui adaptador operacional."),
        };
    }
}
