<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Three roles, one guard.
 *
 * The Manager/Admin split follows the escalation matrix proposed in the Phase 0
 * addendum: Managers run the day-to-day queue, Admins own money, pricing and
 * anything that cannot be undone. AWAITING YOUR CONFIRMATION — if the matrix
 * changes, this seeder is the only place to change it.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $managerPermissions = [
            'reservations.view',
            'reservations.approve',
            'reservations.decline',
            'reservations.request-info',
            'reservations.payment-request',
            'visitors.view',
            'availability.manage',
            'vouchers.redeem',
            'credits.redeem',
            'reports.view',
        ];

        $adminOnlyPermissions = [
            'reservations.cancel-paid',
            'reservations.discount-override',
            'payments.mark-received',
            'payments.refund',
            'vouchers.generate',
            'workshops.manage',
            'settings.manage',
            'users.manage',
            'escalations.decide',
        ];

        foreach ([...$managerPermissions, ...$adminOnlyPermissions] as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Role::findOrCreate(User::ROLE_ADMIN, 'web')
            ->syncPermissions([...$managerPermissions, ...$adminOnlyPermissions]);

        Role::findOrCreate(User::ROLE_MANAGER, 'web')
            ->syncPermissions($managerPermissions);

        // Visitors hold no admin permissions; the role only gates the
        // "my reservations" area from Phase 15.
        Role::findOrCreate(User::ROLE_VISITOR, 'web');
    }
}
