<?php

use App\Http\Controllers\Admin\AvailabilityController;
use App\Http\Controllers\Admin\BlockedDateController;
use App\Http\Controllers\Admin\CommunicationController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\Admin\VoucherController;
use App\Http\Controllers\Admin\ReservationDecisionController;
use App\Http\Controllers\Admin\VisitorController;
use App\Http\Controllers\Admin\WorkshopController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'isLoggedIn', 'role:Admin|Manager'])
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/logout', fn() => redirect()->back())->name('logout.get');

        Route::get('clear-cache', function () {
            clearServerCache();
            return response()->json(['success' => true]);
        })->name('clear.cache');

        /*
        |----------------------------------------------------------------------
        | PHASE 9 — Reservations
        | PHASE 10 — Approval workflow
        |----------------------------------------------------------------------
        | Bound on the reference code, which is Reservation's route key: an
        | admin pasting SHN-2608-A7K3 from an email lands on the right record,
        | and the sequential id stays out of the URL.
        |
        | Gated on reservations.view for the whole group; ReservationPolicy
        | handles every per-action ability, because the decisions each depend on
        | BOTH a permission and where the reservation currently sits. Route
        | middleware cannot express the second half, so there is deliberately no
        | permission middleware on the decision routes — the policy is the gate,
        | and it is called in the controller.
        */
        Route::prefix('reservations')
            ->name('reservations.')
            ->middleware('permission:reservations.view')
            ->group(function () {
                Route::get('/', [ReservationController::class, 'index'])->name('index');
                Route::get('list', [ReservationController::class, 'list'])->name('list');
                Route::get('{reservation}', [ReservationController::class, 'show'])->name('show');

                Route::middleware('permission:reservations.update')->group(function () {
                    Route::get('{reservation}/edit', [ReservationController::class, 'edit'])->name('edit');
                    Route::get('{reservation}/slots', [ReservationController::class, 'slots'])->name('slots');
                    Route::post('{reservation}', [ReservationController::class, 'update'])->name('update');
                });

                // Decisions. Authorised by ReservationPolicy inside the
                // controller — see the note above.
                Route::post('{reservation}/approve', [ReservationDecisionController::class, 'approve'])->name('approve');
                Route::post('{reservation}/escalate', [ReservationDecisionController::class, 'escalate'])->name('escalate');
                Route::post('{reservation}/decline', [ReservationDecisionController::class, 'decline'])->name('decline');
                Route::post('{reservation}/request-info', [ReservationDecisionController::class, 'requestInfo'])->name('request-info');
                Route::post('{reservation}/return-to-review', [ReservationDecisionController::class, 'returnToReview'])->name('return-to-review');
                Route::post('{reservation}/cancel', [ReservationDecisionController::class, 'cancel'])->name('cancel');
            });

        /*
        |----------------------------------------------------------------------
        | PHASE 6 — Workshops
        |----------------------------------------------------------------------
        */
        Route::prefix('workshops')
            ->name('workshops.')
            ->middleware('permission:workshops.view')
            ->group(function () {
                Route::get('/', [WorkshopController::class, 'index'])->name('index');
                Route::get('rows', [WorkshopController::class, 'rows'])->name('rows');
                Route::post('/', [WorkshopController::class, 'store'])->name('store');
                Route::get('{workshop:id}/edit', [WorkshopController::class, 'edit'])->name('edit');
                Route::post('{workshop:id}', [WorkshopController::class, 'update'])->name('update');
                Route::post('{workshop:id}/toggle', [WorkshopController::class, 'toggle'])->name('toggle');
                Route::delete('{workshop:id}', [WorkshopController::class, 'destroy'])->name('destroy');
            });

        /*
        |----------------------------------------------------------------------
        | PHASE 7 — Availability
        |----------------------------------------------------------------------
        | Gated on availability.view; BlockedDatePolicy handles the write
        | abilities per action. Manager holds view only and so reaches the page
        | read-only rather than being bounced to a 403.
        |
        | POST rather than PUT/PATCH throughout, matching the workshops module:
        | one verb across the admin panel is one fewer thing to get wrong.
        */
        Route::prefix('availability')
            ->name('availability.')
            ->middleware('permission:availability.view')
            ->group(function () {
                Route::get('/', [AvailabilityController::class, 'index'])->name('index');
                Route::post('hours', [AvailabilityController::class, 'updateHours'])->name('hours');
                Route::post('rules', [AvailabilityController::class, 'updateRules'])->name('rules');

                Route::prefix('blocked')
                    ->name('blocked.')
                    ->group(function () {
                        Route::post('/', [BlockedDateController::class, 'store'])->name('store');
                        Route::get('{blockedDate:id}/edit', [BlockedDateController::class, 'edit'])->name('edit');
                        Route::post('{blockedDate:id}', [BlockedDateController::class, 'update'])->name('update');
                        Route::delete('{blockedDate:id}', [BlockedDateController::class, 'destroy'])->name('destroy');
                    });
            });

        /*
        |----------------------------------------------------------------------
        | PHASE 8 — Visitors
        |----------------------------------------------------------------------
        | Bound on {visitor:id}. No policy class: a policy on
        | App\Models\Auth\User would also govern staff-user management in a
        | later phase, and the two run on different permissions (visitors.*
        | against users.*). Authorisation is therefore by permission name, on
        | the routes and again in the views.
        |
        | Manager holds both visitors.view and visitors.update — they run
        | day-to-day reservation operations and need to fix a mistyped phone
        | number without waiting for an Admin.
        */
        Route::prefix('visitors')
            ->name('visitors.')
            ->middleware('permission:visitors.view')
            ->group(function () {
                Route::get('/', [VisitorController::class, 'index'])->name('index');
                Route::get('list', [VisitorController::class, 'list'])->name('list');
                Route::get('{visitor:id}', [VisitorController::class, 'show'])->name('show');

                Route::middleware('permission:visitors.update')->group(function () {
                    Route::get('{visitor:id}/edit', [VisitorController::class, 'edit'])->name('edit');
                    Route::post('{visitor:id}', [VisitorController::class, 'update'])->name('update');
                });
            });

        /*
        |----------------------------------------------------------------------
        | PHASE 12A — Payments
        |----------------------------------------------------------------------
        | Gated on payments.view for the whole group; PaymentPolicy handles the
        | write abilities per action, because record and cancel both depend on
        | the payment still being open as well as on a permission — which route
        | middleware cannot express.
        |
        | Manager holds payments.view and nothing else, so they reach the
        | register read-only. That is useful rather than incidental: whoever is
        | on the floor needs to see whether tonight's visitor has paid.
        |
        | Two identifiers in play. These routes bind on {payment:reference} —
        | PAY-2608-K4RT, the code read out over the phone. The visitor's own
        | payment page in Phase 12B binds on the token instead, which is the
        | long random string and is never shown in the admin panel.
        |
        | 'list' is registered before {payment} so the literal wins; same
        | ordering as the reservations group.
        */
        Route::prefix('payments')
            ->name('payments.')
            ->middleware('permission:payments.view')
            ->group(function () {
                Route::get('/', [PaymentController::class, 'index'])->name('index');
                Route::get('list', [PaymentController::class, 'list'])->name('list');

                // Requesting payment is about a RESERVATION, so it binds on the
                // reservation and is authorised by ReservationPolicy. It lives
                // here rather than in the reservations group because everything
                // it touches is a payment.
                Route::get('request/{reservation}', [PaymentController::class, 'create'])->name('create');
                Route::post('request/{reservation}', [PaymentController::class, 'store'])->name('store');

                Route::get('{payment:reference}', [PaymentController::class, 'show'])->name('show');
                Route::post('{payment:reference}/record', [PaymentController::class, 'record'])->name('record');
                Route::post('{payment:reference}/cancel', [PaymentController::class, 'cancel'])->name('cancel');
            });

        /*
        |----------------------------------------------------------------------
        | PHASE 13B — communications
        |----------------------------------------------------------------------
        | Two list endpoints rather than one filtered one: a reservation drawer
        | wants everything ever sent about the booking, a payment drawer wants
        | only what concerns that request, and merging them would make the
        | payment drawer responsible for excluding approval emails.
        |
        | Authorised per action by CommunicationPolicy — resending depends on
        | the message as well as the person, since internal mail can never be
        | repeated whoever asks.
        */
        Route::prefix('communications')
            ->name('communications.')
            ->group(function () {
                Route::get('reservation/{reservation}', [CommunicationController::class, 'forReservation'])
                    ->name('reservation');

                Route::get('payment/{payment:reference}', [CommunicationController::class, 'forPayment'])
                    ->name('payment');

                Route::post('{communication}/resend', [CommunicationController::class, 'resend'])
                    ->middleware('throttle:6,1')
                    ->name('resend');
            });

        /*
        |----------------------------------------------------------------------
        | PHASE 14B — vouchers
        |----------------------------------------------------------------------
        | Gated on vouchers.view for the group; VoucherPolicy handles the write
        | abilities per action, because redeem and cancel both depend on the
        | voucher's state as well as on a permission — something route
        | middleware cannot express.
        |
        | Manager holds vouchers.view and vouchers.redeem. That is the café
        | credit rule: somebody at the counter with a coupon needs whoever is on
        | the floor to be able to honour it. Creating and cancelling stay with
        | Admin — both give away or take back the studio's money.
        |
        | 'list' and 'lookup' are registered before {voucher:code} so the
        | literal segments win, as in the reservations and payments groups.
        */
        Route::prefix('vouchers')
            ->name('vouchers.')
            ->middleware('permission:vouchers.view')
            ->group(function () {
                Route::get('/', [VoucherController::class, 'index'])->name('index');
                Route::get('list', [VoucherController::class, 'list'])->name('list');

                // The counter workflow. Read-only by design — it answers
                // "is this good" without spending anything.
                Route::get('lookup', [VoucherController::class, 'lookup'])->name('lookup');

                Route::post('/', [VoucherController::class, 'store'])->name('store');

                Route::get('{voucher:code}', [VoucherController::class, 'show'])->name('show');
                Route::post('{voucher:code}/redeem', [VoucherController::class, 'redeem'])->name('redeem');
                Route::post('{voucher:code}/cancel', [VoucherController::class, 'cancel'])->name('cancel');
            });

        /*
        |----------------------------------------------------------------------
        | PHASE 16 — reports and CSV export
        |----------------------------------------------------------------------
        | Gated on reports.view for the whole group, with reports.export as a
        | second gate on the download alone. Both are held by Admin and Manager,
        | and the split is not decoration: a page of rows on a screen and the
        | client's entire visitor list as a file on somebody's laptop are
        | different acts, and separating them means the client can withdraw one
        | without withdrawing the other.
        |
        | No policy class. Nothing here writes, and every row shown is one the
        | register already shows to the same person — a policy would be ceremony
        | around a read.
        |
        | {report} is a plain string validated against the ReportType enum in
        | the controller rather than a route constraint, so an unknown report
        | 404s in one place and the enum stays the only list of them.
        |
        | No literal-before-placeholder problem here, unlike the reservations
        | and payments groups: 'list' and 'export' sit UNDER the report segment
        | rather than beside it, so the paths differ in length and cannot
        | collide. The bare /reports path falls through to the reservations
        | report via the controller's default argument.
        */
        Route::prefix('reports')
            ->name('reports.')
            ->middleware('permission:reports.view')
            ->group(function () {
                Route::get('/', [ReportController::class, 'index'])->name('index');

                Route::get('{report}', [ReportController::class, 'index'])->name('show');
                Route::get('{report}/list', [ReportController::class, 'list'])->name('list');

                Route::get('{report}/export', [ReportController::class, 'export'])
                    ->middleware('permission:reports.export')
                    ->name('export');

                /*
                 | PHASE 20 — clearing a log.
                 |
                 | Its own permission, Admin-only, and separate from
                 | reports.export on purpose: reading a log and destroying one
                 | are different acts, and a Manager who needs the first should
                 | not get the second.
                 |
                 | POST rather than DELETE because it takes a body (the cutoff)
                 | and because the controller decides what "clear" means per
                 | report — the gateway log never removes a successful
                 | transaction, whatever cutoff is chosen.
                 */
                Route::post('{report}/clear', [ReportController::class, 'clear'])
                    ->middleware('permission:reports.clear')
                    ->name('clear');
            });

        /*
        |----------------------------------------------------------------------
        | PHASE 17 — settings
        |----------------------------------------------------------------------
        | settings.view and settings.update are Admin-only in the seeder, and
        | left that way. A Manager runs the floor; they record payments and
        | redeem vouchers. They do not change the booking fee, the SMTP
        | password, or how far ahead the calendar opens.
        |
        | Five save endpoints rather than one, matching the five tabs. A single
        | Save would mean a validation error on the mail tab blocks saving a
        | phone number, and every save rewriting every row whether it changed or
        | not.
        |
        | There is no endpoint for the SSLCommerz tab. It is read-only by
        | design — credentials live in .env and only in .env, per §11 and the
        | note in config/services.php.
        */
        Route::prefix('settings')
            ->name('settings.')
            ->group(function () {
                Route::get('/', [SettingController::class, 'index'])
                    ->middleware('permission:settings.view')
                    ->name('index');

                Route::middleware('permission:settings.update')->group(function () {
                    Route::post('general', [SettingController::class, 'updateGeneral'])->name('general');
                    Route::post('reservations', [SettingController::class, 'updateReservations'])->name('reservations');
                    Route::post('payments', [SettingController::class, 'updatePayments'])->name('payments');
                    Route::post('mail', [SettingController::class, 'updateMail'])->name('mail');

                    /*
                     | PHASE 19 — the gateway tab is a form now, not a read-only
                     | panel. Credentials moved from .env into the settings
                     | table; both store passwords are encrypted at rest and
                     | never sent back to the browser.
                     */
                    Route::post('gateway', [SettingController::class, 'updateGateway'])->name('gateway');

                    /*
                     | Throttled, because it sends real email on demand. Also
                     | the replacement for GET /send-test-email in web.php,
                     | which is unauthenticated, unthrottled, and has a personal
                     | Gmail address hardcoded in it. DELETE THAT ROUTE.
                     */
                    Route::post('mail/test', [SettingController::class, 'testMail'])
                        ->middleware('throttle:test-email')
                        ->name('mail.test');
                });
            });

        /*
        |----------------------------------------------------------------------
        | PHASE 17 — your own account
        |----------------------------------------------------------------------
        | No permission gate. Every signed-in member of staff may edit
        | themselves, and the controller reads $request->user() rather than an
        | id from the route — there is no id to tamper with, so there is no
        | authorisation decision to get wrong.
        |
        | The password endpoint is throttled on top of that: it accepts the
        | current password, which makes it somewhere an open session could be
        | used to guess at one.
        */
        Route::prefix('profile')
            ->name('profile.')
            ->group(function () {
                Route::get('/', [ProfileController::class, 'index'])->name('index');
                Route::post('/', [ProfileController::class, 'update'])->name('update');

                /*
                 | PHASE 19 — there is no longer an 'activity' endpoint. The
                 | sign-in history is capped at the last 30 entries and rendered
                 | with the page, then paged and searched by DataTables in the
                 | browser. A server round trip to page thirty rows would be a
                 | request for nothing.
                 */

                Route::post('password', [ProfileController::class, 'updatePassword'])
                    ->middleware('throttle:password-change')
                    ->name('password');
            });

        /*
        |----------------------------------------------------------------------
        | PHASE 20 — staff accounts
        |----------------------------------------------------------------------
        | users.* is Admin-only in the seeder, and stays that way. A Manager who
        | could create accounts could create an Admin, which makes the
        | Admin/Manager split decoration.
        |
        | Bound as {user} on the id. Every endpoint re-checks that the target is
        | actually STAFF — this module does not manage visitors, and a visitor id
        | in the URL gets a 404 rather than an edit form.
        |
        | 'list' before '{user}', per the ordering rule the other registers
        | follow: without it, /admin/users/list is looked up as a user id.
        */
        Route::prefix('users')
            ->name('users.')
            ->middleware('permission:users.view')
            ->group(function () {
                Route::get('/', [UserController::class, 'index'])->name('index');
                Route::get('list', [UserController::class, 'list'])->name('list');

                Route::post('/', [UserController::class, 'store'])
                    ->middleware('permission:users.create')
                    ->name('store');

                Route::middleware('permission:users.update')->group(function () {
                    Route::get('{user:id}/edit', [UserController::class, 'edit'])->name('edit');
                    Route::post('{user:id}', [UserController::class, 'update'])->name('update');
                    Route::post('{user:id}/toggle', [UserController::class, 'toggle'])->name('toggle');
                });

                Route::delete('{user:id}', [UserController::class, 'destroy'])
                    ->middleware('permission:users.delete')
                    ->name('destroy');
            });
    });
