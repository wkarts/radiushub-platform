<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('SEED_ADMIN_EMAIL', 'admin@localhost');
        $password = (string) env('SEED_ADMIN_PASSWORD', 'ChangeMe@123!');

        $admin = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => env('SEED_ADMIN_NAME', 'Administrador'),
                'login' => 'admin',
                'password' => Hash::make($password),
                'is_super_admin' => true,
                'active' => true,
                'must_change_password' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->call(RolePermissionSeeder::class);

        if (filter_var(env('SEED_DEMO', false), FILTER_VALIDATE_BOOL) || config('playground.enabled')) {
            $this->call(PlaygroundSeeder::class);
        }
    }
}
