<?php
namespace Database\Seeders;

use App\Models\Auth\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Super Admin
        $superAdmin = User::create([
            'name'          => 'Ashik',
            'email'         => 'webweaverashik@gmail.com',
            'phone'         => '01899999999',
            'password'      => Hash::make('q{}XCx]s~YE-4s*3'),
        ]);
        $superAdmin->assignRole('Admin');

        // Manager
        $manager = User::create([
            'name'          => 'Rahman',
            'email'         => 'manager@studioshunno.net',
            'phone'         => '01999999999',
            'password'      => Hash::make('password123'),
        ]);
        $manager->assignRole('Manager');
    }
}
