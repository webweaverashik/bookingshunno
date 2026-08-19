<?php

namespace App\Support;

use Closure;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Writing a CSV without holding it in memory.
 *
 * Two things this class exists for, and both are the difference between an
 * export that works on the client's hosting and one that does not.
 *
 * ---------------------------------------------------------------------------
 * 1. THE BYTE ORDER MARK
 * ---------------------------------------------------------------------------
 * Three bytes, EF BB BF, written before anything else.
 *
 * Without them, Excel on Windows opens a UTF-8 file as Windows-1252 and every
 * Bengali name in this database becomes mojibake — শুন্য arrives as a row of
 * question marks and boxes. The whole application is utf8mb4 precisely so those
 * names survive, and an export that mangles them at the last step throws that
 * away. LibreOffice and Google Sheets sniff the encoding and do not need this;
 * Excel does not, and Excel is what the client will use.
 *
 * The BOM is invisible to every reader that matters, including PHP's own
 * fgetcsv, so there is no cost to writing it.
 *
 * ---------------------------------------------------------------------------
 * 2. STREAMING, NOT BUILDING
 * ---------------------------------------------------------------------------
 * Rows are written to php://output as they are produced, and the response is a
 * StreamedResponse, so a year of transactions never exists as one string in
 * memory. The application runs on Webuzo shared hosting where memory_limit is
 * somebody else's decision and is usually modest.
 *
 * Callers must therefore feed this with chunk() or lazy() rather than get() —
 * streaming the write while holding every model in a collection would defeat
 * the whole point. See ReportService for how each report does it.
 *
 * ---------------------------------------------------------------------------
 * A NOTE ON SPREADSHEET FORMULA INJECTION
 * ---------------------------------------------------------------------------
 * A cell beginning =, +, - or @ is executed as a formula when opened. Visitor
 * names and special-request notes are free text typed by the public, so they
 * are exactly the vector. Every value goes through guard() below, which
 * prefixes a tab so the cell is read as text. The tab does not display.
 */
class CsvStream
{
    private const BOM = "\xEF\xBB\xBF";

    /**
     * @param  array<int,string>       $headers
     * @param  Closure(Closure):void   $rows   receives a writer; call it once per row
     *                                         with array<int,string|int|float|null>
     */
    public static function download(string $filename, array $headers, Closure $rows): StreamedResponse
    {
        return response()->streamDownload(
            function () use ($headers, $rows) {
                $handle = fopen('php://output', 'wb');

                echo self::BOM;

                fputcsv($handle, $headers);

                $write = function (array $row) use ($handle) {
                    fputcsv($handle, array_map([self::class, 'guard'], $row));
                };

                $rows($write);

                fclose($handle);
            },
            $filename,
            [
                'Content-Type'        => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',

                // Shared hosting sometimes sits behind a gzipping proxy that
                // buffers the whole body before forwarding it, which quietly
                // undoes the streaming. This asks it not to.
                'X-Accel-Buffering'   => 'no',
                'Cache-Control'       => 'no-store, no-cache, must-revalidate',
            ],
        );
    }

    /**
     * Make one cell safe to open.
     *
     * Nulls become empty rather than the string "null"; everything else is
     * cast to string, and anything a spreadsheet would evaluate is prefixed
     * with a tab so it is read as text instead.
     */
    public static function guard(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = (string) $value;

        if ($value !== '' && str_contains("=+-@\t\r", $value[0])) {
            return "\t" . $value;
        }

        return $value;
    }

    /** Money, written the way a spreadsheet can add up: no commas, no currency. */
    public static function money(float|int|string|null $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
