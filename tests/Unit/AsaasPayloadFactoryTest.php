<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\BillingGatewayException;
use App\Models\Invoice;
use App\Models\PaymentGatewayConfig;
use App\Models\Subscriber;
use App\Services\Billing\Asaas\AsaasPayloadFactory;
use Carbon\CarbonImmutable;
use Tests\TestCase;

final class AsaasPayloadFactoryTest extends TestCase
{
    public function test_it_builds_normalized_customer_and_payment_payloads(): void
    {
        $subscriber = new Subscriber([
            'name' => 'Cliente Teste',
            'document' => '00.586.050/0001-00',
            'email' => 'financeiro@example.com',
            'phone' => '(75) 3000-0000',
            'whatsapp' => '(75) 99999-0000',
            'zip_code' => '44000-000',
            'street' => 'Rua Teste',
            'number' => '10',
            'district' => 'Centro',
        ]);
        $subscriber->id = '11111111-1111-4111-8111-111111111111';

        $gateway = new PaymentGatewayConfig();
        $gateway->settings = ['notification_disabled' => true];

        $invoice = new Invoice([
            'amount' => 129.90,
            'description' => 'Mensalidade 07/2026',
            'due_date' => CarbonImmutable::parse('2026-07-30'),
        ]);
        $invoice->id = '22222222-2222-4222-8222-222222222222';

        $factory = new AsaasPayloadFactory();
        $customer = $factory->customer($subscriber, $gateway);
        $payment = $factory->payment($invoice, 'cus_123', 'PIX');

        self::assertSame('00586050000100', $customer['cpfCnpj']);
        self::assertSame('75999990000', $customer['mobilePhone']);
        self::assertTrue($customer['notificationDisabled']);
        self::assertSame('cus_123', $payment['customer']);
        self::assertSame('PIX', $payment['billingType']);
        self::assertSame('2026-07-30', $payment['dueDate']);
        self::assertSame('RADIUSHUB:invoice:22222222-2222-4222-8222-222222222222', $payment['externalReference']);
    }
    public function test_it_rejects_customer_without_valid_cpf_or_cnpj(): void
    {
        $subscriber = new Subscriber([
            'name' => 'Cliente sem documento',
            'document' => '123',
        ]);
        $subscriber->id = '33333333-3333-4333-8333-333333333333';

        $this->expectException(BillingGatewayException::class);
        $this->expectExceptionMessage('CPF ou CNPJ');

        (new AsaasPayloadFactory())->customer($subscriber, new PaymentGatewayConfig());
    }

}
