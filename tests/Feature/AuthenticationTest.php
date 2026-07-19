<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_available(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_active_super_admin_can_login_by_email(): void
    {
        $user = User::factory()->create(['is_super_admin' => true]);

        $this->post('/login', ['login' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('platform.dashboard'));

        $this->assertAuthenticatedAs($user);
    }
}
