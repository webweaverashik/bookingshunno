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

            {{-- One toolbar, everything in it. A card header is a flex row with
                 space-between, so a second .card-toolbar would push these two
                 groups to opposite ends and leave Filter marooned in the middle
                 of the table. --}}
            <div class="card-toolbar">
                @include('admin.partials.filter-bar', [
                    'id' => 'vouchers-filter',
                    'fields' => [
                        [
                            'key' => 'type',
                            'label' => 'Kind',
                            'default' => 'all',
                            'placeholder' => 'Both kinds',
                            'options' => [
                                'all' => 'Both kinds',
                                'gift' => 'Gift vouchers',
                                'cafe_credit' => 'Café credit',
                            ],
                            'value' => $filters['type'],
                        ],
                        [
                            'key' => 'status',
                            'label' => 'Status',
                            'default' => 'usable',
                            'placeholder' => 'Usable now',
                            'options' => [
                                'usable' => 'Usable now',
                                'redeemed' => 'Redeemed',
                                'expired' => 'Expired',
                                'cancelled' => 'Cancelled',
                                'all' => 'Everything',
                            ],
                            'value' => $filters['status'],
                        ],
                        [
                            'key' => 'issued_from',
                            'label' => 'Issued from',
                            'type' => 'date',
                            'width' => 'col-6',
                            'value' => $filters['issued_from'],
                        ],
                        [
                            'key' => 'issued_to',
                            'label' => 'Issued to',
                            'type' => 'date',
                            'width' => 'col-6',
                            'value' => $filters['issued_to'],
                        ],
                        [
                            'key' => 'per_page',
                            'label' => 'Rows per page',
                            'default' => 25,
                            'options' => collect($pageSizes)->mapWithKeys(fn($size) => [$size => $size . ' per page'])->all(),
                            'value' => $filters['per_page'],
                        ],
                    ],
                ])

                @can('vouchers.create')
                    <button type="button" class="btn btn-primary" id="voucher-create">
                        <i class="ki-outline ki-plus fs-4"></i>
                        Gift voucher
                    </button>
                @endcan
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
            checkCodeUrl: "{{ route('admin.vouchers.check-code') }}"

            // `defaults` used to live here so the reset button and the badge
            // knew where to go back to. Each field now carries its own default
            // in data-filter-default, set by the filter-bar partial from the
            // same array the controller validates against — one list instead of
            // three that could disagree.
        };
    </script>
    <script src="{{ asset('js/admin/shunno.js') }}"></script>
    {{-- The shared filter menu. Must load before any register script that calls
         Shunno.filterBar(). --}}
    <script src="{{ asset('js/admin/filters.js') }}"></script>
    <script src="{{ asset('js/admin/vouchers.js') }}"></script>
@endpush
