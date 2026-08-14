@extends('layouts.app')

@section('title', 'Reservations')

@section('header-title')
    <div data-kt-swapper="true" data-kt-swapper-mode="{default: 'prepend', lg: 'prepend'}"
        data-kt-swapper-parent="{default: '#kt_app_content_container', lg: '#kt_app_header_wrapper'}"
        class="page-title d-flex align-items-center flex-wrap me-3 mb-5 mb-lg-0">
        <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 align-items-center my-0">
            Reservations
        </h1>
        <span class="h-20px border-gray-300 border-start mx-4"></span>
        <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0">
            <li class="breadcrumb-item text-muted">
                <a href="{{ route('admin.dashboard') }}" class="text-muted text-hover-primary">Shunno Art Cafe</a>
            </li>
            <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
            <li class="breadcrumb-item text-muted">Reservations</li>
        </ul>
    </div>
@endsection

@section('content')

    {{-- Four numbers, no more. Anything richer is Phase 16's reports.
         "Escalated" earns a slot of its own from Phase 10A: it is the queue an
         Admin is personally responsible for, and it must not hide inside a
         general pending count. --}}
    <div class="row g-5 mb-5">
        @foreach ([['label' => 'Needing a decision', 'value' => $stats['pending'], 'icon' => 'time', 'tone' => 'warning'], ['label' => 'Escalated to Admin', 'value' => $stats['escalated'], 'icon' => 'arrow-up-right', 'tone' => 'primary'], ['label' => 'Awaiting payment', 'value' => $stats['awaitingPayment'], 'icon' => 'wallet', 'tone' => 'info'], ['label' => 'Confirmed ahead', 'value' => $stats['upcoming'], 'icon' => 'calendar-tick', 'tone' => 'success']] as $stat)
            <div class="col-6 col-xl-3">
                <div class="card h-100">
                    <div class="card-body d-flex align-items-center py-5">
                        <span class="symbol symbol-45px me-4">
                            <span class="symbol-label bg-light-{{ $stat['tone'] }}">
                                <i class="ki-outline ki-{{ $stat['icon'] }} fs-2 text-{{ $stat['tone'] }}"></i>
                            </span>
                        </span>
                        <div>
                            <div class="fs-2 fw-bold text-gray-900 lh-1">{{ number_format($stat['value']) }}</div>
                            <div class="fs-7 text-muted">{{ $stat['label'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header border-0 pt-6">
            <div class="card-title">
                <div class="d-flex align-items-center position-relative my-1 me-3">
                    <i class="ki-outline ki-magnifier fs-3 position-absolute ms-4"></i>
                    <input type="text" id="reservations-search" class="form-control form-control-solid w-250px ps-13"
                        placeholder="Reference, name, email or phone" value="{{ $filters['q'] }}" autocomplete="off" />
                </div>
            </div>

            <div class="card-toolbar flex-row-fluid justify-content-end gap-3">
                <select id="reservations-range" class="form-select form-select-solid w-auto">
                    <option value="upcoming" @selected($filters['range'] === 'upcoming')>Today and ahead</option>
                    <option value="today" @selected($filters['range'] === 'today')>Today only</option>
                    <option value="past" @selected($filters['range'] === 'past')>Past visits</option>
                    <option value="all" @selected($filters['range'] === 'all')>Any date</option>
                </select>

                <select id="reservations-status" class="form-select form-select-solid w-auto">
                    <option value="open" @selected($filters['status'] === 'open')>Still open</option>
                    <option value="needs_decision" @selected($filters['status'] === 'needs_decision')>Needing a decision</option>
                    <option value="all" @selected($filters['status'] === 'all')>Every status</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>

                <select id="reservations-workshop" class="form-select form-select-solid w-auto">
                    <option value="all" @selected($filters['workshop'] === 'all')>All sessions</option>
                    @foreach ($workshops as $workshop)
                        <option value="{{ $workshop->id }}" @selected($filters['workshop'] === (string) $workshop->id)>
                            {{ $workshop->title }}
                        </option>
                    @endforeach
                </select>

                {{-- PHASE 10A. Page size, server-side like everything else here.
                     The table is expected to grow; the browser never holds more
                     than one page of it. --}}
                <select id="reservations-per-page" class="form-select form-select-solid w-auto" aria-label="Rows per page">
                    @foreach ($pageSizes as $size)
                        <option value="{{ $size }}" @selected($filters['per_page'] === $size)>{{ $size }} per page
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="card-body pt-0">
            <div id="reservations-list">
                @include('admin.reservations.partials.list', [
                    'reservations' => $reservations,
                    'filters' => $filters,
                ])
            </div>
        </div>
    </div>
@endsection

@push('modals')
    {{-- Filled from their endpoints; empty until then. --}}
    <div class="modal fade" id="reservation-modal" tabindex="-1" aria-hidden="true">
        {{-- sh-modal-scroll, NOT Bootstrap's modal-dialog-scrollable. Phase 6
             established that the latter does not work inside Metronic's layout:
             it derives the body height from the .modal element through a flex
             chain, and the wrapping <form> plus Metronic's own rules break that
             chain, so a long body is clipped instead of scrolled. The helper in
             admin.css uses vh units, which have no such dependency. --}}
        <div class="modal-dialog modal-dialog-centered mw-750px sh-modal-scroll sh-modal-scroll--nofoot">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title" id="reservation-modal-title">Reservation</h3>
                    <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal">
                        <i class="ki-outline ki-cross fs-1"></i>
                    </div>
                </div>
                <div class="modal-body" id="reservation-modal-body"></div>
            </div>
        </div>
    </div>

    {{-- Rendered for anyone who can reach the page: which buttons appear inside
         the drawer is decided per reservation by the policy, so a Manager simply
         never gets a trigger for an action they cannot take. --}}
    @include('admin.reservations.partials.decision-modal')

    @can('reservations.update')
        <div class="modal fade" id="reservation-edit-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered mw-650px sh-modal-scroll">
                <div class="modal-content">
                    <form id="reservation-form" action="">
                        @csrf
                        <div class="modal-header">
                            <h3 class="modal-title" id="reservation-edit-title">Edit reservation</h3>
                            <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal">
                                <i class="ki-outline ki-cross fs-1"></i>
                            </div>
                        </div>

                        {{-- Server-rendered whole: the time options depend on the date,
                             the session length and the availability rules, and none of
                             that logic belongs in the browser. --}}
                        <div class="modal-body" id="reservation-form-body"></div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="reservation-save">
                                <span class="indicator-label">Save changes</span>
                                <span class="indicator-progress">
                                    Saving… <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan
@endpush

@push('page-js')
    <script>
        var ReservationsConfig = {
            listUrl: "{{ route('admin.reservations.list') }}"
        };
    </script>
    <script src="{{ asset('js/admin/shunno.js') }}"></script>
    <script src="{{ asset('js/admin/reservations.js') }}"></script>
@endpush
