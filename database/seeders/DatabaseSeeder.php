<?php
namespace Database\Seeders;
use App\Models\InternetPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
class DatabaseSeeder extends Seeder { public function run(): void { $email=(string)env('SEED_ADMIN_EMAIL','admin@localhost');$password=(string)env('SEED_ADMIN_PASSWORD','ChangeMe@123!');$admin=User::query()->firstOrCreate(['email'=>$email],['name'=>env('SEED_ADMIN_NAME','Administrador'),'password'=>Hash::make($password),'is_super_admin'=>true,'active'=>true,'email_verified_at'=>now()]); if(filter_var(env('SEED_DEMO',false),FILTER_VALIDATE_BOOL)){ $tenant=Tenant::query()->firstOrCreate(['slug'=>'demonstracao'],['name'=>'Empresa Demonstração','timezone'=>'America/Bahia','active'=>true]);$admin->tenants()->syncWithoutDetaching([$tenant->id=>['role'=>'tenant_admin']]);app(TenantContext::class)->set($tenant);InternetPlan::query()->firstOrCreate(['name'=>'100 Mega'],['service_type'=>'both','download_bps'=>100000000,'upload_bps'=>50000000,'rate_limit'=>'50M/100M','simultaneous_use'=>1,'monthly_price'=>99.90,'active'=>true]);app(TenantContext::class)->clear(); } } }
