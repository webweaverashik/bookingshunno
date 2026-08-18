<?php

/**
 * PHASE 21D — move Controllers and Form Requests into module namespaces, and
 * rewrite the `use` statements in the four route files.
 *
 * RUN FROM THE PROJECT ROOT:
 *
 *     php refactor/phase-21d-move.php --dry
 *     php refactor/phase-21d-move.php
 *     composer dump-autoload -o && php artisan optimize:clear
 *
 * Same machinery as 21B and 21C — word-boundary lookahead so no name is
 * rewritten inside a longer one, and a (?<!namespace ) lookbehind so a repeat
 * run is a no-op. Read the 21B header for why both are load-bearing.
 *
 * ---------------------------------------------------------------------------
 * THE LOWEST-RISK PASS, AND WHY
 * ---------------------------------------------------------------------------
 * Nothing here is resolved by convention. Every controller is named explicitly
 * in a route file and every form request is named explicitly in a controller
 * signature, so a missed import fails at `php artisan route:list` before a
 * single request is served. Compare 21B, where a policy that failed to move in
 * step with its model would have gone undiscovered and failed OPEN.
 *
 * No route NAMES change, so nothing in Blade or JavaScript moves. route()
 * helpers, the `admin.` prefix and every action URL are untouched.
 *
 * ---------------------------------------------------------------------------
 * TWO CLASSES THAT DO NOT MOVE, DELIBERATELY
 * ---------------------------------------------------------------------------
 * App\Http\Controllers\Public\LandingController is the public site itself, not a
 * module of it. Filing it under Public\Landing\ would create a folder to hold
 * one file whose module is "the home page".
 *
 * App\Http\Middleware stays flat. Three files — IsLoggedIn, EnsureVisitor,
 * SecurityHeaders — imported by name in bootstrap/app.php. Folders here would
 * be filing for its own sake.
 *
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| The map
|--------------------------------------------------------------------------
| App\Models\Auth is left exactly as it is. User, LoginOtp and LoginActivity
| were already modular, and User in particular MUST NOT move: Spatie writes its
| fully-qualified name into model_has_roles.model_type, and every existing row
| in that table names it. Moving it would silently unassign every role in the
| database.
 */
$moves = [

    // ---- Admin controllers ------------------------------------------------
    'App\\Http\\Controllers\\Admin\\DashboardController' => 'App\\Http\\Controllers\\Admin\\Dashboard\\DashboardController',
    'App\\Http\\Controllers\\Admin\\ReservationController' => 'App\\Http\\Controllers\\Admin\\Reservation\\ReservationController',
    'App\\Http\\Controllers\\Admin\\ReservationDecisionController' => 'App\\Http\\Controllers\\Admin\\Reservation\\ReservationDecisionController',
    'App\\Http\\Controllers\\Admin\\WorkshopController' => 'App\\Http\\Controllers\\Admin\\Workshop\\WorkshopController',
    'App\\Http\\Controllers\\Admin\\AvailabilityController' => 'App\\Http\\Controllers\\Admin\\Availability\\AvailabilityController',
    'App\\Http\\Controllers\\Admin\\BlockedDateController' => 'App\\Http\\Controllers\\Admin\\Availability\\BlockedDateController',
    'App\\Http\\Controllers\\Admin\\VisitorController' => 'App\\Http\\Controllers\\Admin\\Visitor\\VisitorController',
    'App\\Http\\Controllers\\Admin\\PaymentController' => 'App\\Http\\Controllers\\Admin\\Payment\\PaymentController',
    'App\\Http\\Controllers\\Admin\\VoucherController' => 'App\\Http\\Controllers\\Admin\\Voucher\\VoucherController',
    'App\\Http\\Controllers\\Admin\\CommunicationController' => 'App\\Http\\Controllers\\Admin\\Communication\\CommunicationController',
    'App\\Http\\Controllers\\Admin\\ReportController' => 'App\\Http\\Controllers\\Admin\\Report\\ReportController',
    'App\\Http\\Controllers\\Admin\\SettingController' => 'App\\Http\\Controllers\\Admin\\Setting\\SettingController',
    'App\\Http\\Controllers\\Admin\\UserController' => 'App\\Http\\Controllers\\Admin\\Staff\\UserController',
    'App\\Http\\Controllers\\Admin\\ProfileController' => 'App\\Http\\Controllers\\Admin\\Staff\\ProfileController',

    /*
     | The trait moves INTO Reservation rather than staying in a neutral
     | Admin\Concerns folder, even though PaymentController also uses it.
     | It is reservation knowledge — filter parsing, list rendering, the drawer
     | — and Payment merely borrows it. Making PaymentController import across
     | a module boundary states that coupling out loud, which matters here:
     | the trait declares SORTABLE and PAGE_SIZES as class constants, and
     | PaymentController already has to prefix its own to avoid a fatal
     | composition error. That hazard should be visible at the import.
     */
    'App\\Http\\Controllers\\Admin\\Concerns\\RendersReservations' => 'App\\Http\\Controllers\\Admin\\Reservation\\Concerns\\RendersReservations',

    // ---- Public controllers -----------------------------------------------
    'App\\Http\\Controllers\\Public\\AvailabilityController' => 'App\\Http\\Controllers\\Public\\Availability\\AvailabilityController',
    'App\\Http\\Controllers\\Public\\ReservationRequestController' => 'App\\Http\\Controllers\\Public\\Reservation\\ReservationRequestController',
    'App\\Http\\Controllers\\Public\\PaymentPortalController' => 'App\\Http\\Controllers\\Public\\Payment\\PaymentPortalController',
    'App\\Http\\Controllers\\Public\\PaymentGatewayController' => 'App\\Http\\Controllers\\Public\\Payment\\PaymentGatewayController',
    'App\\Http\\Controllers\\Public\\VoucherRedemptionController' => 'App\\Http\\Controllers\\Public\\Voucher\\VoucherRedemptionController',

    /*
     | ---- Serves both audiences ------------------------------------------
     | PayslipController has a staff() method behind the admin middleware and a
     | visitor() method authorised by the payment's own token. It sits at the
     | root of Controllers today for exactly that reason. Filing it under Admin
     | or Public would misrepresent half of it, so it gets a module folder at
     | the level ABOVE the Admin/Public split — which is what the class is.
     */
    'App\\Http\\Controllers\\PayslipController' => 'App\\Http\\Controllers\\Payment\\PayslipController',

    // ---- Admin form requests ----------------------------------------------
    'App\\Http\\Requests\\Admin\\ReservationEditRequest' => 'App\\Http\\Requests\\Admin\\Reservation\\ReservationEditRequest',
    'App\\Http\\Requests\\Admin\\ReservationDecisionRequest' => 'App\\Http\\Requests\\Admin\\Reservation\\ReservationDecisionRequest',
    'App\\Http\\Requests\\Admin\\WorkshopRequest' => 'App\\Http\\Requests\\Admin\\Workshop\\WorkshopRequest',
    'App\\Http\\Requests\\Admin\\BlockedDateRequest' => 'App\\Http\\Requests\\Admin\\Availability\\BlockedDateRequest',
    'App\\Http\\Requests\\Admin\\OperatingHoursRequest' => 'App\\Http\\Requests\\Admin\\Availability\\OperatingHoursRequest',
    'App\\Http\\Requests\\Admin\\AvailabilityRulesRequest' => 'App\\Http\\Requests\\Admin\\Availability\\AvailabilityRulesRequest',
    'App\\Http\\Requests\\Admin\\VisitorRequest' => 'App\\Http\\Requests\\Admin\\Visitor\\VisitorRequest',
    'App\\Http\\Requests\\Admin\\StorePaymentRequest' => 'App\\Http\\Requests\\Admin\\Payment\\StorePaymentRequest',
    'App\\Http\\Requests\\Admin\\RecordPaymentRequest' => 'App\\Http\\Requests\\Admin\\Payment\\RecordPaymentRequest',
    'App\\Http\\Requests\\Admin\\StoreVoucherRequest' => 'App\\Http\\Requests\\Admin\\Voucher\\StoreVoucherRequest',
    'App\\Http\\Requests\\Admin\\StoreUserRequest' => 'App\\Http\\Requests\\Admin\\Staff\\StoreUserRequest',
    'App\\Http\\Requests\\Admin\\UpdateUserRequest' => 'App\\Http\\Requests\\Admin\\Staff\\UpdateUserRequest',
    'App\\Http\\Requests\\Admin\\UpdateProfileRequest' => 'App\\Http\\Requests\\Admin\\Staff\\UpdateProfileRequest',
    'App\\Http\\Requests\\Admin\\UpdatePasswordRequest' => 'App\\Http\\Requests\\Admin\\Staff\\UpdatePasswordRequest',

    /*
     | Settings -> Setting, matching App\Models\Setting and App\Services\Setting
     | from 21B and 21C. Same reasoning as Reports -> Report: one module, one
     | spelling.
     */
    'App\\Http\\Requests\\Admin\\Settings\\GeneralSettingsRequest' => 'App\\Http\\Requests\\Admin\\Setting\\GeneralSettingsRequest',
    'App\\Http\\Requests\\Admin\\Settings\\MailSettingsRequest' => 'App\\Http\\Requests\\Admin\\Setting\\MailSettingsRequest',
    'App\\Http\\Requests\\Admin\\Settings\\PaymentSettingsRequest' => 'App\\Http\\Requests\\Admin\\Setting\\PaymentSettingsRequest',
    'App\\Http\\Requests\\Admin\\Settings\\ReservationSettingsRequest' => 'App\\Http\\Requests\\Admin\\Setting\\ReservationSettingsRequest',
    'App\\Http\\Requests\\Admin\\Settings\\GatewaySettingsRequest' => 'App\\Http\\Requests\\Admin\\Setting\\GatewaySettingsRequest',

    /*
     | The only form request that was loose at the root of Requests. It backs
     | the public reservation form, so it goes where the public controllers
     | already live. Flat under Public, mirroring the existing Requests\Visitor
     | folder, which also holds one file.
     */
    'App\\Http\\Requests\\StoreReservationRequest' => 'App\\Http\\Requests\\Public\\StoreReservationRequest',

    /*
     | Already modular, left alone:
     |   App\\Http\\Controllers\\Auth\\{AuthController, PasswordController}
     |   App\\Http\\Controllers\\Visitor\\{VisitorAreaController, VisitorAuthController}
     |   App\\Http\\Requests\\Visitor\\UpdateVisitorProfileRequest
     */
];

$scanDirs = ['app', 'bootstrap', 'config', 'database', 'routes', 'resources/views', 'tests'];
$scanExts = ['php'];

/*
|--------------------------------------------------------------------------
| Run
|--------------------------------------------------------------------------
*/

$dry = in_array('--dry', $argv, true);
$root = getcwd();

if (! is_dir($root.'/app') || ! is_file($root.'/artisan')) {
    fwrite(STDERR, "Run this from the Laravel project root.\n");
    exit(1);
}

$git = is_dir($root.'/.git');

echo $dry ? "DRY RUN — nothing will be written.\n\n" : "Applying Phase 21B.\n\n";

// -- 1. Rewrite every reference, while the tree is still flat ----------------

echo "REWRITING REFERENCES\n";

$patterns = [];
$replacements = [];

foreach ($moves as $old => $new) {
    /*
     | Two guards, both required:
     |
     | (?<!namespace )  A file that has already moved declares
     |                  `namespace App\Models\Payment;`, which otherwise matches
     |                  the rule for App\Models\Payment and would be rewritten
     |                  to App\Models\Payment\Payment on a second run. Without
     |                  this, the script is destructive when run twice — and a
     |                  half-finished run is exactly when you want to run it
     |                  again.
     |
     | (?![A-Za-z0-9_\]) The match must end at a name boundary, so
     |                  App\Models\Payment cannot match inside
     |                  App\Models\PaymentTransaction.
     */
    $patterns[] = '/(?<!namespace )'.preg_quote($old, '/').'(?![A-Za-z0-9_\\\\])/';
    $replacements[] = str_replace('\\', '\\\\', $new);   // literal, for preg_replace
}

$touched = 0;
$hits = 0;

foreach (sourceFiles($root, $scanDirs, $scanExts) as $file) {
    $original = file_get_contents($file);
    $updated = preg_replace($patterns, $replacements, $original, -1, $count);

    if ($count === 0) {
        continue;
    }

    printf("  %-58s %d\n", relative($root, $file), $count);

    $touched++;
    $hits += $count;

    if (! $dry) {
        file_put_contents($file, $updated);
    }
}

if ($touched === 0) {
    echo "  nothing to rewrite (already applied?)\n";
}

// -- 2. Move the files, and fix their own namespace declaration --------------

echo "\nMOVING FILES\n";

$moved = 0;

foreach ($moves as $old => $new) {
    $from = $root.'/'.fqcnToPath($old);
    $to = $root.'/'.fqcnToPath($new);

    if (! is_file($from)) {
        echo is_file($to)
            ? sprintf("  skip   %s (already at destination)\n", short($old))
            : sprintf("  MISS   %s (not found at %s)\n", short($old), fqcnToPath($old));

        continue;
    }

    printf("  move   %s\n         -> %s\n", fqcnToPath($old), fqcnToPath($new));

    if ($dry) {
        $moved++;

        continue;
    }

    @mkdir(dirname($to), 0775, true);

    $ok = $git ? (gitMove($from, $to) || rename($from, $to)) : rename($from, $to);

    if (! $ok) {
        fwrite(STDERR, "  FAILED to move {$from}\n");
        exit(1);
    }

    $contents = file_get_contents($to);
    $contents = str_replace(
        'namespace '.namespaceOf($old).';',
        'namespace '.namespaceOf($new).';',
        $contents,
        $count
    );

    if ($count !== 1) {
        fwrite(STDERR, sprintf(
            "  WARNING %s: expected 1 namespace declaration, found %d. Fix by hand.\n",
            fqcnToPath($new),
            $count
        ));
    }

    file_put_contents($to, $contents);
    $moved++;
}

// -- 3. Report anything still pointing at the flat namespaces ---------------

if (! $dry) {
    echo "\nLEFTOVER OLD REFERENCES\n";

    /*
     | Checked against the exact old names rather than a "anything not in a
     | known module" heuristic. The heuristic version flagged every moved file's
     | own `namespace App\Models\Payment;` line as a leftover, which is noise
     | that trains you to ignore the section.
     */
    $left = 0;

    foreach (sourceFiles($root, $scanDirs, $scanExts) as $file) {
        $contents = file_get_contents($file);

        foreach ($patterns as $i => $pattern) {
            if (preg_match($pattern, $contents)) {
                printf("  %-58s %s\n", relative($root, $file), array_keys($moves)[$i]);
                $left++;
            }
        }
    }

    if ($left === 0) {
        echo "  none.\n";
    }

    // The old plural namespace, in case anything referenced the folder rather
    // than a class inside it.
    echo "\nLEFTOVER 'Requests\\Admin\\Settings' REFERENCES\n";

    $plural = 0;

    foreach (sourceFiles($root, $scanDirs, $scanExts) as $file) {
        if (str_contains(file_get_contents($file), 'App\Http\Requests\Admin\Settings')) {
            echo '  '.relative($root, $file)."\n";
            $plural++;
        }
    }

    if ($plural === 0) {
        echo "  none — app/Http/Requests/Admin/Settings can be removed.\n";
    }
}

printf(
    "\n%s %d file(s) moved, %d reference(s) rewritten across %d file(s).\n",
    $dry ? 'WOULD APPLY:' : 'DONE:',
    $moved,
    $hits,
    $touched
);

if (! $dry) {
    echo "\nNow run:\n"
       ."  composer dump-autoload -o\n"
       ."  php artisan optimize:clear\n"
       ."  php artisan route:list > /dev/null && echo 'routes OK'\n";
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function fqcnToPath(string $fqcn): string
{
    return 'app/'.str_replace('\\', '/', substr($fqcn, strlen('App\\'))).'.php';
}

function namespaceOf(string $fqcn): string
{
    return substr($fqcn, 0, strrpos($fqcn, '\\'));
}

function short(string $fqcn): string
{
    return substr($fqcn, strrpos($fqcn, '\\') + 1);
}

function relative(string $root, string $path): string
{
    return str_replace('\\', '/', substr($path, strlen($root) + 1));
}

function gitMove(string $from, string $to): bool
{
    exec(sprintf('git mv %s %s 2>&1', escapeshellarg($from), escapeshellarg($to)), $out, $code);

    return $code === 0;
}

/** @return iterable<string> */
function sourceFiles(string $root, array $dirs, array $exts): iterable
{
    foreach ($dirs as $dir) {
        $path = $root.'/'.$dir;

        if (! is_dir($path)) {
            continue;
        }

        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($it as $file) {
            /** @var SplFileInfo $file */
            if ($file->isFile() && in_array(strtolower($file->getExtension()), $exts, true)) {
                yield $file->getPathname();
            }
        }
    }
}
