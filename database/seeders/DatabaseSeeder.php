<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,          // roles and permissions first
            SettingSeeder::class,
            OperatingHourSeeder::class,
            WorkshopSeeder::class,
            VisitPurposeSeeder::class,  // may reference workshops
            UserSeeder::class,     // needs the Admin role to exist
        ]);
    }
}
