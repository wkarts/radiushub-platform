<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\ProcessAsaasWebhook;
use App\Models\Company;
use App\Models\PaymentGatewayConfig;
use App\Models\Tenant;
use App\Services\Tenancy\CompanyContext;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class AsaasWebhookIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_endpoint_resolves_the_exact_company_and_gateway_by_secret_url_token(): void
    {
        Queue::fake();

        [$tenant, $company, $gateway] = $this->createGateway('Empresa A');
        $token = $gateway->webhook_public_token;

        app(CompanyContext::class)->clear();
        app(TenantContext::class)->clear();

        $response = $this->postJson(
            route('webhooks.asaas', ['token' => $token]),
            [
                'id' => 'evt_company_a_001',
                'event' => 'PAYMENT_RECEIVED',
                'payment' => ['id' => 'pay_company_a_001'],
            ],
            ['asaas-access-token' => $gateway->webhook_token],
        );

        $response->assertOk()->assertJson(['received' => true, 'duplicate' => false]);
        $this->assertDatabaseHas('webhook_events', [
            'tenant_id' => $tenant->id,
            'company_id' => $company->id,
            'payment_gateway_config_id' => $gateway->id,
            'external_event_id' => 'evt_company_a_001',
        ]);
        Queue::assertPushed(ProcessAsaasWebhook::class);
    }

    public function test_same_external_event_id_can_exist_in_different_company_gateways_without_collision(): void
    {
        Queue::fake();

        [, , $gatewayA] = $this->createGateway('Empresa A', 'tenant-a');
        app(CompanyContext::class)->clear();
        app(TenantContext::class)->clear();
        [, , $gatewayB] = $this->createGateway('Empresa B', 'tenant-b');
        app(CompanyContext::class)->clear();
        app(TenantContext::class)->clear();

        $payload = [
            'id' => 'evt_shared_identifier',
            'event' => 'PAYMENT_RECEIVED',
            'payment' => ['id' => 'pay_shared_identifier'],
        ];

        $this->postJson(
            route('webhooks.asaas', ['token' => $gatewayA->webhook_public_token]),
            $payload,
            ['asaas-access-token' => $gatewayA->webhook_token],
        )->assertOk();

        $this->postJson(
            route('webhooks.asaas', ['token' => $gatewayB->webhook_public_token]),
            $payload,
            ['asaas-access-token' => $gatewayB->webhook_token],
        )->assertOk();

        $this->assertDatabaseCount('webhook_events', 2);
    }

    public function test_invalid_public_url_token_is_rejected_without_revealing_tenant_or_company(): void
    {
        $response = $this->postJson(
            '/webhooks/asaas/'.str_repeat('a', 96),
            ['id' => 'evt_invalid', 'event' => 'PAYMENT_RECEIVED'],
            ['asaas-access-token' => 'invalid'],
        );

        $response->assertNotFound();
        $this->assertDatabaseCount('webhook_events', 0);
    }

    /** @return array{Tenant, Company, PaymentGatewayConfig} */
    private function createGateway(string $companyName, ?string $slug = null): array
    {
        $slug ??= strtolower(str_replace(' ', '-', $companyName));
        $tenant = Tenant::query()->create([
            'name' => 'Tenant '.$companyName,
            'slug' => $slug,
            'email' => 'tenant-'.$slug.'@example.com',
            'active' => true,
            'status' => 'active',
        ]);
        $company = Company::query()->create([
            'tenant_id' => $tenant->id,
            'legal_name' => $companyName,
            'email' => 'financeiro-'.$slug.'@example.com',
            'active' => true,
            'status' => 'active',
        ]);

        app(TenantContext::class)->set($tenant);
        app(CompanyContext::class)->set($company);

        $gateway = PaymentGatewayConfig::query()->create([
            'name' => 'Asaas '.$companyName,
            'driver' => 'asaas',
            'environment' => 'sandbox',
            'active' => true,
            'credentials' => ['api_key' => 'test-api-key-'.$slug],
            'webhook_token' => 'header-token-'.$slug,
        ]);

        return [$tenant, $company, $gateway];
    }
}
