{{--
    One payment request, in full.

    The layout follows the brief's payment summary exactly — reservation total,
    payment type, payment required, amount paid, remaining — because those five
    lines are what the client asked to be able to read, and re-ordering them
    into something prettier would make the screen and the specification disagree.
--}}

@php
    use App\Enums\PaymentStatus;

    $reservation = $payment->reservation;
    $overdue = $payment->isOverdue();
@endphp

<div class="d-flex align-items-start flex-wrap gap-3 mb-6">
    <div>
        <div class="fs-3 fw-bold text-gray-900">{{ $payment->reference }}</div>
        <div class="text-muted fs-7">
            Requested {{ $payment->created_at->format('j M Y, g:i A') }}
            @if ($payment->requestedBy)
                by {{ $payment->requestedBy->name }}
            @endif
        </div>
    </div>
    <div class="ms-auto text-end">
        <span class="badge badge-light-{{ $payment->status->colour() }} fs-7">
            {{ $payment->status->label() }}
        </span>
    </div>
</div>

@if ($overdue)
    <div class="notice d-flex bg-light-danger rounded border-danger border border-dashed p-4 mb-5">
        <i class="ki-outline ki-information-5 fs-2 text-danger me-3"></i>
        <div class="fs-7 text-gray-700">
            <strong>Past the deadline.</strong>
            This was due {{ $payment->due_at->diffForHumans() }}.
            <div class="text-muted fs-8 mt-1">
                Nothing expires automatically — the slot is still held. Chase the visitor, or withdraw
                the request to put the reservation back to Approved.
            </div>
        </div>
    </div>
@endif

{{--
    PHASE 12A. An Admin who agrees a new price after the link went out creates
    exactly this, and it is not an error — it is something a person has to
    decide about. Nothing auto-corrects: rewriting a figure the visitor has
    already been asked for is how a studio takes the wrong money.
--}}
@if ($payment->isOpen() && $payment->divergesFromReservation())
    <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-4 mb-5">
        <i class="ki-outline ki-information-5 fs-2 text-warning me-3"></i>
        <div class="fs-7 text-gray-700">
            <strong>The reservation total has changed since this was sent.</strong>
            It was BDT {{ number_format((float) $payment->reservation_total) }} when the request went out
            and is BDT {{ number_format($reservation->payableTotal()) }} now.
            <div class="text-muted fs-8 mt-1">
                This request still asks for the original figure. Withdraw it and send a new one if the
                visitor should be paying the new amount.
            </div>
        </div>
    </div>
@endif

{{-- ================= The summary ================= --}}
<div class="border border-gray-300 border-dashed rounded p-4 mb-5">
    <div class="fw-bold text-gray-800 mb-3">Payment summary</div>

    <div class="d-flex justify-content-between fs-7 mb-2">
        <span class="text-muted">Reservation total</span>
        <span class="text-gray-800">BDT {{ number_format((float) $payment->reservation_total) }}</span>
    </div>

    <div class="d-flex justify-content-between fs-7 mb-2">
        <span class="text-muted">Payment type</span>
        <span class="text-gray-800">{{ $payment->type->describe($payment->percentage) }}</span>
    </div>

    <div class="separator separator-dashed my-3"></div>

    <div class="d-flex justify-content-between mb-2">
        <span class="fw-bold text-gray-800">Payment required</span>
        <span class="fw-bold text-gray-900 fs-5">BDT {{ number_format((float) $payment->amount_due) }}</span>
    </div>

    <div class="d-flex justify-content-between fs-7 mb-2">
        <span class="text-muted">Amount paid</span>
        <span class="{{ (float) $payment->amount_paid > 0 ? 'text-success fw-semibold' : 'text-muted' }}">
            BDT {{ number_format((float) $payment->amount_paid) }}
        </span>
    </div>

    @if ($payment->isPartiallyPaid())
        <div class="d-flex justify-content-between fs-7">
            <span class="text-warning fw-semibold">Still outstanding on this request</span>
            <span class="text-warning fw-semibold">BDT {{ number_format($payment->outstanding()) }}</span>
        </div>
    @endif

    {{-- Only for a booking fee. A full payment leaves 0.00, and a row reading
         "Remaining: BDT 0" states a debt of nothing rather than nothing owed. --}}
    @if ($payment->type->leavesBalance())
        <div class="separator separator-dashed my-3"></div>
        <div class="d-flex justify-content-between fs-7">
            <span class="text-muted">Remaining on the visit</span>
            <span class="text-gray-800">BDT {{ number_format($payment->remainingOnReservation()) }}</span>
        </div>
        <div class="text-muted fs-8 mt-1">
            Payable at the studio. The system tracks this balance but does not chase it.
        </div>
    @endif
</div>

{{-- ================= Timing ================= --}}
<div class="row g-3 fs-7 mb-5">
    <div class="col-sm-6">
        <span class="text-muted d-block fs-8">Deadline</span>
        <span class="fw-semibold {{ $overdue ? 'text-danger' : 'text-gray-900' }}">
            {{ $payment->due_at->format('l, j F Y, g:i A') }}
        </span>
    </div>

    <div class="col-sm-6">
        <span class="text-muted d-block fs-8">Paid</span>
        <span class="fw-semibold text-gray-900">
            {{ $payment->paid_at?->format('l, j F Y, g:i A') ?? '—' }}
        </span>
    </div>

    @if ($payment->method)
        <div class="col-sm-6">
            <span class="text-muted d-block fs-8">Method</span>
            <span class="fw-semibold text-gray-900">{{ $payment->method->label() }}</span>
            @if ($payment->method->isManual() && $payment->recordedBy)
                {{-- Worth stating plainly. "Marked paid by Rifat" and "settled
                     by the gateway" carry very different weight at month end. --}}
                <span class="text-muted fs-8 d-block">
                    Recorded by hand by {{ $payment->recordedBy->name }}
                </span>
            @endif
        </div>
    @endif

    @if ($payment->gateway_reference)
        <div class="col-sm-6">
            <span class="text-muted d-block fs-8">Transaction reference</span>
            <span class="fw-semibold text-gray-900">{{ $payment->gateway_reference }}</span>
        </div>
    @endif
</div>

{{-- ================= The visit ================= --}}
@if ($reservation)
    <div class="border border-gray-300 border-dashed rounded p-4 mb-5">
        <div class="fw-bold text-gray-800 mb-3">The visit</div>

        <div class="row g-3 fs-7">
            <div class="col-sm-6">
                <span class="text-muted d-block fs-8">Reservation</span>
                <span class="fw-semibold text-gray-900">{{ $reservation->reference_code }}</span>
                <span class="badge badge-light-{{ $reservation->status->colour() }} fs-8 ms-1">
                    {{ $reservation->status->label() }}
                </span>
            </div>

            <div class="col-sm-6">
                <span class="text-muted d-block fs-8">Session</span>
                <span class="fw-semibold text-gray-900">{{ $reservation->title() }}</span>
            </div>

            <div class="col-sm-6">
                <span class="text-muted d-block fs-8">Date</span>
                <span class="fw-semibold text-gray-900">
                    {{ $reservation->reserved_date->format('l, j F Y') }}
                </span>
            </div>

            <div class="col-sm-6">
                <span class="text-muted d-block fs-8">Visitor</span>
                <span class="fw-semibold text-gray-900">{{ $reservation->user?->name }}</span>
                <span class="text-muted fs-8 d-block">
                    {{ $reservation->user?->email }}
                    @if ($reservation->user?->phone)
                        &middot; {{ $reservation->user->phone }}
                    @endif
                </span>
            </div>
        </div>

        @can('reservations.view')
            <a href="{{ route('admin.reservations.index', ['q' => $reservation->reference_code, 'status' => 'all', 'range' => 'all']) }}"
                class="fs-8 d-inline-block mt-3">Open the reservation</a>
        @endcan
    </div>
@endif

{{-- ================= Receipts (Phase 12B) ================= --}}
@if ($payment->transactions->isNotEmpty())
    <div class="border border-gray-300 border-dashed rounded p-4 mb-5">
        <div class="fw-bold text-gray-800 mb-3">Receipts</div>

        @foreach ($payment->transactions as $receipt)
            <div class="d-flex align-items-start flex-wrap gap-2 {{ !$loop->last ? 'border-bottom border-gray-300 border-dashed pb-3 mb-3' : '' }}">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <span class="fw-bold text-gray-900">{{ $receipt->reference }}</span>
                        <span class="badge badge-light-{{ $receipt->channel->colour() }} fs-8">
                            {{ $receipt->channel->label() }}
                        </span>
                    </div>
                    <div class="text-muted fs-8">
                        {{ $receipt->method->label() }}
                        &middot; {{ $receipt->received_at->format('j M Y, g:i A') }}
                        @if ($receipt->external_reference)
                            &middot; {{ $receipt->external_reference }}
                        @endif
                    </div>
                    @if ($receipt->note)
                        <div class="text-gray-600 fs-8 fst-italic mt-1">“{{ $receipt->note }}”</div>
                    @endif
                </div>

                <div class="text-end">
                    <div class="fw-bold text-success">BDT {{ number_format((float) $receipt->amount) }}</div>
                    <a class="fs-8" target="_blank"
                        href="{{ route('admin.payments.payslip', ['payment' => $payment, 'transaction' => $receipt]) }}">
                        Payslip
                    </a>
                </div>
            </div>
        @endforeach

        {{-- The visitor's own copy sits behind the payment token rather than
             behind a login, so this is the link that goes in their email. Shown
             here so staff can send it by hand while 12C is outstanding. --}}
        <div class="text-muted fs-8 mt-3">
            The visitor's copy of the most recent receipt:
            <a target="_blank"
                href="{{ route('payslip', ['token' => $payment->token, 'transaction' => $payment->transactions->first()]) }}">
                open it
            </a>
        </div>
    </div>
@endif

@if ($payment->note)
    <div class="mb-5">
        <div class="text-muted fs-8">Note sent with the request</div>
        <div class="text-gray-700 fs-7 fst-italic">“{{ $payment->note }}”</div>
    </div>
@endif

@if ($payment->status === PaymentStatus::Cancelled && $payment->cancellation_reason)
    <div class="mb-5">
        <div class="text-muted fs-8">Withdrawn because</div>
        <div class="text-gray-700 fs-7">{{ $payment->cancellation_reason }}</div>
    </div>
@endif

{{-- ================= Actions ================= --}}
@if ($payment->isOpen() && (auth()->user()->can('record', $payment) || auth()->user()->can('cancel', $payment)))
    <div class="separator my-5"></div>

    <div class="fw-bold text-gray-800 mb-1">Actions</div>
    <div class="text-muted fs-8 mb-4">
        {{-- PHASE 12B replaces this line once the visitor-facing portal and the
             payment emails exist. Saying so is better than an empty space that
             leaves an admin wondering whether a link was ever sent. --}}
        Nothing has been emailed to the visitor yet — the payment page and its emails arrive in the
        next phase. Send them the reference and take payment however you normally would, then record
        it here.
    </div>

    <div class="d-flex flex-wrap gap-2">
        @can('record', $payment)
            <button type="button" class="btn btn-sm btn-success" data-action="record-payment"
                data-url="{{ route('admin.payments.record', $payment) }}"
                data-reference="{{ $payment->reference }}"
                data-outstanding="{{ number_format($payment->outstanding(), 2, '.', '') }}">
                <i class="ki-outline ki-check-circle fs-5"></i>
                Record a payment
            </button>
        @endcan

        @can('cancel', $payment)
            <button type="button" class="btn btn-sm btn-light-danger" data-action="cancel-payment"
                data-url="{{ route('admin.payments.cancel', $payment) }}"
                data-reference="{{ $payment->reference }}">
                <i class="ki-outline ki-cross-circle fs-5"></i>
                Withdraw this request
            </button>
        @endcan
    </div>
@elseif ($payment->isOpen())
    <div class="separator my-5"></div>
    <div class="text-muted fs-7">You have read-only access to payments. An Admin records and withdraws them.</div>
@endif
