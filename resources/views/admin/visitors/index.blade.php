@extends('layouts.app')

@section('title', 'Visitors')

@section('header-title')
    <div data-kt-swapper="true" data-kt-swapper-mode="{default: 'prepend', lg: 'prepend'}"
        data-kt-swapper-parent="{default: '#kt_app_content_container', lg: '#kt_app_header_wrapper'}"
        class="page-title d-flex align-items-center flex-wrap me-3 mb-5 mb-lg-0">
        <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 align-items-center my-0">Visitors</h1>
        <span class="h-20px border-gray-300 border-start mx-4"></span>
        <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0">
            <li class="breadcrumb-item text-muted">
                <a href="{{ route('admin.dashboard') }}" class="text-muted text-hover-primary">Shunno Art Cafe</a>
            </li>
            <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
            <li class="breadcrumb-item text-muted">Operations</li>
            <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
            <li class="breadcrumb-item text-muted">Visitors</li>
        </ul>
    </div>
@endsection

@section('content')

    {{-- Three figures worth glancing at, not a dashboard. Anything more belongs
         in Phase 16's reports. --}}
    <div class="row g-5 mb-5">
        @foreach ([
            ['label' => 'Visitors on record', 'value' => $stats['total'], 'icon' => 'profile-user', 'tone' => 'primary'],
            ['label' => 'Returning', 'value' => $stats['returning'], 'icon' => 'heart', 'tone' => 'success'],
            ['label' => 'New this month', 'value' => $stats['thisMonth'], 'icon' => 'calendar-add', 'tone' => 'info'],
        ] as $stat)
            <div class="col-md-4">
                <div class="card">
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
                    <input type="text" id="visitors-search" class="form-control form-control-solid w-250px ps-13"
                        placeholder="Name, email or phone" value="{{ $filters['q'] }}" autocomplete="off" />
                </div>
            </div>

            <div class="card-toolbar">
                <select id="visitors-status" class="form-select form-select-solid w-200px">
                    <option value="all" @selected($filters['status'] === 'all')>All visitors</option>
                    <option value="returning" @selected($filters['status'] === 'returning')>Returning</option>
                    <option value="never" @selected($filters['status'] === 'never')>No reservations yet</option>
                    <option value="active" @selected($filters['status'] === 'active')>Active accounts</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>Deactivated</option>
                </select>
            </div>
        </div>

        <div class="card-body pt-0">
            <div id="visitors-list">
                @include('admin.visitors.partials.list', ['visitors' => $visitors, 'filters' => $filters])
            </div>
        </div>
    </div>
@endsection

@push('modals')
    {{-- Detail drawer. Filled from the show endpoint; empty until then. --}}
    <div class="modal fade" id="visitor-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered mw-750px sh-modal-scroll">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title" id="visitor-modal-title">Visitor</h3>
                    <button type="button" class="btn btn-icon btn-sm btn-active-light-primary ms-2"
                        data-bs-dismiss="modal" aria-label="Close">
                        <i class="ki-outline ki-cross fs-1"></i>
                    </button>
                </div>
                <div class="modal-body py-8 px-9" id="visitor-modal-body">
                    <div class="text-center text-muted py-10">Loading…</div>
                </div>
            </div>
        </div>
    </div>

    @can('visitors.update')
        @include('admin.visitors.partials.edit-modal')
    @endcan
@endpush

@push('page-js')
    <script>
        var VisitorsConfig = {
            listUrl: "{{ route('admin.visitors.list') }}"
        };
    </script>
    <script src="{{ asset('js/admin/shunno.js') }}"></script>
    <script src="{{ asset('js/admin/visitors.js') }}"></script>
@endpush
