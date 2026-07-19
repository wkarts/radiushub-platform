<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantUserPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_cannot_create_a_subscriber(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Empresa', 'slug' => 'empresa', 'active' => true]);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => 'viewer']);

        $response = $this->actingAs($user)
            ->withSession([config('tenancy.session_key') => $tenant->id])
            ->post(route('subscribers.store'), []);

        $response->assertForbidden();
    }

    public function test_tenant_admin_can_open_user_management(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Empresa', 'slug' => 'empresa', 'active' => true]);
        $user = User::factory()->create();
        $user->tenants()->attach($tenant->id, ['role' => 'tenant_admin']);

        $response = $this->actingAs($user)
            ->withSession([config('tenancy.session_key') => $tenant->id])
            ->get(route('users.index'));

        $response->assertOk();
    }
}
