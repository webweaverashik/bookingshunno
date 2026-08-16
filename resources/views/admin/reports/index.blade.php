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
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    {{-- Flatpickr, per the project convention for every date
                         input. Type is text, not date: a native picker would
                         render its own control on top of Flatpickr's and the two
                         would fight over the same field.

                         Shunno.datepickers() attaches them. The field submits
                         Y-m-d and displays "14 Aug 2026" through altInput, so
                         ReportController::filters() still parses an exact mask
                         and nothing downstream has to read a formatted date. --}}
                    <input type="text" id="report-from" name="from"
                        class="form-control form-control-solid w-150px shunno-datepicker"
                        value="{{ $filters['from']->format('Y-m-d') }}" aria-label="From" autocomplete="off">
                    <span class="text-muted fs-7">to</span>
                    <input type="text" id="report-to" name="to"
                        class="form-control form-control-solid w-150px shunno-datepicker"
                        value="{{ $filters['to']->format('Y-m-d') }}" aria-label="To" autocomplete="off">

                    {{-- The four windows anyone actually asks for. --}}
                    <div class="btn-group btn-group-sm" role="group" aria-label="Quick ranges">
                        <button type="button" class="btn btn-light" data-report-range="this-month">This
                            month</button>
                        <button type="button" class="btn btn-light" data-report-range="last-month">Last month</button>
                        <button type="button" class="btn btn-light" data-report-range="quarter">Last 90
                            days</button>
                        <button type="button" class="btn btn-light" data-report-range="year">This year</button>
                    </div>
                </div>

                <span class="text-muted fs-8 mt-2">{{ $report->rangeBasis() }}</span>
            </div>

            <div class="card-toolbar gap-3">
                @if (count($statuses) > 1)
                    {{-- Select2. This is the long searchable list the Phase 6
                         rule reserves it for: the reservations report offers
                         fourteen options, and scrolling a native dropdown for
                         "Payment requested" is worse than typing three letters.

                         It works as a filter because Shunno.onChange() binds
                         through jQuery when it sees data-control="select2" —
                         Select2 announces a selection with jQuery's .trigger(),
                         which never reaches a native addEventListener. That is
                         the reason every other filter in this panel is a plain
                         form-select, and it is now handled rather than avoided. --}}
                    <select id="report-status" class="form-select form-select-solid w-225px"
                        data-control="select2" data-placeholder="Filter">
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                @endif

                {{-- Plain form-select, deliberately. Three numeric options with
                     nothing to search: Select2 here would add a search box over
                     "25, 50, 100" and a dropdown that takes two frames to open. --}}
                <select id="report-per-page" class="form-select form-select-solid w-auto">
                    @foreach ($pageSizes as $size)
                        <option value="{{ $size }}" @selected($filters['per_page'] === $size)>{{ $size }} per page
                        </option>
                    @endforeach
                </select>

                @can('reports.export')
                    {{-- A real link, not a fetch(). A browser cannot save a
                         streamed file it received over XHR without staging the
                         whole thing in memory first, which is what the streaming
                         exists to avoid. reports.js keeps the href in step with
                         the filters. --}}
                    <a class="btn btn-primary" id="report-export"
                        href="{{ route('admin.reports.export', array_merge(['report' => $report->value], [
                            'from' => $filters['from']->format('Y-m-d'),
                            'to' => $filters['to']->format('Y-m-d'),
                            'status' => $filters['status'],
                        ])) }}">
                        <i class="ki-outline ki-exit-down fs-3"></i>
                        Download CSV
                    </a>
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
            exportUrl: "{{ route('admin.reports.export', $report->value) }}"
        };
    </script>
    <script src="{{ asset('js/admin/shunno.js') }}"></script>
    <script src="{{ asset('js/admin/reports.js') }}"></script>
@endpush
