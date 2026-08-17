@extends('layouts.app')

@section('title', 'Dashboard')

@section('header-title')
    <div data-kt-swapper="true" data-kt-swapper-mode="{default: 'prepend', lg: 'prepend'}"
        data-kt-swapper-parent="{default: '#kt_app_content_container', lg: '#kt_app_header_wrapper'}"
        class="page-title d-flex align-items-center flex-wrap me-3 mb-5 mb-lg-0">
        <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 align-items-center my-0">Dashboard</h1>
        <span class="h-20px border-gray-300 border-start mx-4"></span>
        <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0">
            <li class="breadcrumb-item text-muted">Shunno Art Cafe</li>
            <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
            <li class="breadcrumb-item text-muted">Dashboard</li>
        </ul>
    </div>
@endsection

@section('content')

    {{--
        ORDERED BY HOW SOON SOMEBODY ACTS ON IT.

        1. What needs a decision right now
        2. Who is coming today
        3. The week ahead, and whether anything is broken
        4. Trends — interesting, not urgent

        A dashboard that opens with a twelve-week chart is one people stop
        reading, because the thing they came for is below the fold.
    --}}

    @include('admin.dashboard.partials.actions')

    @can('reservations.view')
        @include('admin.dashboard.partials.today')
    @endcan

    <div class="row g-5 mb-5">
        <div class="col-xl-4">
            @can('reservations.view')
                @include('admin.dashboard.partials.week')
            @endcan
        </div>

        <div class="col-xl-4">
            @include('admin.dashboard.partials.balances')
        </div>

        <div class="col-xl-4">
            {{-- Admin only. $health is empty for everybody else, so the column
                 collapses rather than showing a hollow card. --}}
            @if (!empty($health))
                @include('admin.dashboard.partials.health')
            @else
                {{-- A Manager gets the month's figures here instead. Same space,
                     something they can use, no gap in the grid. --}}
                <div class="card h-100">
                    <div class="card-header border-0 pt-6">
                        <div class="card-title flex-column align-items-start">
                            <h3 class="fw-bold m-0">This month</h3>
                            <span class="text-muted fs-7 mt-1">{{ now()->format('F Y') }}</span>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="d-flex align-items-center justify-content-between py-4">
                            <span class="fw-semibold text-gray-800">Visits confirmed</span>
                            <span class="fs-3 fw-bold text-gray-900">{{ number_format($summary['visits_this_month']) }}</span>
                        </div>
                        @can('payments.view')
                            <div class="d-flex align-items-center justify-content-between py-4 border-top">
                                <span class="fw-semibold text-gray-800">Received</span>
                                <span class="fs-3 fw-bold text-success">
                                    {{ number_format($summary['received_this_month']) }}
                                </span>
                            </div>
                        @endcan
                        <div class="d-flex align-items-center justify-content-between py-4 border-top">
                            <span class="fw-semibold text-gray-800">Active experiences</span>
                            <span class="fs-3 fw-bold text-gray-900">{{ $summary['active_workshops'] }}</span>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <div class="row g-5 mb-5">
        <div class="col-xl-7">
            @can('reservations.view')
                @include('admin.dashboard.partials.trend')
            @endcan
        </div>
        <div class="col-xl-5">
            @can('workshops.view')
                @include('admin.dashboard.partials.workshops')
            @endcan
        </div>
    </div>

    @can('payments.view')
        <div class="row g-5">
            <div class="col-12">
                @include('admin.dashboard.partials.revenue')
            </div>
        </div>
    @endcan
@endsection

@push('page-js')
    <script>
        /*
            Every series is built server-side, tooltips included. The chart plots
            numbers and prints strings PHP already formatted — no money is
            formatted in the browser, which is the same rule the rest of the
            panel follows.
        */
        var DashboardCharts = {
            trend: @json($trend),
            revenue: @json($revenue),
            workshops: @json($workshops)
        };
    </script>
    <script src="{{ asset('js/admin/dashboard.js') }}"></script>
@endpush
