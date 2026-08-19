@extends('layouts.app')

@section('title', 'Payments')

@section('header-title')
    <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">
        Payments
        <span class="page-desc text-muted fs-7 fw-semibold pt-1">
            What has been asked for, and what has arrived
        </span>
    </h1>
@endsection

@section('content')
    {{--
        Three figures, not a dashboard. They answer the questions the client
        asked in the proposal — how much is owed, how much is late, how much has
        come in — and they are computed across every payment rather than across
        the current filter, so the number does not change while somebody is
        reading it out.
    --}}
    <div class="row g-5 mb-6">
        <div class="col-sm-4">
            <div class="card card-flush h-100">
                <div class="card-body py-5">
                    <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Outstanding</div>
                    <div class="fs-2 fw-bold text-gray-900">
                        BDT {{ number_format($summary['outstanding']) }}
                    </div>
                    <div class="text-muted fs-8">Across every open request</div>
                </div>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="card card-flush h-100">
                <div class="card-body py-5">
                    <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Overdue</div>
                    <div class="fs-2 fw-bold text-{{ $summary['overdue'] > 0 ? 'danger' : 'gray-900' }}">
                        {{ $summary['overdue'] }}
                    </div>
                    <div class="text-muted fs-8">
                        {{ $summary['overdue'] === 1 ? 'request past its deadline' : 'requests past their deadline' }}
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-4">
            <div class="card card-flush h-100">
                <div class="card-body py-5">
                    <div class="text-muted fs-8 text-uppercase fw-bold mb-1">Collected</div>
                    <div class="fs-2 fw-bold text-success">
                        BDT {{ number_format($summary['collected']) }}
                    </div>
                    <div class="text-muted fs-8">Settled in full</div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header border-0 pt-6">
            <div class="card-title">
                <div class="d-flex align-items-center position-relative my-1">
                    <i class="ki-outline ki-magnifier fs-3 position-absolute ms-4"></i>
                    <input type="text" id="payments-search" class="form-control form-control-solid w-250px ps-13"
                        placeholder="Reference, visitor or transaction ID"
                        value="{{ $filters['q'] }}" autocomplete="off" />
                </div>
            </div>

            <div class="card-toolbar">
                @include('admin.partials.filter-bar', [
                    'id' => 'payments-filter',
                    'fields' => [
                        [
                            'key' => 'status',
                            'label' => 'Status',
                            'default' => 'open',
                            'placeholder' => 'Awaiting payment',
                            'options' => [
                                'open' => 'Awaiting payment',
                                'overdue' => 'Overdue',
                                'paid' => 'Paid',
                                'cancelled' => 'Cancelled',
                                'all' => 'Everything',
                            ],
                            'value' => $filters['status'],
                        ],
                        [
                            'key' => 'from',
                            'label' => 'Requested from',
                            'type' => 'date',
                            'width' => 'col-6',
                            'value' => $filters['from'],
                        ],
                        [
                            'key' => 'to',
                            'label' => 'Requested to',
                            'type' => 'date',
                            'width' => 'col-6',
                            'value' => $filters['to'],
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
            </div>
        </div>

        <div class="card-body pt-0">
            <div id="payments-list">
                @include('admin.payments.partials.list', [
                    'payments' => $payments,
                    'filters' => $filters,
                ])
            </div>
        </div>
    </div>
@endsection

@push('modals')
    {{-- Drawer. Filled from the show endpoint; empty until then. --}}
    <div class="modal fade" id="payment-modal" tabindex="-1" aria-hidden="true">
        {{-- sh-modal-scroll rather than Bootstrap's modal-dialog-scrollable —
             see the note in the reservations register for why the latter is
             unusable inside Metronic's flex layout. --}}
        <div class="modal-dialog modal-dialog-centered mw-700px sh-modal-scroll sh-modal-scroll--nofoot">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title" id="payment-modal-title">Payment</h3>
                    <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal">
                        <i class="ki-outline ki-cross fs-1"></i>
                    </div>
                </div>
                <div class="modal-body" id="payment-modal-body"></div>
            </div>
        </div>
    </div>

    @can('payments.update-status')
        @include('admin.payments.partials.record-modal')
    @endcan
@endpush

@push('page-js')
    <script>
        var PaymentsConfig = {
            listUrl: "{{ route('admin.payments.list') }}",
            currency: "BDT"
        };
    </script>
    <script src="{{ asset('js/admin/shunno.js') }}"></script>
    {{-- The shared filter menu. Must load before any register script that calls
         Shunno.filterBar(). --}}
    <script src="{{ asset('js/admin/filters.js') }}"></script>
    <script src="{{ asset('js/admin/payments.js') }}"></script>
    {{-- PHASE 13B — message history, resend, and copying the payment link.
         Delegated from the document, so it works inside drawers that the other
         scripts replace wholesale. --}}
    <script src="{{ asset('js/admin/communications.js') }}"></script>
@endpush
