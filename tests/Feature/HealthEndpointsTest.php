<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HealthEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_liveness_endpoint_is_public_and_reports_version(): void
    {
        $this->getJson('/health/live')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('service', 'radiushub')
            ->assertJsonPath('version', config('app.version'));
    }

    public function test_readiness_endpoint_validates_database_cache_and_storage(): void
    {
        $this->getJson('/health/ready')
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonPath('checks.cache', 'ok')
            ->assertJsonPath('checks.storage', 'ok');
    }
}
