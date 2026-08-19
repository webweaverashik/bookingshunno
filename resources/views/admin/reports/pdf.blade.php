{{--
    The PDF body, rendered by mPDF.

    NOT the same markup as the on-screen table, and it should not be. mPDF
    understands a narrow, old subset of CSS: no flexbox, no grid, no CSS
    variables, no Bootstrap. Feeding it the Metronic table would produce a page
    of stacked, unstyled rows. This is written for what mPDF actually supports —
    plain tables, inline-ish styles, fixed widths.

    THE FONT IS SET ON <body> AND INHERITED. $font is 'notosansbengali' when the
    TTFs are installed and 'freeserif' when they are not; see
    ReportExporter::bengaliFont(). Setting it once here means a Bengali visitor
    name inside an otherwise English table renders with the right glyphs without
    every cell being tagged.
--}}
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        /* mPDF reads this at parse time; there is no cascade worth relying on
           beyond simple element and class selectors. */
        body {
            font-family: "{{ $font }}", sans-serif;
            font-size: 8pt;
            color: #1a1a1a;
        }

        .masthead {
            border-bottom: 1.5pt solid #2A4A63;
            padding-bottom: 6pt;
            margin-bottom: 10pt;
        }

        .masthead h1 {
            font-size: 15pt;
            margin: 0 0 2pt 0;
            color: #2A4A63;
        }

        .masthead .meta {
            font-size: 8pt;
            color: #666;
        }

        /* A table rather than divs — mPDF has no flexbox, and this is the only
           reliable way to get four boxes across a page. */
        .summary {
            width: 100%;
            margin-bottom: 10pt;
            border-collapse: collapse;
        }

        .summary td {
            width: 25%;
            border: 0.5pt solid #ddd;
            padding: 5pt 7pt;
        }

        .summary .value {
            font-size: 11pt;
            font-weight: bold;
            color: #2A4A63;
        }

        .summary .label {
            font-size: 7pt;
            color: #666;
        }

        table.rows {
            width: 100%;
            border-collapse: collapse;
        }

        table.rows th {
            background: #F1F1F4;
            border-bottom: 0.8pt solid #999;
            padding: 4pt 5pt;
            text-align: left;
            font-size: 7pt;
            text-transform: uppercase;
        }

        table.rows td {
            border-bottom: 0.3pt solid #e5e5e5;
            padding: 4pt 5pt;
            font-size: 7.5pt;
        }

        .note {
            margin-top: 8pt;
            padding: 5pt 7pt;
            background: #FFF6E5;
            border-left: 2pt solid #B8860B;
            font-size: 7.5pt;
        }
    </style>
</head>

<body>

    {{-- htmlspecialchars is what {{ }} already does. The point of noting it: a
         visitor's special-request note reaching mPDF unescaped would let a
         booking form inject markup into a document the studio prints. --}}
    <div class="masthead">
        <h1>{{ $report->label() }} report</h1>
        <div class="meta">
            Shunno Art Cafe &nbsp;&middot;&nbsp;
            {{ $filters['from']->format('j F Y') }} to {{ $filters['to']->format('j F Y') }}
            &nbsp;&middot;&nbsp; {{ $report->rangeBasis() }}
            &nbsp;&middot;&nbsp; Generated {{ now()->format('j F Y, g:i A') }}
        </div>
    </div>

    @if (!empty($summary))
        <table class="summary">
            <tr>
                @foreach ($summary as $stat)
                    <td>
                        <div class="value">{{ $stat['value'] }}</div>
                        <div class="label">{{ $stat['label'] }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif

    <table class="rows">
        {{-- thead, so mPDF repeats the header on every page. Without it, page
             four of a reservation report is columns of numbers with nothing
             saying what they are. --}}
        <thead>
            <tr>
                @foreach ($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>

        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($headers) }}" style="text-align:center; padding:14pt; color:#888;">
                        Nothing in this window.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if ($truncated)
        {{-- Said on the document itself, not only in the UI. A PDF gets emailed
             onwards, and whoever opens it next has no idea it was cut short
             unless the page says so. --}}
        <div class="note">
            This PDF shows the first {{ number_format(count($rows)) }} of {{ number_format($total) }} rows.
            Export as CSV for the complete data, or narrow the date range.
        </div>
    @endif

</body>

</html>
