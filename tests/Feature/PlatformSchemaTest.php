<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlatformSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_multitenant_ssh_and_voucher_schema_is_available(): void
    {
        $this->assertTrue(Schema::hasColumns('tenants', ['subscription_plan', 'usage_limits', 'status']));
        $this->assertTrue(Schema::hasColumns('companies', ['tenant_id', 'usage_limits', 'status']));
        $this->assertTrue(Schema::hasColumns('mikrotik_devices', [
            'company_id', 'connection_method', 'ssh_port', 'ssh_username',
            'ssh_private_key_ciphertext', 'ssh_host_fingerprint', 'routeros_version',
        ]));
        $this->assertTrue(Schema::hasColumns('vouchers', [
            'tenant_id', 'company_id', 'code', 'password_ciphertext',
            'validity_mode', 'first_access_at', 'last_access_at', 'status',
        ]));
        $this->assertTrue(Schema::hasTable('roles'));
        $this->assertTrue(Schema::hasTable('permissions'));
        $this->assertTrue(Schema::hasTable('mikrotik_connection_logs'));
        $this->assertTrue(Schema::hasTable('mikrotik_command_logs'));
        $this->assertTrue(Schema::hasColumns('payment_gateway_configs', [
            'company_id', 'webhook_public_token', 'webhook_public_token_hash',
        ]));
        $this->assertTrue(Schema::hasColumns('webhook_events', [
            'tenant_id', 'company_id', 'payment_gateway_config_id', 'external_event_id',
        ]));
    }
}
