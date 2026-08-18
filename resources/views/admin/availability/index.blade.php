@extends('layouts.app')

@section('title', 'Availability')

@section('header-title')
    <div data-kt-swapper="true" data-kt-swapper-mode="{default: 'prepend', lg: 'prepend'}"
        data-kt-swapper-parent="{default: '#kt_app_content_container', lg: '#kt_app_header_wrapper'}"
        class="page-title d-flex align-items-center flex-wrap me-3 mb-5 mb-lg-0">
        <h1 class="page-heading d-flex text-gray-900 fw-bold fs-3 align-items-center my-0">
            Availability
        </h1>
        <span class="h-20px border-gray-300 border-start mx-4"></span>
        <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0">
            <li class="breadcrumb-item text-muted">
                <a href="{{ route('admin.dashboard') }}" class="text-muted text-hover-primary">Shunno Art Cafe</a>
            </li>
            <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
            <li class="breadcrumb-item text-muted">Catalogue</li>
            <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
            <li class="breadcrumb-item text-muted">Availability</li>
        </ul>
    </div>
@endsection

@section('content')
    @php $canManage = auth()->user()->can('availability.update'); @endphp

    <div class="row g-5 g-xl-8">

        {{-- ============================================================ --}}
        {{-- Opening hours                                                --}}
        {{-- ============================================================ --}}
        <div class="col-xl-7">
            <div class="card h-100">
                <div class="card-header border-0 pt-6">
                    <div class="card-title flex-column align-items-start">
                        <h3 class="fw-bold m-0">Opening hours</h3>
                        <span class="text-muted fs-7 mt-1">
                            The studio window that governs bookable time. The cafe may stay open later.
                        </span>
                    </div>
                </div>

                <form id="hours-form" action="{{ route('admin.availability.hours') }}">
                    @csrf
                    <div class="card-body pt-2">
                        <div class="table-responsive">
                            <table class="table table-row-dashed align-middle gs-0 gy-3 mb-0">
                                <thead>
                                    <tr class="fw-bold text-muted text-uppercase fs-8">
                                        <th class="min-w-100px">Day</th>
                                        <th class="min-w-100px">Opens</th>
                                        <th class="min-w-100px">Closes</th>
                                        <th class="min-w-80px text-center">Closed</th>
                                    </tr>
                                </thead>
                                <tbody id="hours-rows">
                                    @include('admin.availability.partials.hours', ['hours' => $hours])
                                </tbody>
                            </table>
                        </div>
                        <div class="invalid-feedback d-block" data-error-for="days"></div>
                    </div>

                    @if ($canManage)
                        <div class="card-footer py-4 text-end">
                            <button type="submit" class="btn btn-primary" id="hours-save">
                                <span class="indicator-label">Save hours</span>
                                <span class="indicator-progress">Saving…
                                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                            </button>
                        </div>
                    @endif
                </form>
            </div>
        </div>

        {{-- ============================================================ --}}
        {{-- Booking rules                                                --}}
        {{-- ============================================================ --}}
        <div class="col-xl-5">
            <div class="card h-100">
                <div class="card-header border-0 pt-6">
                    <div class="card-title flex-column align-items-start">
                        <h3 class="fw-bold m-0">Booking rules</h3>
                        <span class="text-muted fs-7 mt-1">How far ahead and how late visitors may request a visit.</span>
                    </div>
                </div>

                <form id="rules-form" action="{{ route('admin.availability.rules') }}">
                    @csrf
                    <div class="card-body pt-2">

                        <div class="mb-6">
                            <label class="form-label">Minimum notice (hours)</label>
                            <input type="number" name="min_lead_hours" class="form-control form-control-solid"
                                min="0" max="336" value="{{ $rules['min_lead_hours'] }}"
                                {{ $canManage ? '' : 'disabled' }} />
                            <div class="form-text">0 allows same-day requests.</div>
                            <div class="invalid-feedback d-block" data-error-for="min_lead_hours"></div>
                        </div>

                        <div class="mb-6">
                            <label class="form-label">Book up to (days ahead)</label>
                            <input type="number" name="max_advance_days" class="form-control form-control-solid"
                                min="7" max="730" value="{{ $rules['max_advance_days'] }}"
                                {{ $canManage ? '' : 'disabled' }} />
                            <div class="invalid-feedback d-block" data-error-for="max_advance_days"></div>
                        </div>

                        <div class="separator my-5"></div>

                        {{-- The switch the whole capacity pathway hangs off. The
                             warning is not decoration: the seeded capacities are
                             placeholders and turning this on against them would
                             start refusing real bookings. --}}
                        <label class="form-check form-switch form-check-custom form-check-solid mb-3">
                            <input class="form-check-input" type="checkbox" name="enforce_capacity" value="1"
                                @checked($rules['enforce_capacity']) {{ $canManage ? '' : 'disabled' }} />
                            <span class="form-check-label fw-semibold text-gray-800">
                                Enforce per-session capacity
                            </span>
                        </label>

                        <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-4">
                            <i class="ki-outline ki-information fs-2 text-warning me-3"></i>
                            <div class="fs-8 text-gray-700">
                                Only switch this on once every workshop has a real maximum-participants figure.
                                Until then the seeded value is a placeholder and enforcing it will turn away
                                bookings the studio could take.
                            </div>
                        </div>
                    </div>

                    @if ($canManage)
                        <div class="card-footer py-4 text-end">
                            <button type="submit" class="btn btn-primary" id="rules-save">
                                <span class="indicator-label">Save rules</span>
                                <span class="indicator-progress">Saving…
                                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                            </button>
                        </div>
                    @endif
                </form>
            </div>
        </div>

        {{-- ============================================================ --}}
        {{-- Blocked dates                                                --}}
        {{-- ============================================================ --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header border-0 pt-6">
                    <div class="card-title flex-column align-items-start">
                        <h3 class="fw-bold m-0">Closed dates</h3>
                        <span class="text-muted fs-7 mt-1" id="blocks-count">
                            {{ $blocks->count() }} upcoming {{ Str::plural('closure', $blocks->count()) }}
                        </span>
                    </div>

                    @can('create', App\Models\Availability\BlockedDate::class)
                        <div class="card-toolbar">
                            <button type="button" class="btn btn-primary" data-block-create>
                                <i class="ki-outline ki-plus fs-2"></i>Block a date
                            </button>
                        </div>
                    @endcan
                </div>

                <div class="card-body pt-0">
                    <div class="table-responsive">
                        <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
                            <thead>
                                <tr class="fw-bold text-muted text-uppercase fs-7">
                                    <th class="min-w-150px">Date</th>
                                    <th class="min-w-150px">Period</th>
                                    <th class="min-w-250px">Reason</th>
                                    <th class="min-w-120px">Added by</th>
                                    <th class="min-w-100px text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="blocks-rows">
                                @include('admin.availability.partials.blocked-rows', ['blocks' => $blocks])
                            </tbody>
                        </table>
                    </div>
                    <p class="text-muted fs-8 mt-4 mb-0">
                        Past closures are kept in the database but hidden here.
                        Blocking a date never cancels an existing reservation.
                    </p>
                </div>
            </div>
        </div>

    </div>
@endsection

@push('modals')
    @can('create', App\Models\Availability\BlockedDate::class)
        @include('admin.availability.partials.blocked-modal')
    @endcan
@endpush

@push('page-js')
    <script>
        var AvailabilityConfig = {
            blockStoreUrl: "{{ route('admin.availability.blocked.store') }}"
        };
    </script>
    <script src="{{ asset('js/admin/shunno.js') }}"></script>
    <script src="{{ asset('js/admin/availability.js') }}"></script>
@endpush
