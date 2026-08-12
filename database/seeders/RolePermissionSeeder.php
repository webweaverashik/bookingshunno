<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        /*
        |--------------------------------------------------------------------------
        | Permissions
        |--------------------------------------------------------------------------
        */

        $permissions = [

            // ---------------------------------------------------------------
            // Reservations
            // ---------------------------------------------------------------
            'reservations.view',
            'reservations.approve',
            'reservations.decline',
            'reservations.request-info',
            'reservations.update',
            'reservations.payment-request',
            'reservations.cancel-paid',
            'reservations.discount-override',

            // ---------------------------------------------------------------
            // Visitors
            // ---------------------------------------------------------------
            'visitors.view',
            'visitors.update',

            // ---------------------------------------------------------------
            // Availability
            // ---------------------------------------------------------------
            'availability.view',
            'availability.update',
            'availability.block-date',
            'availability.update-capacity',

            // ---------------------------------------------------------------
            // Workshops
            // ---------------------------------------------------------------
            'workshops.view',
            'workshops.create',
            'workshops.update',
            'workshops.delete',
            'workshops.manage-availability',

            // ---------------------------------------------------------------
            // Payments
            // ---------------------------------------------------------------
            'payments.view',
            'payments.verify',
            'payments.update-status',

            // ---------------------------------------------------------------
            // Gift Vouchers
            // ---------------------------------------------------------------
            'vouchers.view',
            'vouchers.create',
            'vouchers.update',
            'vouchers.cancel',
            'vouchers.redeem',

            // ---------------------------------------------------------------
            // Reports
            // ---------------------------------------------------------------
            'reports.view',
            'reports.export',

            // ---------------------------------------------------------------
            // Settings
            // ---------------------------------------------------------------
            'settings.view',
            'settings.update',

            // ---------------------------------------------------------------
            // Users
            // ---------------------------------------------------------------
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
        ];

        /*
        |--------------------------------------------------------------------------
        | Create Permissions
        |--------------------------------------------------------------------------
        */

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name'       => $permission,
                'guard_name' => 'web',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Roles
        |--------------------------------------------------------------------------
        */

        $admin = Role::firstOrCreate([
            'name'       => 'Admin',
            'guard_name' => 'web',
        ]);

        $manager = Role::firstOrCreate([
            'name'       => 'Manager',
            'guard_name' => 'web',
        ]);

        $visitor = Role::firstOrCreate([
            'name'       => 'Visitor',
            'guard_name' => 'web',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Admin
        |--------------------------------------------------------------------------
        | Admin has full access to the entire administration system.
        */

        $admin->syncPermissions(Permission::all());

        /*
        |--------------------------------------------------------------------------
        | Manager
        |--------------------------------------------------------------------------
        | Manager handles day-to-day reservation operations.
        |
        | Restricted:
        | - Payments
        | - Payment requests
        | - Voucher management
        | - Capacity/date configuration
        | - Workshop management
        | - System settings
        | - User management
        | - Irreversible financial actions
        */

        $manager->syncPermissions([

            // Reservations
            'reservations.view',
            'reservations.approve',
            'reservations.decline',
            'reservations.request-info',
            'reservations.update',

            // Visitors
            'visitors.view',
            'visitors.update',

            // Availability - view only
            'availability.view',

            // Workshops - view only
            'workshops.view',

            // Payments - view only
            'payments.view',

            // Gift vouchers - view only
            'vouchers.view',

            // Reports
            'reports.view',
            'reports.export',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Visitor
        |--------------------------------------------------------------------------
        |
        | Visitor is primarily a public-facing role. No admin permissions
        | are assigned here.
        */

        $visitor->syncPermissions([]);
    }
}
