<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * A first Admin so Phase 5 has something to log in with.
 *
 * The password comes from the environment and is never committed. If
 * ADMIN_PASSWORD is unset, a random one is generated and printed once — set a
 * real one before this ever runs on the production server.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email    = env('ADMIN_EMAIL', 'admin@studioshunno.net');
        $password = env('ADMIN_PASSWORD');
        $generated = false;

        if (blank($password)) {
            $password  = Str::password(16);
            $generated = true;
        }

        $admin = User::updateOrCreate(
            ['email' => $email],
            [
                'name'            => env('ADMIN_NAME', 'Shunno Admin'),
                'password'        => Hash::make($password),
                'password_set_at' => now(),
                'is_active'       => true,
                'source'          => 'admin',
            ],
        );

        $admin->syncRoles([User::ROLE_ADMIN]);

        if ($generated) {
            $this->command?->warn("Generated admin password for {$email}: {$password}");
            $this->command?->warn('Save it now — it is not stored anywhere and will not be shown again.');
        }
    }
}
