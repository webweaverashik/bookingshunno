@extends('layouts.app')

@section('title', 'Vouchers')

@section('header-title')
    <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">
        Vouchers
        <span class="page-desc text-muted fs-7 fw-semibold pt-1">
            Gift vouchers and café credit
        </span>
    </h1>
@endsection

@section('content')
    {{--
        THE COUNTER BOX, and it is first on the page for a reason. The common
        use of this screen is not browsing — it is somebody standing at the till
        holding a coupon while a visitor waits. Type the code, read the answer,
        mark it used. Everything below is for the rarer case of looking
        something up afterwards.
    --}}
    @can('vouchers.redeem')
        <div class="card mb-6">
            <div class="card-body py-5">
                <div class="d-flex align-items-end flex-wrap gap-3">
                    <div class="flex-grow-1" style="min-width: 220px;">
                        <label class="form-label fw-bold text-gray-800">Check a voucher</label>
                        <input type="text" id="voucher-lookup" class="form-control form-control-solid text-uppercase"
                            placeholder="CAFE-2608-K4RT" autocomplete="off" spellcheck="false" />
                    </div>
                    <button type="button" class="btn btn-primary" id="voucher-lookup-go">
                        <i class="ki-outline ki-magnifier fs-4"></i>
                        Check
                    </button>
                </div>

                {{-- Filled by vouchers.js from the lookup response. Empty until
                     somebody asks, so the page does not open with a blank
                     result panel implying a failed search. --}}
                <div id="voucher-lookup-result" class="mt-4" hidden></div>
            </div>
        </div>
    @endcan

    <div class="row g-5 mb-6">
        <div class="col-sm-4">
            <div class="card card-flush h-100">
                <div class="card-body py-5">
                    <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Outstanding</div>
                    <div class="fs-2 fw-bold text-gray-900">BDT {{ number_format($summary['outstanding']) }}</div>
                    <div class="text-muted fs-8">Live vouchers the studio still owes</div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card card-flush h-100">
                <div class="card-body py-5">
                    <div class="text-muted fs-8 text-uppercase fw-bold mb-1">In circulation</div>
                    <div class="fs-2 fw-bold text-gray-900">{{ $summary['live'] }}</div>
                    <div class="text-muted fs-8">Usable right now</div>
                </div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="card card-flush h-100">
                <div class="card-body py-5">
                    <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Redeemed</div>
                    <div class="fs-2 fw-bold text-success">BDT {{ number_format($summary['redeemed']) }}</div>
                    <div class="text-muted fs-8">Spent to date</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header border-0 pt-6">
            <div class="card-title">
                <div class="d-flex align-items-center position-relative my-1">
                    <i class="ki-outline ki-magnifier fs-3 position-absolute ms-4"></i>
                    <input type="text" id="vouchers-search" class="form-control form-control-solid w-250px ps-13"
                        placeholder="Code, name or reservation" value="{{ $filters['q'] }}" autocomplete="off" />
                </div>
            </div>

            {{--
                THE FILTER MENU.

                Three dropdowns used to sit loose in this toolbar and fire on
                change. Behind a menu with an Apply button they stop being three
                separate page loads: somebody narrowing to "café credit,
                expired, 100 per page" now asks the server once instead of three
                times, and sees one settled table instead of two intermediate
                ones.

                It also settles the Select2 argument. The Phase 6 rule keeps
                short filter dropdowns as plain selects because Select2
                announces itself through jQuery's .trigger('change'), which
                never reaches addEventListener. Nothing here listens for change
                at all — Apply reads .value off each select when it is pressed —
                so the gap simply does not arise and these can be Select2 like
                every other dropdown in the panel.

                data-dropdown-parent is NOT optional and is the one line that
                will break this if it is removed. Select2 appends its dropdown
                to <body> by default; KTMenu closes on any click outside its own
                DOM; so choosing an option would shut the menu underneath the
                person choosing. Pointing it at the menu keeps the dropdown
                inside and the menu open.
            --}}
            <div class="card-toolbar">
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <button type="button" class="btn btn-light-primary" data-kt-menu-trigger="click"
                        data-kt-menu-placement="bottom-end" id="vouchers-filter-toggle">
                        <i class="ki-duotone ki-filter fs-2">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        Filter
                        {{-- How many filters are on. Without it, a menu that is
                             closed looks identical whether it is filtering
                             everything out or nothing at all. --}}
                        <span class="badge badge-circle badge-primary ms-2 d-none" id="vouchers-filter-count">0</span>
                    </button>

                    <div class="menu menu-sub menu-sub-dropdown w-300px w-md-350px" data-kt-menu="true"
                        id="vouchers-filter-menu">
                        <div class="px-7 py-5">
                            <div class="fs-5 text-gray-900 fw-bold">Filter options</div>
                        </div>

                        <div class="separator border-gray-200"></div>

                        <div class="px-7 py-5">
                            <div class="mb-5">
                                <label class="form-label fs-6 fw-semibold">Kind:</label>
                                <select id="vouchers-type" class="form-select form-select-solid fw-bold"
                                    data-control="select2" data-hide-search="true"
                                    data-dropdown-parent="#vouchers-filter-menu">
                                    <option value="all" @selected($filters['type'] === 'all')>Both kinds</option>
                                    <option value="gift" @selected($filters['type'] === 'gift')>Gift vouchers</option>
                                    <option value="cafe_credit" @selected($filters['type'] === 'cafe_credit')>Café credit</option>
                                </select>
                            </div>

                            <div class="mb-5">
                                <label class="form-label fs-6 fw-semibold">Status:</label>
                                <select id="vouchers-status" class="form-select form-select-solid fw-bold"
                                    data-control="select2" data-hide-search="true"
                                    data-dropdown-parent="#vouchers-filter-menu">
                                    <option value="usable" @selected($filters['status'] === 'usable')>Usable now</option>
                                    <option value="redeemed" @selected($filters['status'] === 'redeemed')>Redeemed</option>
                                    <option value="expired" @selected($filters['status'] === 'expired')>Expired</option>
                                    <option value="cancelled" @selected($filters['status'] === 'cancelled')>Cancelled</option>
                                    <option value="all" @selected($filters['status'] === 'all')>Everything</option>
                                </select>
                            </div>

                            <div class="mb-5">
                                <label class="form-label fs-6 fw-semibold">Rows per page:</label>
                                <select id="vouchers-per-page" class="form-select form-select-solid fw-bold"
                                    data-control="select2" data-hide-search="true"
                                    data-dropdown-parent="#vouchers-filter-menu">
                                    @foreach ($pageSizes as $size)
                                        <option value="{{ $size }}" @selected($filters['per_page'] === $size)>{{ $size }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="d-flex justify-content-end">
                                {{-- type="button", not type="reset": there is no form
                                     here, and a reset would not reach Select2 anyway. --}}
                                <button type="button" class="btn btn-light btn-active-light-primary fw-semibold me-2 px-6"
                                    data-kt-menu-dismiss="true" id="vouchers-filter-reset">Reset</button>
                                <button type="button" class="btn btn-primary fw-semibold px-6"
                                    data-kt-menu-dismiss="true" id="vouchers-filter-apply">Apply</button>
                            </div>
                        </div>
                    </div>

                    @can('vouchers.create')
                        <button type="button" class="btn btn-primary" id="voucher-create">
                            <i class="ki-outline ki-plus fs-4"></i>
                            Gift voucher
                        </button>
                    @endcan
                </div>
            </div>
        </div>

        <div class="card-body pt-0">
            <div id="vouchers-list">
                @include('admin.vouchers.partials.list', ['vouchers' => $vouchers, 'filters' => $filters])
            </div>
        </div>
    </div>
@endsection

@push('modals')
    <div class="modal fade" id="voucher-modal" tabindex="-1" aria-hidden="true">
        {{-- sh-modal-scroll rather than Bootstrap's modal-dialog-scrollable — see
             the reservations register for why the latter breaks in Metronic. --}}
        <div class="modal-dialog modal-dialog-centered mw-650px sh-modal-scroll sh-modal-scroll--nofoot">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title" id="voucher-modal-title">Voucher</h3>
                    <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal">
                        <i class="ki-outline ki-cross fs-1"></i>
                    </div>
                </div>
                <div class="modal-body" id="voucher-modal-body"></div>
            </div>
        </div>
    </div>

    {{-- canany, not can('vouchers.create'). This one modal serves both jobs, so
         gating it on creating alone would give somebody who may edit vouchers
         but not create them an Edit button that opens nothing. --}}
    @canany(['vouchers.create', 'vouchers.update'])
        @include('admin.vouchers.partials.form-modal', [
            'workshops' => $workshops,
            'suggestedCode' => $suggestedCode,
        ])
    @endcanany

    @canany(['vouchers.redeem', 'vouchers.cancel'])
        @include('admin.vouchers.partials.action-modals')
    @endcanany
@endpush

@push('page-js')
    <script>
        var VouchersConfig = {
            listUrl: "{{ route('admin.vouchers.list') }}",
            lookupUrl: "{{ route('admin.vouchers.lookup') }}",
            storeUrl: "{{ route('admin.vouchers.store') }}",
            checkCodeUrl: "{{ route('admin.vouchers.check-code') }}",

            // Defaults, so the browser can tell a filter that is set from one
            // that merely exists — and so the reset button has somewhere to go
            // back to without repeating the controller's list of defaults.
            defaults: { type: 'all', status: 'usable', per_page: '25' }
        };
    </script>
    <script src="{{ asset('js/admin/shunno.js') }}"></script>
    <script src="{{ asset('js/admin/vouchers.js') }}"></script>
@endpush
