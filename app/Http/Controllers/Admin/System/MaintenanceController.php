<?php

namespace App\Http\Controllers\Admin\System;

use App\Enums\System\MaintenanceTask;
use App\Http\Controllers\Controller;
use App\Services\Setting\SettingsRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/**
 * Running artisan from the browser, because the live server has no shell.
 *
 * Webuzo shared hosting gives no SSH, so `php artisan migrate` after a deploy
 * had nowhere to happen. This is the narrowest thing that solves that.
 *
 * FOUR LOCKS, and none of them is sufficient alone:
 *
 *   The route is Admin-only and behind system.maintenance, so a Manager cannot
 *   reach it even by URL.
 *
 *   The task is an ENUM CASE, never a command string. See MaintenanceTask for
 *   why that is the whole security model rather than a detail of it.
 *
 *   The admin re-types their password for anything that writes. A stolen
 *   session is not a migration.
 *
 *   Every run is logged with the user, the task and the outcome, before and
 *   after. If this is ever misused, the log says by whom.
 *
 * Rate limited on top of all four: five runs a minute is generous for a person
 * and slow for anything else.
 */
class MaintenanceController extends Controller
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    /**
     * Whether the console is switched on at all.
     *
     * 404 rather than 403, and the difference is deliberate. A 403 confirms the
     * page exists and is merely shut, which invites somebody to come back with
     * a better session; a 404 says there is nothing here. Since the switch is
     * off by default, that is also the honest answer for most installs.
     *
     * Checked in BOTH actions rather than in middleware, because a settings
     * read is cheap and a route file is one careless edit away from losing a
     * middleware entry.
     */
    private function assertEnabled(Request $request): void
    {
        abort_unless($request->user()?->can('system.maintenance'), 404);
        abort_unless((bool) $this->settings->get('system.maintenance_console', false), 404);
    }

    public function index(Request $request): View
    {
        /*
         | The permission AND the switch. See assertEnabled().
         |
         | The permission check reads system.maintenance, which is the name that
         | exists — an earlier version asked the gate for 'run-maintenance', an
         | ability nothing had ever defined, and denied everyone including Admin.
         |
         | Route middleware enforces the permission too. Kept here as well
         | because a controller that trusts its route file is one copy-pasted
         | route definition away from being open.
         */
        $this->assertEnabled($request);

        return view('admin.system.maintenance', [
            'tasks' => MaintenanceTask::all(),
        ]);
    }

    public function run(Request $request): JsonResponse
    {
        // Re-checked, not assumed from index(). Somebody could have the page
        // open when the switch is turned off, and the buttons would still post.
        $this->assertEnabled($request);

        $validated = $request->validate([
            // Rule::enum is the gate. A payload naming anything not in the enum
            // never reaches Artisan::call().
            'task'     => ['required', Rule::enum(MaintenanceTask::class)],
            'password' => ['nullable', 'string'],
        ]);

        $task = MaintenanceTask::from($validated['task']);
        $user = $request->user();

        /*
         | Per user, not per IP. The studio is behind one connection, so an IP
         | limit would have two admins sharing a budget — and the thing being
         | limited here is a person hammering a button, which is a per-person
         | problem.
         */
        $key = 'maintenance:' . $user->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many commands in a row. Wait a minute and try again.',
            ], 429);
        }

        RateLimiter::hit($key, 60);

        /*
         | The password, for anything that writes.
         |
         | Not theatre: this page can migrate a database, and the realistic way
         | somebody gets here is a laptop left open or a session cookie lifted,
         | neither of which comes with the password. Read-only tasks are exempt
         | because the point of them is that somebody diagnosing a problem
         | presses them freely.
         |
         | hash_equals is not usable here — Hash::check does its own constant
         | time comparison against the bcrypt hash.
         */
        if (! $task->isReadOnly() && ! Hash::check((string) ($validated['password'] ?? ''), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'That password is not right.',
                'errors'  => ['password' => ['Enter your own password to confirm.']],
            ], 422);
        }

        [$command, $arguments] = $task->command();

        Log::warning('Maintenance command started from the admin panel.', [
            'user'    => $user->email,
            'task'    => $task->value,
            'command' => $command,
            'ip'      => $request->ip(),
        ]);

        try {
            /*
             | Artisan::call(), not exec(). Nothing here shells out: PHP's
             | process functions are disabled on most shared hosting anyway, and
             | invoking the command in-process means no shell is involved for
             | anything to be injected into even in principle.
             */
            $status = Artisan::call($command, $arguments);
            $output = trim(Artisan::output());
        } catch (Throwable $e) {
            Log::error('Maintenance command failed.', [
                'user'  => $user->email,
                'task'  => $task->value,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $task->label() . ' failed.',
                'data'    => [
                    /*
                     | The exception message, shown deliberately. §16 says not to
                     | leak debug detail to the browser — and this is the one
                     | screen where the audience IS the person debugging, who is
                     | an authenticated Admin holding a permission granted for
                     | exactly this. No stack trace, though: the message is what
                     | is useful, the trace is what is dangerous.
                     */
                    'output' => $e->getMessage(),
                ],
            ], 500);
        }

        Log::warning('Maintenance command finished.', [
            'user'   => $user->email,
            'task'   => $task->value,
            'status' => $status,
        ]);

        return response()->json([
            'success' => $status === 0,
            'message' => $status === 0
                ? $task->label() . ' finished.'
                : $task->label() . ' exited with status ' . $status . '.',
            'data' => [
                // Artisan writes nothing for several of these when there was
                // nothing to do. Silence reads as a failure, so it is spelled
                // out rather than left blank.
                'output' => $output !== '' ? $output : 'Finished with no output — there was nothing to do.',
            ],
        ]);
    }
}
