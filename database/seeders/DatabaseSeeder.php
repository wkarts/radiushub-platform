<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        if (config('playground.enabled')) {
            $this->call(PlaygroundSeeder::class);

            return;
        }

        $this->call(PlatformBootstrapSeeder::class);
    }
}
