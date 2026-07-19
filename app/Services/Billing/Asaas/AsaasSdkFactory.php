<?php

declare(strict_types=1);

namespace App\Services\Billing\Asaas;

use App\Exceptions\BillingGatewayException;
use App\Models\PaymentGatewayConfig;
use Asaas\Sdk\AsaasSdk;
use Asaas\Sdk\Config\AsaasConfig;
use Asaas\Sdk\Http\Environment;

final class AsaasSdkFactory
{
    /** @var array<string, AsaasSdk> */
    private array $instances = [];

    public function make(PaymentGatewayConfig $gateway): AsaasSdk
    {
        $credentials = $gateway->credentials ?? [];
        $apiKey = trim((string) ($credentials['api_key'] ?? ''));

        if ($apiKey === '') {
            throw new BillingGatewayException('A API Key do Asaas não foi configurada para esta empresa.');
        }

        $environment = $gateway->environment === 'production'
            ? Environment::Production
            : Environment::Sandbox;

        $cacheKey = implode(':', [
            $gateway->getKey(),
            $gateway->environment,
            hash('sha256', $apiKey),
        ]);

        return $this->instances[$cacheKey] ??= new AsaasSdk(
            new AsaasConfig(
                apiKey: $apiKey,
                environment: $environment,
            )
        );
    }
}
