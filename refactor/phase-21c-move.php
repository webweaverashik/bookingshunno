<?php

/**
 * PHASE 21C — move Services, Events, Listeners and Mailables into module
 * namespaces.
 *
 * RUN FROM THE PROJECT ROOT:
 *
 *     php refactor/phase-21c-move.php --dry
 *     php refactor/phase-21c-move.php
 *     composer dump-autoload -o && php artisan optimize:clear
 *
 * Same machinery as 21B — word-boundary matching so no name can be rewritten
 * inside a longer one, and a (?<!namespace ) lookbehind so running the script
 * twice is a no-op rather than a corruption. Read the 21B header for why both
 * are load-bearing.
 *
 * ---------------------------------------------------------------------------
 * THIS IS THE DEPLOY THAT NEEDS AN EMPTY QUEUE
 * ---------------------------------------------------------------------------
 * Three of the classes moving here are ShouldQueue mailables, and every queued
 * job payload names its own class in full. A pending
 * ReservationNotificationMail serialised before the deploy cannot be
 * unserialised after it — the visitor simply never receives their approval
 * email, and nothing in the panel says so. Drain the queue first.
 *
 * ---------------------------------------------------------------------------
 * LISTENER DISCOVERY SURVIVES THIS
 * ---------------------------------------------------------------------------
 * Nothing registers these listeners; Laravel discovers them. The default
 * discovery path is app_path('Listeners') and the scan uses Symfony Finder,
 * which recurses — so app/Listeners/Communication/SendReservationNotifications
 * is still found, and still bound by the event type-hinted on each handle
 * method. What DOES go stale is bootstrap/cache/events.php if event caching was
 * ever run, which is why optimize:clear is part of the sequence rather than a
 * suggestion.
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

    // ---- Services ---------------------------------------------------------
    'App\\Services\\WorkshopService' => 'App\\Services\\Workshop\\WorkshopService',

    'App\\Services\\AvailabilityService' => 'App\\Services\\Availability\\AvailabilityService',
    'App\\Services\\AvailabilityAdminService' => 'App\\Services\\Availability\\AvailabilityAdminService',

    'App\\Services\\ReservationService' => 'App\\Services\\Reservation\\ReservationService',
    'App\\Services\\PricingService' => 'App\\Services\\Reservation\\PricingService',

    'App\\Services\\PaymentService' => 'App\\Services\\Payment\\PaymentService',
    'App\\Services\\SslCommerzService' => 'App\\Services\\Payment\\SslCommerzService',

    'App\\Services\\VoucherService' => 'App\\Services\\Voucher\\VoucherService',

    'App\\Services\\CommunicationLogger' => 'App\\Services\\Communication\\CommunicationLogger',

    'App\\Services\\SettingsRepository' => 'App\\Services\\Setting\\SettingsRepository',

    'App\\Services\\DashboardService' => 'App\\Services\\Dashboard\\DashboardService',

    /*
     | Reports -> Report. The only folder in the application that was plural.
     | 21A put the enum at App\Enums\Report\ReportType, and a tree where the
     | module is called Report in one place and Reports in another is a tree
     | you have to guess at every time you reach for it.
     */
    'App\\Services\\Reports\\ReportService' => 'App\\Services\\Report\\ReportService',
    'App\\Services\\Reports\\ReportExporter' => 'App\\Services\\Report\\ReportExporter',

    /*
     | Already modular, left alone:
     |   App\Services\Auth\OtpService
     |   App\Services\Visitor\VisitorPortalService
     */

    // ---- Events -----------------------------------------------------------
    'App\\Events\\ReservationRequested' => 'App\\Events\\Reservation\\ReservationRequested',
    'App\\Events\\ReservationStatusChanged' => 'App\\Events\\Reservation\\ReservationStatusChanged',

    'App\\Events\\PaymentRequested' => 'App\\Events\\Payment\\PaymentRequested',
    'App\\Events\\PaymentReceived' => 'App\\Events\\Payment\\PaymentReceived',

    'App\\Events\\VoucherIssued' => 'App\\Events\\Voucher\\VoucherIssued',

    /*
     | ---- Listeners -------------------------------------------------------
     | BOTH go to Communication, including the one that listens to reservation,
     | payment and voucher events alike. A listener belongs to what it DOES,
     | not to what it hears: SendReservationNotifications turns any domain event
     | into an email plus a communications row, which is the Communication
     | module's whole job. Filing it under Reservation would leave the payment
     | and voucher branches of the same class living in the wrong module.
     */
    'App\\Listeners\\SendReservationNotifications' => 'App\\Listeners\\Communication\\SendReservationNotifications',
    'App\\Listeners\\LogMailDelivery' => 'App\\Listeners\\Communication\\LogMailDelivery',

    /*
     | ---- Mailables -------------------------------------------------------
     | Filed by SUBJECT rather than by medium, unlike the listeners above. A
     | mailable is a template for one specific message about one specific thing,
     | and this mirrors how resources/views/emails is already laid out. The
     | Communication module owns the log and the dispatcher; it does not own
     | every message that has ever been written.
     */
    'App\\Mail\\ReservationNotificationMail' => 'App\\Mail\\Reservation\\ReservationNotificationMail',
    'App\\Mail\\VoucherMail' => 'App\\Mail\\Voucher\\VoucherMail',
    'App\\Mail\\LoginOtpMail' => 'App\\Mail\\Auth\\LoginOtpMail',
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

echo $dry ? "DRY RUN — nothing will be written.\n\n" : "Applying Phase 21C.\n\n";

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
    echo "\nLEFTOVER FLAT REFERENCES\n";

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

    // The plural namespace itself, in case anything referenced the folder
    // rather than a class inside it.
    echo "\nLEFTOVER 'App\\Services\\Reports' REFERENCES\n";

    $plural = 0;

    foreach (sourceFiles($root, $scanDirs, $scanExts) as $file) {
        if (str_contains(file_get_contents($file), 'App\Services\Reports')) {
            echo '  '.relative($root, $file)."\n";
            $plural++;
        }
    }

    if ($plural === 0) {
        echo "  none — app/Services/Reports can be removed.\n";
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
