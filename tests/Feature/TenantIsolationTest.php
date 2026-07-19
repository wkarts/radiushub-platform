<?php
namespace Tests\Feature;
use App\Models\Subscriber;use App\Models\Tenant;use App\Models\User;use App\Services\Tenancy\TenantContext;use Illuminate\Foundation\Testing\RefreshDatabase;use Tests\TestCase;
class TenantIsolationTest extends TestCase { use RefreshDatabase; public function test_subscriber_query_is_scoped_to_current_tenant(): void { $a=Tenant::create(['name'=>'A','slug'=>'a']);$b=Tenant::create(['name'=>'B','slug'=>'b']);$context=app(TenantContext::class);$context->set($a);Subscriber::create(['name'=>'Cliente A','type'=>'person','status'=>'active']);$context->clear();$context->set($b);Subscriber::create(['name'=>'Cliente B','type'=>'person','status'=>'active']);$this->assertSame(['Cliente B'],Subscriber::pluck('name')->all()); } }
