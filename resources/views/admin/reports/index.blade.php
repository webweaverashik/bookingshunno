@extends('layouts.app')

@section('title', $report->label() . ' report')

@section('header-title')
    <div data-kt-swapper="true" data-kt-swapper-mode="{default: 'prepend', lg: 'prepend'}"
        data-kt-swapper-parent="{default: '#kt_app_content_container', lg: '#kt_app_header_wrapper'}"
        class="page-title d-flex align-items-center flex-wrap me-3 mb-5 mb-lg-0">
        <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 align-items-center my-0">
            {{ $report->label() }} report
        </h1>
        <span class="h-20px border-gray-300 border-start mx-4"></span>
        <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0">
            <li class="breadcrumb-item text-muted">
                <a href="{{ route('admin.dashboard') }}" class="text-muted text-hover-primary">Shunno Art Cafe</a>
            </li>
            <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
            <li class="breadcrumb-item text-muted">Reports</li>
        </ul>
    </div>
@endsection

@section('content')

    {{-- Which report. Plain links rather than tabs driven by JavaScript: each
         report is a real URL that can be bookmarked, and a member of staff who
         wants "the payments report for last August" should be able to keep it. --}}
    <ul class="nav nav-tabs nav-line-tabs nav-line-tabs-2x border-0 fs-6 fw-semibold mb-6">
        @foreach ($reports as $option)
            <li class="nav-item">
                <a class="nav-link {{ $option === $report ? 'active' : '' }}"
                    href="{{ route('admin.reports.show', array_merge(['report' => $option->value], [
                        'from' => $filters['from']->format('Y-m-d'),
                        'to' => $filters['to']->format('Y-m-d'),
                    ])) }}">
                    <i class="ki-outline ki-{{ $option->icon() }} fs-4 me-2"></i>
                    {{ $option->label() }}
                </a>
            </li>
        @endforeach
    </ul>

    <div id="report-summary">
        @include('admin.reports.partials.summary', ['summary' => $summary])
    </div>

    <div class="card">
        <div class="card-header border-0 pt-6">

            <div class="card-title flex-column align-items-start">
                {{-- The window currently on screen, in words. It moved out of
                     the toolbar and into the filter menu, so this line is now
                     the only thing saying which dates the table covers — and it
                     matters more than it looks: the same rows give four
                     different totals depending on which date column the range
                     runs on, and rangeBasis() is what says which. --}}
                <h3 class="fw-bold m-0" data-report-window>
                    {{ $filters['from']->format('j M Y') }} &ndash; {{ $filters['to']->format('j M Y') }}
                </h3>
                <span class="text-muted fs-8 mt-1">{{ $report->rangeBasis() }}</span>
            </div>

            {{-- One toolbar, everything in it. A card header is a flex row with
                 space-between, so a second .card-toolbar would push these groups
                 to opposite ends and leave Filter marooned in the middle. --}}
            <div class="card-toolbar gap-3">
                @include('admin.partials.filter-bar', [
                    'id' => 'reports-filter',
                    'fields' => array_values(
                        array_filter([
                            [
                                'key' => 'range',
                                'label' => 'Period',
                                'default' => 'custom',
                                'placeholder' => 'Custom range',
                                'options' => [
                                    'custom' => 'Custom range',
                                    'this-month' => 'This month',
                                    'last-month' => 'Last month',
                                    'quarter' => 'Last 90 days',
                                    'year' => 'This year',
                                ],
                                'value' => 'custom',
                            ],
                            [
                                'key' => 'from',
                                'label' => 'From',
                                'type' => 'date',
                                'width' => 'col-6',
                                'when' => 'range:custom',

                                // Default AND value are the window on screen, so the
                                // badge counts a narrowing rather than the range the
                                // report always has. reports.js keeps the default in
                                // step when the server resolves a named period.
                                'default' => $filters['from']->format('Y-m-d'),
                                'value' => $filters['from']->format('Y-m-d'),
                            ],
                            [
                                'key' => 'to',
                                'label' => 'To',
                                'type' => 'date',
                                'width' => 'col-6',
                                'when' => 'range:custom',
                                'default' => $filters['to']->format('Y-m-d'),
                                'value' => $filters['to']->format('Y-m-d'),
                            ],

                            // The reservations report offers fourteen statuses; the
                            // email log offers one, and a dropdown with a single
                            // option is furniture. array_filter drops it.
                            count($statuses) > 1
                                ? [
                                    'key' => 'status',
                                    'label' => 'Filter',
                                    'default' => array_key_first($statuses),
                                    'placeholder' => reset($statuses),
                                    'options' => $statuses,
                                    'value' => $filters['status'],
                                ]
                                : null,
                            [
                                'key' => 'per_page',
                                'label' => 'Rows per page',
                                'default' => 25,
                                'options' => collect($pageSizes)->mapWithKeys(fn($size) => [$size => $size . ' per page'])->all(),
                                'value' => $filters['per_page'],
                            ],
                        ]),
                    ),
                ])

                {{-- Clearing and exporting are in the toolbar but NOT in the
                     filter menu: neither is a filter, and burying a destructive
                     action behind an Apply button would be the wrong shape. --}}
                @if ($report->isClearable())
                    @can('reports.clear')
                        {{-- Only the two logs carry this. The other reports are
                             views over reservations, payments and vouchers — a
                             clear button on those would be a delete button on
                             the studio's own records. --}}
                        <div class="dropdown d-inline-block">
                            <button type="button" class="btn btn-light-danger" data-kt-menu-trigger="click"
                                data-kt-menu-placement="bottom-end">
                                <i class="ki-outline ki-trash fs-2"></i>Clear log
                            </button>
                            <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-250px py-4"
                                data-kt-menu="true">
                                <div class="menu-item px-3">
                                    <a href="#" class="menu-link px-3" data-log-clear="365">Older than a year</a>
                                </div>
                                <div class="menu-item px-3">
                                    <a href="#" class="menu-link px-3" data-log-clear="90">Older than 90 days</a>
                                </div>
                                <div class="menu-item px-3">
                                    <a href="#" class="menu-link px-3" data-log-clear="30">Older than 30 days</a>
                                </div>
                                <div class="separator my-2"></div>
                                <div class="menu-item px-3">
                                    <a href="#" class="menu-link px-3 text-danger" data-log-clear="0">Everything</a>
                                </div>
                            </div>
                        </div>
                    @endcan
                @endif

                @can('reports.export')
                    {{--
                        Three formats, one endpoint, one set of rows.

                        Deliberately NOT the filter menu's own export dropdown.
                        That one sends only the filters that differ from their
                        defaults, which is right for a register — but on this
                        page the dates ARE the report, and an export that
                        silently fell back to the server's default window would
                        be a spreadsheet of the wrong month. reports.js sends the
                        whole set through params().

                        AJAX rather than a plain link: the response comes back as
                        a blob and is saved from memory, which is what lets a
                        refusal — "that is 40,000 rows, too many for PDF" —
                        arrive as a message rather than as a downloaded file full
                        of an error page. See Shunno.download().
                    --}}
                    <div class="dropdown d-inline-block">
                        <button type="button" class="btn btn-light-primary" id="report-export"
                            data-kt-menu-trigger="click" data-kt-menu-placement="bottom-end">
                            <i class="ki-outline ki-exit-up fs-2"></i>Export
                        </button>
                        <div class="menu menu-sub menu-sub-dropdown menu-column menu-rounded menu-gray-600 menu-state-bg-light-primary fw-semibold fs-7 w-200px py-4"
                            data-kt-menu="true">
                            <div class="menu-item px-3">
                                <a href="#" class="menu-link px-3" data-row-export="xlsx">Export as Excel</a>
                            </div>
                            <div class="menu-item px-3">
                                <a href="#" class="menu-link px-3" data-row-export="csv">Export as CSV</a>
                            </div>
                            <div class="menu-item px-3">
                                <a href="#" class="menu-link px-3" data-row-export="pdf">Export as PDF</a>
                            </div>
                        </div>
                    </div>
                @endcan
            </div>

        </div>

        <div class="card-body pt-0">
            <div id="report-list">
                @include('admin.reports.partials.' . $report->value, [
                    'report' => $report,
                    'rows' => $rows,
                    'filters' => $filters,
                ])
            </div>
        </div>
    </div>
@endsection

@push('page-js')
    <script>
        var ReportsConfig = {
            listUrl: "{{ route('admin.reports.list', $report->value) }}",
            exportUrl: "{{ route('admin.reports.export', $report->value) }}",
            clearUrl: "{{ route('admin.reports.clear', $report->value) }}",
            reportLabel: @json($report->label())
        };
    </script>
    <script src="{{ asset('js/admin/shunno.js') }}"></script>
    {{-- The shared filter menu. Must load before reports.js, which calls
         Shunno.filterBar(). --}}
    <script src="{{ asset('js/admin/filters.js') }}"></script>
    <script src="{{ asset('js/admin/reports.js') }}"></script>
@endpush
