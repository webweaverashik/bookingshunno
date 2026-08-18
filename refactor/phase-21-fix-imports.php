<?php

/**
 * PHASE 21 REPAIR — add the `use` statements that same-namespace resolution
 * used to make unnecessary.
 *
 * RUN FROM THE PROJECT ROOT, AFTER 21A–21D:
 *
 *     php refactor/phase-21-fix-imports.php --dry
 *     php refactor/phase-21-fix-imports.php
 *     composer dump-autoload -o && php artisan optimize:clear
 *
 * ---------------------------------------------------------------------------
 * WHAT WENT WRONG
 * ---------------------------------------------------------------------------
 * The four move scripts rewrote every FULLY-QUALIFIED reference correctly, and
 * missed the other half of the problem entirely: a class does not need an
 * import to reach a class in its own namespace.
 *
 *     PayslipController          extends Controller       // App\Http\Controllers
 *     ReservationItem            belongsTo(Workshop::class)  // App\Models
 *     PaymentService::__construct(PricingService $p, ...)    // App\Services
 *
 * None of those had a `use` line, because none needed one. Split the namespace
 * and every one of them starts resolving against its NEW namespace instead —
 * App\Http\Controllers\Payment\Controller, App\Models\Reservation\Workshop,
 * App\Services\Payment\PricingService. Classes that do not exist.
 *
 * The failure mode is what makes this worth a script rather than a checklist:
 * PHP resolves class names at USE time, not at parse time, so most of these
 * files still lint clean and still autoload. `route:list` catches the ones in
 * class headers (extends, implements, trait use) because those resolve when the
 * class is declared. A ::class inside a relation method or a type hint on a
 * constructor argument fails only when that specific line runs — which for a
 * relation might be the one report nobody opened this week.
 *
 * ---------------------------------------------------------------------------
 * HOW IT WORKS
 * ---------------------------------------------------------------------------
 * 1. Index every class, interface, trait and enum declared under app/, short
 *    name to fully-qualified name.
 *
 * 2. Tokenise each file and collect every BARE class reference — PHP 8 emits
 *    qualified names as single T_NAME_QUALIFIED tokens, so anything still
 *    arriving as T_STRING is unqualified by definition. Comments, docblocks
 *    and string literals are separate token types and are never touched.
 *
 * 3. Keep a reference only if it names something in the index, is not declared
 *    in this file, is not already imported, and now lives in a DIFFERENT
 *    namespace from the file referencing it.
 *
 * 4. Insert the import into the existing `use` block.
 *
 * Ambiguous short names are reported and skipped rather than guessed at. There
 * is one in this codebase — AvailabilityController exists under both Admin and
 * Public — and a script that picked one at random would produce a file that
 * looks right and serves the wrong page.
 *
 * Safe to re-run: a file that already has the import is left alone.
 */

declare(strict_types=1);

$dry = in_array('--dry', $argv, true);
$root = getcwd();

if (! is_dir($root.'/app') || ! is_file($root.'/artisan')) {
    fwrite(STDERR, "Run this from the Laravel project root.\n");
    exit(1);
}

echo $dry ? "DRY RUN — nothing will be written.\n\n" : "Repairing imports.\n\n";

/*
|--------------------------------------------------------------------------
| 1. Index everything declared under app/
|--------------------------------------------------------------------------
*/

$index = [];   // short name => [fqcn, ...]
$fileOwner = [];   // path => ['namespace' => ..., 'declares' => [...]]

foreach (phpFiles($root.'/app') as $file) {
    $source = file_get_contents($file);

    if (! preg_match('/^namespace\s+([^;]+);/m', $source, $ns)) {
        continue;
    }

    $namespace = trim($ns[1]);
    $declares = [];

    if (preg_match_all(
        '/^\s*(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|trait|enum)\s+(\w+)/m',
        $source,
        $matches
    )) {
        foreach ($matches[1] as $name) {
            $declares[] = $name;
            $index[$name][] = $namespace.'\\'.$name;
        }
    }

    $fileOwner[$file] = ['namespace' => $namespace, 'declares' => $declares];
}

$ambiguous = array_keys(array_filter($index, fn ($v) => count(array_unique($v)) > 1));

if ($ambiguous) {
    echo "AMBIGUOUS SHORT NAMES — skipped, resolve these by hand if they appear below\n";

    foreach ($ambiguous as $name) {
        echo "  {$name}: ".implode(', ', array_unique($index[$name]))."\n";
    }

    echo "\n";
}

/*
|--------------------------------------------------------------------------
| 2. Repair each file
|--------------------------------------------------------------------------
*/

$touched = 0;
$added = 0;

foreach ($fileOwner as $file => $meta) {
    $source = file_get_contents($file);
    $imports = topLevelImports($source);
    $needed = [];

    foreach (bareClassReferences($source) as $name) {
        if (in_array($name, $meta['declares'], true)) {
            continue;                       // declared right here
        }

        if (isset($imports[$name])) {
            continue;                       // already imported, or aliased
        }

        if (! isset($index[$name]) || in_array($name, $ambiguous, true)) {
            continue;                       // not ours, or too risky to guess
        }

        $fqcn = $index[$name][0];

        if (namespaceOf($fqcn) === $meta['namespace']) {
            continue;                       // still a sibling, still fine
        }

        $needed[$fqcn] = true;
    }

    if (! $needed) {
        continue;
    }

    $list = array_keys($needed);
    sort($list);

    printf("  %s\n", relative($root, $file));

    foreach ($list as $fqcn) {
        printf("      + use %s;\n", $fqcn);
    }

    $touched++;
    $added += count($list);

    if (! $dry) {
        file_put_contents($file, insertImports($source, $list));
    }
}

printf(
    "\n%s %d import(s) added across %d file(s).\n",
    $dry ? 'WOULD ADD:' : 'DONE:',
    $added,
    $touched
);

if ($touched === 0) {
    echo "Nothing to repair.\n";
}

if (! $dry && $touched > 0) {
    echo "\nNow run:\n"
       ."  composer dump-autoload -o\n"
       ."  php artisan optimize:clear\n"
       ."  php artisan route:list\n";
}

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Every bare (unqualified) class-position name in the file.
 *
 * @return list<string>
 */
function bareClassReferences(string $source): array
{
    $tokens = token_get_all($source);
    $found = [];

    $depth = 0;     // brace depth: top-level `use` is an import, deeper is a trait
    $skipToSemi = false; // inside a namespace or import statement

    $significant = [];   // index map of the previous meaningful token

    foreach ($tokens as $i => $token) {
        if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }

        $significant[] = $i;
    }

    foreach ($significant as $pos => $i) {
        $token = $tokens[$i];

        if ($token === '{') {
            $depth++;

            continue;
        }

        if ($token === '}') {
            $depth--;

            continue;
        }

        if ($skipToSemi) {
            if ($token === ';' || $token === '{') {
                $skipToSemi = false;
            }

            continue;
        }

        if (is_array($token)) {
            // `namespace X;` and top-level `use X;` are declarations about
            // names, not uses of them.
            if ($token[0] === T_NAMESPACE || ($token[0] === T_USE && $depth === 0)) {
                $skipToSemi = true;

                continue;
            }

            if ($token[0] !== T_STRING) {
                continue;
            }
        } else {
            continue;
        }

        $prev = $pos > 0 ? $tokens[$significant[$pos - 1]] : null;
        $next = isset($significant[$pos + 1]) ? $tokens[$significant[$pos + 1]] : null;

        // Property, method or constant access — not a class name.
        if (is_array($prev) && in_array($prev[0], [
            T_OBJECT_OPERATOR,
            T_NULLSAFE_OBJECT_OPERATOR,
            T_DOUBLE_COLON,
            T_FUNCTION,
            T_CONST,
        ], true)) {
            continue;
        }

        // A call, unless it is `new Foo(...)`.
        if ($next === '(' && ! (is_array($prev) && $prev[0] === T_NEW)) {
            continue;
        }

        $found[] = $token[1];
    }

    return array_values(array_unique($found));
}

/**
 * Short name (or alias) => fully-qualified name, for top-level imports only.
 *
 * @return array<string,string>
 */
function topLevelImports(string $source): array
{
    $imports = [];

    if (! preg_match_all('/^use\s+(?!function\s|const\s)([^;]+);/m', $source, $matches)) {
        return $imports;
    }

    foreach ($matches[1] as $clause) {
        foreach (explode(',', $clause) as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            if (preg_match('/^(.+?)\s+as\s+(\w+)$/i', $part, $alias)) {
                $imports[$alias[2]] = trim($alias[1]);

                continue;
            }

            $imports[substr($part, (int) strrpos($part, '\\') + 1)] = $part;
        }
    }

    return $imports;
}

/** @param list<string> $fqcns */
function insertImports(string $source, array $fqcns): string
{
    $lines = preg_split('/(\r\n|\n)/', $source, -1, PREG_SPLIT_DELIM_CAPTURE);
    $eol = str_contains($source, "\r\n") ? "\r\n" : "\n";

    // Rebuild as plain lines; the delimiter capture above is only used to
    // detect the line ending, which must be preserved on Windows checkouts.
    $lines = preg_split('/\r\n|\n/', $source);

    $lastUse = null;
    $namespace = null;

    foreach ($lines as $n => $line) {
        if ($namespace === null && preg_match('/^namespace\s+[^;]+;/', $line)) {
            $namespace = $n;
        }

        if (preg_match('/^use\s+[^;]+;/', $line)) {
            $lastUse = $n;
        }

        // Stop at the class header: a `use` after this point is a trait.
        if (preg_match('/^\s*(?:final\s+|abstract\s+|readonly\s+)*(?:class|interface|trait|enum)\s+\w+/', $line)) {
            break;
        }
    }

    $new = array_map(fn (string $fqcn): string => "use {$fqcn};", $fqcns);

    if ($lastUse !== null) {
        array_splice($lines, $lastUse + 1, 0, $new);
    } elseif ($namespace !== null) {
        array_splice($lines, $namespace + 1, 0, array_merge([''], $new));
    } else {
        return implode($eol, $lines);   // no namespace: leave it alone
    }

    return implode($eol, $lines);
}

function namespaceOf(string $fqcn): string
{
    return substr($fqcn, 0, (int) strrpos($fqcn, '\\'));
}

function relative(string $root, string $path): string
{
    return str_replace('\\', '/', substr($path, strlen($root) + 1));
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
