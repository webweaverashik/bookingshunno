<?php

namespace App\Services\Reports;

use App\Enums\ReportType;
use Illuminate\Support\Facades\View;
use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * PHASE 20 — one report, three files.
 *
 * All three are built from the SAME rows: ReportService::stream() feeds this
 * class exactly what it feeds the CSV, so a spreadsheet and a PDF of the same
 * window cannot disagree with each other or with the screen they came from.
 *
 * ---------------------------------------------------------------------------
 * WHY THE PDF PATH IS DIFFERENT FROM EVERYTHING ELSE HERE
 * ---------------------------------------------------------------------------
 * Bengali. It is the reason mPDF was chosen over dompdf, and it is not a
 * preference — it is the difference between a usable document and a page of
 * empty boxes.
 *
 * Bengali is a complex script: it reorders glyphs (the vowel ই is typed after
 * its consonant and drawn before it), forms conjuncts from consonant clusters,
 * and positions marks above and below the baseline. Rendering it needs OpenType
 * shaping, which dompdf and TCPDF do not do — they place one glyph per
 * codepoint, left to right, and the result is legible to nobody. mPDF has an
 * Indic shaping engine.
 *
 * Shaping alone is not enough; the FONT has to carry the glyphs. See
 * bengaliFont() below for what has to be installed and what happens when it is
 * not.
 *
 * ---------------------------------------------------------------------------
 * MEMORY
 * ---------------------------------------------------------------------------
 * The CSV streams. These two cannot: a spreadsheet is a zip archive that has to
 * be finished before its first byte is valid, and a PDF needs the whole table
 * to lay out pages. Both therefore hold the report in memory, which is why
 * ReportController caps what may be asked for — see the note there.
 */
class ReportExporter
{
    /** Beyond this, a PDF stops being a document anyone reads. */
    private const PDF_ROW_LIMIT = 5000;

    public function __construct(private readonly ReportService $reports)
    {
    }

    /*
    |--------------------------------------------------------------------------
    | Excel
    |--------------------------------------------------------------------------
    */

    /**
     * A real .xlsx, not a CSV with the extension changed.
     *
     * Worth the library: renaming a CSV makes Excel show a security warning on
     * every open, loses every number's type, and mangles a phone number
     * beginning 0 into an integer. Here the header is styled, the pane is
     * frozen, and columns are sized.
     */
    public function xlsx(ReportType $report, array $filters): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle($report->label());

        $headers = $report->csvHeaders();
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;

        $this->reports->stream($report, $filters, function (array $line) use ($sheet, &$row) {
            $column = 1;

            foreach ($line as $value) {
                /*
                 | setValueExplicit with a string type for anything that only
                 | looks like a number. A reference code, a phone number, a
                 | voucher code — Excel would helpfully turn 01712345678 into
                 | 1712345678 and SHN-0001 into a date. Money is written as a
                 | number so it can be summed.
                 */
                $cell = $sheet->getCell([$column, $row]);

                if (is_numeric($value) && ! $this->looksLikeCode((string) $value)) {
                    $cell->setValue((float) $value);
                } else {
                    $cell->setValueExplicit((string) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }

                $column++;
            }

            $row++;
        });

        $lastColumn = $sheet->getHighestColumn();

        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}1")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F1F1F4');
        $sheet->getStyle("A1:{$lastColumn}1")->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER);

        // So the headers stay put when somebody scrolls a year of reservations.
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$lastColumn}1");

        foreach (range('A', $lastColumn) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Written to a temp file rather than php://output: the writer seeks
        // backwards while assembling the zip, which a stream cannot do.
        $path = tempnam(sys_get_temp_dir(), 'shunno-xlsx-');
        (new Xlsx($spreadsheet))->save($path);

        $spreadsheet->disconnectWorksheets();

        return $path;
    }

    /*
    |--------------------------------------------------------------------------
    | PDF
    |--------------------------------------------------------------------------
    */

    public function pdf(ReportType $report, array $filters, array $summary): string
    {
        $rows  = [];
        $count = 0;

        $this->reports->stream($report, $filters, function (array $line) use (&$rows, &$count) {
            if ($count < self::PDF_ROW_LIMIT) {
                $rows[] = $line;
            }

            $count++;
        });

        $html = View::make('admin.reports.pdf', [
            'report'    => $report,
            'headers'   => $report->csvHeaders(),
            'rows'      => $rows,
            'summary'   => $summary,
            'filters'   => $filters,
            'truncated' => $count > self::PDF_ROW_LIMIT,
            'total'     => $count,
            'font'      => $this->bengaliFont(),
        ])->render();

        $mpdf = new Mpdf($this->mpdfConfig());

        $mpdf->SetTitle($report->label() . ' report — Shunno Art Cafe');
        $mpdf->SetAuthor('Shunno Art Cafe');

        // Written, not printed. Nothing here is confidential beyond what the
        // panel already shows, but a report that arrives locked against copying
        // is a report somebody has to retype.
        $mpdf->WriteHTML($html);

        $path = tempnam(sys_get_temp_dir(), 'shunno-pdf-');
        $mpdf->Output($path, \Mpdf\Output\Destination::FILE);

        return $path;
    }

    /**
     * mPDF's configuration, and the part that makes Bengali work.
     *
     * fontDir APPENDS to mPDF's own directory rather than replacing it, so the
     * bundled fonts stay available as fallbacks. fontdata registers the family
     * by name; without that entry mPDF has no idea the file exists, however
     * correctly it is installed.
     *
     * autoScriptToLang and autoLangToFont are what let a single table hold an
     * English column and a Bengali one — mPDF detects the script per run of
     * text and switches font accordingly, rather than needing every cell tagged.
     */
    private function mpdfConfig(): array
    {
        $defaultConfig     = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();

        return [
            'mode'    => 'utf-8',
            'format'  => 'A4-L',           // Landscape: these tables are wide.
            'margin_top'    => 12,
            'margin_bottom' => 14,
            'margin_left'   => 8,
            'margin_right'  => 8,

            'tempDir' => storage_path('app/mpdf'),

            'fontDir' => array_merge($defaultConfig['fontDir'], [storage_path('fonts')]),

            'fontdata' => $defaultFontConfig['fontdata'] + [
                'notosansbengali' => [
                    'R'          => 'NotoSansBengali-Regular.ttf',
                    'B'          => 'NotoSansBengali-Bold.ttf',
                    'useOTL'     => 0xFF,  // OpenType layout on — this is what shapes conjuncts.
                    'useKashida' => 75,
                ],
            ],

            'default_font' => $this->bengaliFont(),

            'autoScriptToLang' => true,
            'autoLangToFont'   => true,
        ];
    }

    /**
     * Which font to ask for, and the honest fallback.
     *
     * NotoSansBengali has to be installed by hand — the TTFs are not shipped
     * with mPDF and not committed here, because they are ~500KB each and belong
     * with the deployment rather than the repository:
     *
     *     storage/fonts/NotoSansBengali-Regular.ttf
     *     storage/fonts/NotoSansBengali-Bold.ttf
     *
     * When they are missing, this returns freeserif instead. mPDF ships it, it
     * covers Bengali, and its shaping is adequate — noticeably worse than Noto
     * for conjuncts, but readable. The alternative would be naming a font that
     * is not there, which mPDF answers with an exception on every export.
     */
    private function bengaliFont(): string
    {
        return is_file(storage_path('fonts/NotoSansBengali-Regular.ttf'))
            ? 'notosansbengali'
            : 'freeserif';
    }

    /**
     * Does this value only look numeric?
     *
     * A leading zero means it is an identifier that happens to be digits — a
     * phone number, a reference — and must stay a string or Excel eats the
     * zero. Anything long enough to lose precision as a float is treated the
     * same way.
     */
    private function looksLikeCode(string $value): bool
    {
        return str_starts_with($value, '0') || strlen($value) > 15;
    }
}
