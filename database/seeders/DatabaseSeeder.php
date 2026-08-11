<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class, // roles and permissions first
            SettingSeeder::class,
            OperatingHourSeeder::class,
            WorkshopSeeder::class,
            VisitPurposeSeeder::class, // may reference workshops
            AdminUserSeeder::class,    // needs the Admin role to exist
        ]);
    }
}
