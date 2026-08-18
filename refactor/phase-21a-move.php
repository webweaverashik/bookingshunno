<?php

/**
 * PHASE 21A — move Enums, Support classes and model traits into module
 * namespaces, and rewrite every reference to them.
 *
 * RUN FROM THE PROJECT ROOT:
 *
 *     php refactor/phase-21a-move.php --dry
 *     php refactor/phase-21a-move.php
 *     composer dump-autoload -o && php artisan optimize:clear
 *
 * ---------------------------------------------------------------------------
 * WHY A SCRIPT AND NOT FIFTY HAND-EDITED FILES
 * ---------------------------------------------------------------------------
 * Nothing here changes behaviour. Every one of these files keeps its exact
 * contents apart from one namespace line and a handful of imports. Retyping
 * them by hand introduces the only bug this pass can have — a missed import —
 * in the one place nobody would think to look. A script either rewrites all of
 * them or none of them, and --dry shows you which before it touches anything.
 *
 * ---------------------------------------------------------------------------
 * WHAT IT DOES NOT DO
 * ---------------------------------------------------------------------------
 * It does not touch database rows. Enum CASTS store the backed value
 * ('approved'), never the class name, so no stored data references these
 * namespaces — with one exception the deploy notes cover: a QUEUED job whose
 * payload carries an enum argument serialises the fully-qualified class name.
 * Drain the queue before running this on production.
 */

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| The map
|--------------------------------------------------------------------------
| old FQCN => new FQCN. Order does not matter: no short name here is a prefix
| of another, so the replacements cannot cascade into each other, and running
| the script twice is a no-op rather than a corruption.
*/

$moves = [

    // ---- Reservation ------------------------------------------------------
    'App\Enums\ReservationStatus' => 'App\Enums\Reservation\ReservationStatus',
    'App\Enums\ReservationSource' => 'App\Enums\Reservation\ReservationSource',
    'App\Support\VisitPurposes' => 'App\Support\Reservation\VisitPurposes',

    // ---- Workshop ---------------------------------------------------------
    'App\Enums\WorkshopCategory' => 'App\Enums\Workshop\WorkshopCategory',

    // ---- Availability -----------------------------------------------------
    'App\Support\SessionSlots' => 'App\Support\Availability\SessionSlots',

    // ---- Payment ----------------------------------------------------------
    'App\Enums\PaymentStatus' => 'App\Enums\Payment\PaymentStatus',
    'App\Enums\PaymentType' => 'App\Enums\Payment\PaymentType',
    'App\Enums\PaymentMethod' => 'App\Enums\Payment\PaymentMethod',
    'App\Enums\PaymentChannel' => 'App\Enums\Payment\PaymentChannel',
    'App\Enums\TransactionStatus' => 'App\Enums\Payment\TransactionStatus',

    // ---- Voucher ----------------------------------------------------------
    'App\Enums\VoucherType' => 'App\Enums\Voucher\VoucherType',
    'App\Enums\VoucherStatus' => 'App\Enums\Voucher\VoucherStatus',

    // ---- Communication ----------------------------------------------------
    'App\Enums\CommunicationStatus' => 'App\Enums\Communication\CommunicationStatus',
    'App\Enums\ReservationMailKind' => 'App\Enums\Communication\ReservationMailKind',

    // ---- Report -----------------------------------------------------------
    'App\Enums\ReportType' => 'App\Enums\Report\ReportType',

    // ---- Model concerns ---------------------------------------------------
    // App\Traits disappears entirely. This is a model trait, so it belongs
    // beside the models, which is also where Laravel's own generators put one.
    'App\Traits\HasCreatedBy' => 'App\Models\Concerns\HasCreatedBy',
];

/*
| Deleted rather than moved. LogsModelActivity configures spatie/laravel-
| activitylog, which is not in composer.json, and no model uses the trait. It
| would fatal on the missing LogOptions class the moment anything did.
*/
$deletes = [
    'app/Traits/LogsModelActivity.php',
];

/*
| Left where it is, deliberately. CsvStream writes a byte-order mark and streams
| rows; it knows nothing about reports and would serve a voucher export or a
| visitor export unchanged. App\Support stays for classes like this — genuinely
| shared infrastructure — and gains module subfolders only for classes that
| encode one module's rules.
*/

/*
|--------------------------------------------------------------------------
| Where to rewrite references
|--------------------------------------------------------------------------
*/

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

echo $dry ? "DRY RUN — nothing will be written.\n\n" : "Applying Phase 21A.\n\n";

// -- 1. Move the files -------------------------------------------------------

echo "MOVING FILES\n";

$moved = 0;

foreach ($moves as $old => $new) {
    $from = $root.'/'.fqcnToPath($old);
    $to = $root.'/'.fqcnToPath($new);

    if (! is_file($from)) {
        // Already moved on a previous run, or never existed. Say so and carry on.
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

    $ok = $git
        ? (shellMove($from, $to) || rename($from, $to))
        : rename($from, $to);

    if (! $ok) {
        fwrite(STDERR, "  FAILED to move {$from}\n");
        exit(1);
    }

    // Rewrite the file's own namespace declaration.
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

// -- 2. Delete dead files ----------------------------------------------------

echo "\nDELETING DEAD FILES\n";

foreach ($deletes as $rel) {
    $path = $root.'/'.$rel;

    if (! is_file($path)) {
        echo "  skip   {$rel} (already gone)\n";

        continue;
    }

    echo "  delete {$rel}\n";

    if (! $dry) {
        unlink($path);
    }
}

// -- 3. Rewrite every reference ----------------------------------------------

echo "\nREWRITING REFERENCES\n";

$touched = 0;
$hits = 0;

foreach (sourceFiles($root, $scanDirs, $scanExts) as $file) {
    $original = file_get_contents($file);
    $updated = $original;

    foreach ($moves as $old => $new) {
        // Matches `use App\Enums\Foo;`, `\App\Enums\Foo::bar()`, the string
        // form inside @use('App\Enums\Foo') and class-string config values.
        // Blade escapes nothing here, so one replacement covers all of them.
        $updated = str_replace($old, $new, $updated);
    }

    if ($updated === $original) {
        continue;
    }

    $fileHits = 0;
    foreach ($moves as $old => $new) {
        $fileHits += substr_count($original, $old);
    }

    printf("  %-58s %d\n", relative($root, $file), $fileHits);

    $touched++;
    $hits += $fileHits;

    if (! $dry) {
        file_put_contents($file, $updated);
    }
}

// -- 4. Report anything left behind ------------------------------------------

echo "\nLEFTOVER REFERENCES TO App\\Traits\n";

$leftovers = 0;

// On a dry run this would scan the untouched tree and report every file the
// script is about to fix, which reads as a failure. Skip it.
if ($dry) {
    echo "  (skipped on a dry run)\n";
    goto summary;
}

foreach (sourceFiles($root, $scanDirs, $scanExts) as $file) {
    $contents = file_get_contents($file);

    if (str_contains($contents, 'App\Traits')) {
        echo '  '.relative($root, $file)."\n";
        $leftovers++;
    }
}

if ($leftovers === 0) {
    echo "  none — app/Traits can be removed.\n";
}

// -- Summary -----------------------------------------------------------------

summary:

printf(
    "\n%s %d file(s) moved, %d reference(s) rewritten across %d file(s).\n",
    $dry ? 'WOULD APPLY:' : 'DONE:',
    $moved,
    $hits,
    $touched
);

if (! $dry) {
    echo "\nNow run:\n  composer dump-autoload -o\n  php artisan optimize:clear\n  php artisan route:list > /dev/null && echo 'routes OK'\n";
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function fqcnToPath(string $fqcn): string
{
    // App\Enums\Foo -> app/Enums/Foo.php
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

function shellMove(string $from, string $to): bool
{
    $cmd = sprintf('git mv %s %s 2>&1', escapeshellarg($from), escapeshellarg($to));
    exec($cmd, $out, $code);

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
