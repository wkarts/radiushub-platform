<?php

declare(strict_types=1);

namespace Tests\Unit;

use Asaas\Sdk\AsaasSdk;
use Asaas\Sdk\Config\AsaasConfig;
use Asaas\Sdk\Http\Environment;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class AsaasSdkPackageIntegrationTest extends TestCase
{
    public function test_the_vendored_sdk_creates_a_payment_using_the_expected_endpoint(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'id' => 'pay_123',
                'status' => 'PENDING',
            ], JSON_THROW_ON_ERROR)),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $sdk = new AsaasSdk(new AsaasConfig(
            apiKey: 'test_key',
            environment: Environment::Sandbox,
            httpClient: new Client(['handler' => $stack, 'base_uri' => Environment::Sandbox->value]),
        ));

        $result = $sdk->payment->createNewPayment(payload: [
            'customer' => 'cus_123',
            'billingType' => 'PIX',
            'value' => 10.00,
            'dueDate' => '2026-07-30',
        ]);

        self::assertSame('pay_123', $result['id']);
        self::assertCount(1, $history);
        self::assertSame('POST', $history[0]['request']->getMethod());
        self::assertSame('/v3/payments', $history[0]['request']->getUri()->getPath());
        self::assertSame('test_key', $history[0]['request']->getHeaderLine('access_token'));
    }
}
