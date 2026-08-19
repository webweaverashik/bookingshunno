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
            'reservations.escalate',        // Manager hands a decision up
            'reservations.update',
            'reservations.payment-request',
            'reservations.cancel',          // cancel before any payment
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

            // Repeating an email the visitor was already sent.
            'communications.resend',

            // ---------------------------------------------------------------
            // Gift Vouchers
            // ---------------------------------------------------------------
            'vouchers.view',
            'vouchers.create',
            'vouchers.update',
            'vouchers.cancel',
            'vouchers.redeem',

            /*
             | Deleting is not cancelling and does not inherit from
             | it. Cancelling withdraws a voucher and leaves a row saying why;
             | deleting removes the row. Only ever right for one created in
             | error, and never available for a voucher that was actually spent
             | — VoucherPolicy::delete() and the model enforce that half.
             */
            'vouchers.delete',

            // ---------------------------------------------------------------
            // Reports
            // ---------------------------------------------------------------
            'reports.view',
            'reports.export',

            /*
             | The three system logs — emails, gateway attempts, settings
             | changes. Admin only, and separate from reports.view because
             | reading how the studio is doing and reading the body of every
             | email it has sent are different acts. See ReportType::isLog().
             */
            'reports.logs',

            /*
             | Clearing a log is not the same act as reading one, so
             | it is not the same permission. Admin only; Manager keeps view and
             | export below.
             */
            'reports.clear',

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
        | The client's escalation rule. Manager prepares a request
        | and hands the decision up; Admin commits the studio to it. So:
        |
        |   REMOVED  reservations.approve   — approval is the commitment
        |   ADDED    reservations.escalate  — the way to ask for one
        |   WITHHELD reservations.cancel    — cancelling an approved visit is
        |                                     equally a decision about a
        |                                     commitment already made
        |
        | The client has now closed the question 10A left open:
        |
        |   REMOVED  reservations.decline   — a refusal is a decision about the
        |                                     studio's business, and the visitor
        |                                     is emailed the reason. It belongs
        |                                     with whoever answers for it.
        |
        | Manager now holds no ability that ENDS a reservation. Everything they
        | can do either keeps it moving (update, request-info, return-to-review)
        | or hands it to someone who can finish it (escalate). Approve, decline
        | and cancel — the three terminal acts — are Admin's alone.
        |
        | Manager keeps reservations.update, which is what lets them fix the
        | party size and the date/time before handing the request over — that is
        | the point of the escalation flow rather than an oversight.
        |
        | Worth noting what this also closed: Declined is reachable from
        | Approved, so a Manager previously had a route to undo an approval they
        | were never allowed to make. That is gone as a side effect.
        |
        | Nothing else changed to implement this. The policy is permission-driven
        | and the drawer renders only the actions the policy allows, so deleting
        | one line here removes the button, blocks the route and updates the
        | drawer's help text together.
        */

        $manager->syncPermissions([

            // Reservations
            'reservations.view',
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

            /*
             | Payments.
             |
             | PHASE 12C added payments.update-status, on the client's
             | instruction that a Manager may take payment offline. That is not
             | a reversal of the 10A/10B rule — recording money is not a
             | judgement. Approving, declining and cancelling are decisions
             | about the studio's business and stay with Admin; whether 1,500
             | taka arrived in the till is a fact, and the person holding it is
             | the one who knows.
             |
             | It does mean a Manager can move a reservation to Confirmed, since
             | settling a request in full does that. That follows mechanically
             | from the payment rather than from anyone's opinion, so it belongs
             | with whoever took the money.
             |
             | payments.verify stays with Admin. Phase 13 gives it a meaning —
             | trusting a gateway callback — and that is a different act from
             | writing down cash.
             */
            'payments.view',
            'payments.update-status',

            /*
             | Resending is a Manager job too. Repeating a message
             | the visitor was already sent is not a decision about the
             | studio's business — the content, the recipient and the
             | reservation are all unchanged. Whoever is on the phone to
             | somebody saying "I never got it" is the right person to fix it.
             */
            'communications.resend',

            /*
             | Vouchers.
             |
             | PHASE 14B added vouchers.redeem. The café credit rule requires
             | it: a visitor standing at the counter with a coupon needs
             | whoever is on the floor to honour it, and making that Admin-only
             | would leave somebody waiting while a manager phones the owner
             | about 300 taka of coffee.
             |
             | Creating and cancelling stay with Admin. Both give away or take
             | back the studio's money, which is the line 10A and 10B drew.
             */
            'vouchers.view',
            'vouchers.redeem',

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
