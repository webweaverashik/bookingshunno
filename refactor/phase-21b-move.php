<?php

/**
 * PHASE 21B — move Models and Policies into module namespaces.
 *
 * RUN FROM THE PROJECT ROOT:
 *
 *     php refactor/phase-21b-move.php --dry
 *     php refactor/phase-21b-move.php
 *     composer dump-autoload -o && php artisan optimize:clear
 *
 * ---------------------------------------------------------------------------
 * TWO DIFFERENCES FROM THE 21A SCRIPT — BOTH LOAD-BEARING
 * ---------------------------------------------------------------------------
 *
 * 1. WORD-BOUNDARY MATCHING, NOT str_replace.
 *
 *    In 21A no class name was a prefix of another, so a plain string replace
 *    was safe. Here four of them are:
 *
 *        App\Models\Payment      is a prefix of  App\Models\PaymentTransaction
 *        App\Models\Reservation  is a prefix of  App\Models\ReservationItem
 *                                        and of  App\Models\ReservationStatusHistory
 *        App\Models\Setting      is a prefix of  App\Models\SettingChange
 *
 *    A str_replace would rewrite PaymentTransaction into
 *    App\Models\Payment\PaymentTransaction — a class that does not exist, in a
 *    file that still parses, failing only at runtime on the one code path that
 *    touches it. Every replacement below is therefore anchored with a negative
 *    lookahead: the match must not be followed by another name character or a
 *    further backslash.
 *
 * 2. NAMESPACE DECLARATIONS ARE EXCLUDED FROM THE REWRITE.
 *
 *    Several models move into a folder named after themselves —
 *    App\Models\Workshop becomes App\Models\Workshop\Workshop. Once moved, that
 *    file declares `namespace App\Models\Workshop;`, which matches its own
 *    rewrite rule. A second run would turn it into
 *    `namespace App\Models\Workshop\Workshop;` and take the application down.
 *
 *    Every pattern therefore carries a `(?<!namespace )` lookbehind. Running
 *    this script twice is a no-op, which matters because the run you most want
 *    to repeat is the one that stopped halfway.
 *
 * ---------------------------------------------------------------------------
 * WHAT MAKES THIS PASS SAFE TO DEPLOY
 * ---------------------------------------------------------------------------
 * Policies are NOT registered anywhere — there is no AuthServiceProvider map,
 * discovery does all of it. Laravel's guesser (Gate::guessPolicyName) contains
 * an explicit branch for class names containing \Models\: it swaps that segment
 * for \Policies\ and keeps everything after it. So App\Models\Reservation\
 * Reservation resolves to App\Policies\Reservation\ReservationPolicy without a
 * line of configuration — PROVIDED the two trees mirror each other exactly.
 * That is why models and policies move in the same pass rather than separately.
 *
 * Factories resolve the same way (Database\Factories\<path-after-Models>), so
 * any factory added in Phase 18 goes in database/factories/<Module>/. There are
 * none today.
 */

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

    // ---- Models -----------------------------------------------------------
    'App\Models\Workshop' => 'App\Models\Workshop\Workshop',

    'App\Models\BlockedDate' => 'App\Models\Availability\BlockedDate',
    'App\Models\OperatingHour' => 'App\Models\Availability\OperatingHour',

    'App\Models\Reservation' => 'App\Models\Reservation\Reservation',
    'App\Models\ReservationItem' => 'App\Models\Reservation\ReservationItem',
    'App\Models\ReservationStatusHistory' => 'App\Models\Reservation\ReservationStatusHistory',
    'App\Models\VisitPurpose' => 'App\Models\Reservation\VisitPurpose',

    'App\Models\Payment' => 'App\Models\Payment\Payment',
    'App\Models\PaymentTransaction' => 'App\Models\Payment\PaymentTransaction',

    'App\Models\Voucher' => 'App\Models\Voucher\Voucher',

    'App\Models\Communication' => 'App\Models\Communication\Communication',

    'App\Models\Setting' => 'App\Models\Setting\Setting',
    'App\Models\SettingChange' => 'App\Models\Setting\SettingChange',

    // ---- Policies (must mirror the models above, exactly) -----------------
    'App\Policies\WorkshopPolicy' => 'App\Policies\Workshop\WorkshopPolicy',
    'App\Policies\BlockedDatePolicy' => 'App\Policies\Availability\BlockedDatePolicy',
    'App\Policies\ReservationPolicy' => 'App\Policies\Reservation\ReservationPolicy',
    'App\Policies\PaymentPolicy' => 'App\Policies\Payment\PaymentPolicy',
    'App\Policies\VoucherPolicy' => 'App\Policies\Voucher\VoucherPolicy',
    'App\Policies\CommunicationPolicy' => 'App\Policies\Communication\CommunicationPolicy',
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
