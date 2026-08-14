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
            'reservations.escalate',        // PHASE 10A — Manager hands a decision up
            'reservations.update',
            'reservations.payment-request',
            'reservations.cancel',          // PHASE 10A — cancel before any payment
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
        | PHASE 10A — the client's escalation rule. Manager prepares a request
        | and hands the decision up; Admin commits the studio to it. So:
        |
        |   REMOVED  reservations.approve   — approval is the commitment
        |   ADDED    reservations.escalate  — the way to ask for one
        |   WITHHELD reservations.cancel    — cancelling an approved visit is
        |                                     equally a decision about a
        |                                     commitment already made
        |
        | Manager keeps reservations.update, which is what lets them fix the
        | party size and the date/time before handing the request over — that is
        | the point of the escalation flow rather than an oversight.
        |
        | STILL WITH THE CLIENT: reservations.decline. The instruction named
        | approve and cancel; declining is arguably the same kind of decision
        | and has been left with Manager only because removing it was not asked
        | for. If the intent is that Manager escalates EVERY outcome, delete the
        | line below and nothing else changes.
        */

        $manager->syncPermissions([

            // Reservations
            'reservations.view',
            'reservations.decline',
            'reservations.request-info',
            'reservations.escalate',
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
