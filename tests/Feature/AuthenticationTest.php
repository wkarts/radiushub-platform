<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class AuthenticationTest extends TestCase { use RefreshDatabase; public function test_login_page_is_available(): void { $this->get('/login')->assertOk(); } public function test_active_user_can_login(): void { $user=User::factory()->create();$this->post('/login',['email'=>$user->email,'password'=>'password'])->assertRedirect('/');$this->assertAuthenticatedAs($user); } }
