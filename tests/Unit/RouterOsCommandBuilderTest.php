<?php

namespace Tests\Unit;

use App\Services\Mikrotik\RouterOsCommandBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RouterOsCommandBuilderTest extends TestCase
{
    public function test_it_rejects_commands_outside_the_allowlist(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new RouterOsCommandBuilder())->build('/system reboot');
    }

    public function test_it_quotes_routeros_parameters_and_blocks_control_characters(): void
    {
        $builder = new RouterOsCommandBuilder();
        $command = $builder->build('sync-access', [
            'service_type' => 'hotspot', 'username' => 'cliente"teste',
            'password' => 'Senha$Forte', 'profile' => 'Plano 100M',
        ]);

        $this->assertStringContainsString('name="cliente\\"teste"', $command);
        $this->assertStringContainsString('password="Senha\\$Forte"', $command);

        $this->expectException(InvalidArgumentException::class);
        $builder->build('sync-access', ['username' => "cliente\nreboot", 'password' => 'senha']);
    }

    public function test_it_builds_scoped_session_disconnect_commands(): void
    {
        $command = (new RouterOsCommandBuilder())->build('disconnect-session', [
            'username' => 'cliente01',
            'framed_ip_address' => '192.168.88.10',
            'session_id' => 'sess-123',
        ]);

        $this->assertStringContainsString('/ip hotspot active find where user="cliente01" and address="192.168.88.10"', $command);
        $this->assertStringContainsString('/ppp active find where name="cliente01" and address="192.168.88.10"', $command);
        $this->assertStringContainsString('RadiusHub-session-sess-123', $command);
    }

    public function test_it_validates_session_rate_limit_and_ip(): void
    {
        $builder = new RouterOsCommandBuilder();
        $command = $builder->build('set-session-rate-limit', [
            'framed_ip_address' => '10.0.0.25',
            'session_id' => 'abc',
            'rate_limit' => '10M/50M',
        ]);

        $this->assertStringContainsString('target="10.0.0.25/32"', $command);
        $this->assertStringContainsString('max-limit="10M/50M"', $command);

        $this->expectException(InvalidArgumentException::class);
        $builder->build('set-session-rate-limit', [
            'framed_ip_address' => '10.0.0.25; /system reboot',
            'rate_limit' => '10M/50M',
        ]);
    }


    public function test_it_synchronizes_both_hotspot_and_pppoe_when_requested(): void
    {
        $command = (new RouterOsCommandBuilder())->build('sync-access', [
            'service_type' => 'both',
            'username' => 'dual-user',
            'password' => 'Senha123',
            'profile' => 'Plano Dual',
        ]);

        $this->assertStringContainsString('/ip hotspot user find', $command);
        $this->assertStringContainsString('/ppp secret find', $command);
    }

}
