<?php

/**
 * PHASE 21E — the verification sweep.
 *
 * RUN FROM THE PROJECT ROOT, AFTER 21A–21D AND THE IMPORT REPAIR:
 *
 *     php refactor/phase-21e-verify.php
 *     php refactor/phase-21e-verify.php --clean     # also removes empty dirs
 *
 * Read-only unless --clean is passed. Exits non-zero if anything fails, so it
 * can sit in a deploy script.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS IS NOT JUST "GREP FOR THE OLD NAMES"
 * ---------------------------------------------------------------------------
 * A list of the old namespaces would only find the mistakes I already knew to
 * look for — and the import bug in 21D proved that is the wrong bet. So check 3
 * works the other way round: it indexes every class that ACTUALLY EXISTS on
 * disk, then flags every App\ reference in the codebase that does not resolve
 * to one of them. That catches a namespace I mistyped, a class I forgot to
 * move, and a reference in a form I never anticipated, without my having to
 * predict any of them.
 *
 * Check 1 is the other half of the same idea. PSR-4 requires a file's namespace
 * to match its directory; if a move script renamed a file but botched its
 * namespace declaration, Composer's optimised autoloader would still find it
 * from the classmap and nothing would break locally — until a deploy that runs
 * dump-autoload differently. Comparing every declaration against its own path
 * catches that class of drift outright.
 */

declare(strict_types=1);

$root = getcwd();
$clean = in_array('--clean', $argv, true);

if (! is_dir($root.'/app') || ! is_file($root.'/artisan')) {
    fwrite(STDERR, "Run this from the Laravel project root.\n");
    exit(1);
}

$scanDirs = ['app', 'bootstrap', 'config', 'database', 'routes', 'resources/views', 'tests'];

$failures = 0;

/*
|--------------------------------------------------------------------------
| Index what exists
|--------------------------------------------------------------------------
*/

$classes = [];   // fqcn => path
$namespaces = [];   // every namespace prefix that exists

foreach (phpFiles($root.'/app') as $file) {
    $source = file_get_contents($file);

    if (! preg_match('/^namespace\s+([^;]+);/m', $source, $ns)) {
        continue;
    }

    $namespace = trim($ns[1]);

    for ($p = $namespace; str_contains($p, '\\'); $p = substr($p, 0, (int) strrpos($p, '\\'))) {
        $namespaces[$p] = true;
    }

    $namespaces[$namespace] = true;

    if (preg_match_all(
        '/^\s*(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|trait|enum)\s+(\w+)/m',
        $source,
        $matches
    )) {
        foreach ($matches[1] as $name) {
            $classes[$namespace.'\\'.$name] = $file;
        }
    }
}

printf("Indexed %d class(es) under app/.\n\n", count($classes));

/*
|--------------------------------------------------------------------------
| 1. PSR-4: does every namespace match its own directory?
|--------------------------------------------------------------------------
*/

section('1. PSR-4 namespace / path agreement');

$bad = 0;

foreach (phpFiles($root.'/app') as $file) {
    $source = file_get_contents($file);

    if (! preg_match('/^namespace\s+([^;]+);/m', $source, $ns)) {
        continue;
    }

    $declared = trim($ns[1]);

    /*
     | app/Models/Reservation/Reservation.php
     |   -> dirname            app/Models/Reservation
     |   -> strip the leading  'app'
     |   -> prefix             App\Models\Reservation
     |
     | Done by offset rather than str_replace so a folder that happens to be
     | called 'app' further down the tree cannot be eaten.
     */
    $expected = 'App'.str_replace(
        '/',
        '\\',
        substr(trim(dirname(relative($root, $file)), '/'), strlen('app'))
    );

    if ($declared !== $expected) {
        printf("  MISMATCH %s\n           declares %s\n           expects  %s\n", relative($root, $file), $declared, $expected);
        $bad++;
    }
}

report($bad, 'file(s) whose namespace does not match its path', $failures);

/*
|--------------------------------------------------------------------------
| 2. Class name matches file name
|--------------------------------------------------------------------------
*/

section('2. Class name / file name agreement');

$bad = 0;

foreach ($classes as $fqcn => $file) {
    $short = substr($fqcn, (int) strrpos($fqcn, '\\') + 1);

    if ($short !== basename($file, '.php')) {
        // Only a problem when the file declares nothing matching its own name.
        $siblings = array_filter(
            $classes,
            fn ($p, $f) => $p === $file && substr($f, (int) strrpos($f, '\\') + 1) === basename($file, '.php'),
            ARRAY_FILTER_USE_BOTH
        );

        if (! $siblings) {
            printf("  %s declares %s\n", relative($root, $file), $short);
            $bad++;
        }
    }
}

report($bad, 'file(s) not autoloadable by their own name', $failures);

/*
|--------------------------------------------------------------------------
| 3. Dangling App\ references
|--------------------------------------------------------------------------
*/

section('3. References to App\\ classes that do not exist');

$bad = 0;
$notes = 0;

foreach (sourceFiles($root, $scanDirs) as $file) {
    $source = file_get_contents($file);

    /*
     | Comments are stripped before matching, and this is the whole point of
     | the check rather than a nicety.
     |
     | The first version regexed the raw file and flagged
     | App\Support\ExperienceCatalogue in WorkshopSeeder — a docblock sentence
     | recording that the class was DELETED in Phase 6. The comment is correct
     | and should stay. A check that fails on accurate documentation is a check
     | people learn to ignore, which is worse than not having it.
     |
     | String literals are deliberately kept: 'App\Models\Foo' in a config
     | value or a $model property is a real reference that would really break.
     */
    $code = stripComments($file, $source);

    if (! preg_match_all('/App(?:\\\\{1,2}[A-Za-z_][A-Za-z0-9_]*)+/', $code, $matches)) {
        continue;
    }

    foreach (array_unique($matches[0]) as $reference) {
        $normalised = str_replace('\\\\', '\\', $reference);

        if (isset($classes[$normalised]) || isset($namespaces[$normalised])) {
            continue;
        }

        if (! str_contains($normalised, '\\')) {
            continue;
        }

        printf("  %-52s %s\n", relative($root, $file), $normalised);
        $bad++;
    }
}

report($bad, 'dangling reference(s)', $failures);

/*
 | Comments are reported separately and never fail the run. A stale class name
 | in a docblock is worth knowing about and is not a reason to block a deploy.
 */
foreach (sourceFiles($root, $scanDirs) as $file) {
    $source = file_get_contents($file);

    if (str_ends_with($file, '.blade.php')) {
        continue;   // no reliable comment boundaries; handled above as code
    }

    $comments = commentsOnly($source);

    if ($comments === '' || ! preg_match_all('/App(?:\\\\{1,2}[A-Za-z_][A-Za-z0-9_]*)+/', $comments, $matches)) {
        continue;
    }

    foreach (array_unique($matches[0]) as $reference) {
        $normalised = str_replace('\\\\', '\\', $reference);

        if (isset($classes[$normalised]) || isset($namespaces[$normalised]) || ! str_contains($normalised, '\\')) {
            continue;
        }

        if ($notes === 0) {
            echo "  Mentioned in comments only — informational, not a failure:\n";
        }

        printf("    %-50s %s\n", relative($root, $file), $normalised);
        $notes++;
    }
}

if ($notes > 0) {
    echo "\n";
}

/*
|--------------------------------------------------------------------------
| 4. Syntax
|--------------------------------------------------------------------------
*/

section('4. Syntax check');

$bad = 0;

foreach (phpFiles($root.'/app') as $file) {
    exec(sprintf('%s -l %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($file)), $out, $code);

    if ($code !== 0) {
        printf("  %s\n    %s\n", relative($root, $file), implode(' ', $out));
        $bad++;
    }

    $out = [];
}

report($bad, 'file(s) with syntax errors', $failures);

/*
|--------------------------------------------------------------------------
| 5. Empty directories left behind by the moves
|--------------------------------------------------------------------------
*/

section('5. Empty directories under app/');

$empty = emptyDirs($root.'/app');

foreach ($empty as $dir) {
    printf("  %s%s\n", relative($root, $dir), $clean ? '  (removed)' : '');

    if ($clean) {
        @rmdir($dir);
    }
}

if (! $empty) {
    echo "  none.\n";
} elseif (! $clean) {
    echo "  Re-run with --clean to remove these. Git ignores empty directories,\n"
       ."  so they will disappear on a fresh clone either way.\n";
}

/*
|--------------------------------------------------------------------------
| 6. Things a script cannot check
|--------------------------------------------------------------------------
*/

section('6. Check these by hand before deploying');

echo <<<'NOTES'
  Run against the LIVE database — all three should return 0:

    SELECT COUNT(*) FROM jobs;
    SELECT COUNT(*) FROM failed_jobs;
    SELECT COUNT(*) FROM model_has_roles WHERE model_type <> 'App\\Models\\Auth\\User';

  The first two hold serialised class names from before the refactor and can no
  longer be unserialised. The third confirms nothing moved User out from under
  Spatie.

  Then, because route:list does NOT resolve lazy references, touch each one:

    php artisan tinker
    > App\Models\Reservation\ReservationItem::with('workshop','reservation')->first();
    > App\Models\Voucher\Voucher::with('reservation','user')->first();
    > App\Models\Payment\Payment::with('reservation','transactions')->first();
    > app(App\Services\Payment\PaymentService::class);
    > app(App\Services\Report\ReportService::class);

NOTES;

/*
|--------------------------------------------------------------------------
| Result
|--------------------------------------------------------------------------
*/

echo "\n".str_repeat('=', 72)."\n";

if ($failures === 0) {
    echo "PASS — nothing structural left to fix.\n";
    exit(0);
}

printf("FAIL — %d check(s) reported problems.\n", $failures);
exit(1);

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * The file with every comment blanked out, so a class name discussed in prose
 * is not mistaken for one that is used.
 *
 * Blade templates are returned untouched: token_get_all() treats everything
 * outside <?php as inline HTML, so @php blocks would survive as text anyway and
 * a partial strip would be worse than none.
 */
function stripComments(string $file, string $source): string
{
    if (str_ends_with($file, '.blade.php')) {
        return $source;
    }

    $out = '';

    foreach (token_get_all($source) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        $out .= is_array($token) ? $token[1] : $token;
    }

    return $out;
}

/** Only the comments, for the informational pass. */
function commentsOnly(string $source): string
{
    $out = '';

    foreach (token_get_all($source) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            $out .= $token[1]."\n";
        }
    }

    return $out;
}

function section(string $title): void
{
    echo str_repeat('-', 72)."\n{$title}\n".str_repeat('-', 72)."\n";
}

function report(int $bad, string $noun, int &$failures): void
{
    if ($bad === 0) {
        echo "  OK.\n\n";

        return;
    }

    printf("  %d %s.\n\n", $bad, $noun);
    $failures++;
}

function relative(string $root, string $path): string
{
    return str_replace('\\', '/', substr($path, strlen($root) + 1));
}

/** @return list<string> */
function emptyDirs(string $path): array
{
    $found = [];

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($it as $entry) {
        /** @var SplFileInfo $entry */
        if ($entry->isDir() && ! (new FilesystemIterator($entry->getPathname()))->valid()) {
            $found[] = $entry->getPathname();
        }
    }

    return $found;
}

/** @return iterable<string> */
function phpFiles(string $path): iterable
{
    if (! is_dir($path)) {
        return;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($it as $file) {
        /** @var SplFileInfo $file */
        if ($file->isFile() && strtolower($file->getExtension()) === 'php') {
            yield $file->getPathname();
        }
    }
}

/** @return iterable<string> */
function sourceFiles(string $root, array $dirs): iterable
{
    foreach ($dirs as $dir) {
        yield from phpFiles($root.'/'.$dir);
    }
}
