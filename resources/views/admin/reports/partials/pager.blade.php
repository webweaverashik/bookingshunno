{{--
    The pager, shared by three of the four report tables.

    The visitors report does not include it: that report is an aggregate over
    the whole range rather than a page of rows, so there is nothing to page
    through. See ReportService::visitors() for why.

    Guarded on the paginator interface rather than on the report name, so a
    future report that returns a plain Collection needs no change here.
--}}

@if ($rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $rows->hasPages())
    <div class="d-flex flex-stack flex-wrap gap-3 pt-4">
        <span class="text-muted fs-7">
            Showing {{ $rows->firstItem() }}–{{ $rows->lastItem() }} of {{ number_format($rows->total()) }}
        </span>

        {{-- data-report-page marks these for reports.js, which intercepts them
             and swaps the table rather than reloading the page. Real hrefs, so
             they still work if the script has not loaded. --}}
        <div data-report-pager>
            {{ $rows->onEachSide(1)->links() }}
        </div>
    </div>
@elseif ($rows instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $rows->total() > 0)
    <div class="pt-4">
        <span class="text-muted fs-7">{{ number_format($rows->total()) }}
            {{ \Illuminate\Support\Str::plural('row', $rows->total()) }}</span>
    </div>
@endif
