{{--
    One voucher, in full.

    The code is the largest thing on the screen because that is what somebody is
    checking against a card, a phone screen or a printed slip while a visitor
    waits.
--}}

@php
    use App\Enums\Voucher\VoucherStatus;
    $blocked = $voucher->unusableReason();
@endphp

<div class="d-flex align-items-start flex-wrap gap-3 mb-5">
    <div>
        <div class="fs-2 fw-bold text-gray-900">{{ $voucher->code }}</div>
        <div class="text-muted fs-7">
            {{ $voucher->type->label() }}
            @if ($voucher->issuedBy)
                &middot; issued by {{ $voucher->issuedBy->name }}
            @elseif ($voucher->type->isAutomatic())
                &middot; issued automatically
            @endif
            &middot; {{ $voucher->created_at->format('j M Y') }}
        </div>
    </div>
    <div class="ms-auto text-end">
        <div class="fs-2 fw-bold text-gray-900">BDT {{ number_format((float) $voucher->value) }}</div>
        <span class="badge badge-light-{{ $voucher->displayColour() }}">{{ $voucher->displayStatus() }}</span>
    </div>
</div>

@if ($blocked)
    {{-- Stated plainly and specifically. "Expired on 14 August" ends a
         conversation at the counter that "invalid voucher" would prolong. --}}
    <div class="notice d-flex bg-light-danger rounded border-danger border border-dashed p-4 mb-5">
        <i class="ki-outline ki-information-5 fs-2 text-danger me-3"></i>
        <div class="fs-7 text-gray-700">{{ $blocked }}</div>
    </div>
@endif

<div class="border border-gray-300 border-dashed rounded p-4 mb-5">
    <div class="fw-bold text-gray-800 mb-3">What it buys</div>
    <div class="fs-7 text-gray-700">{{ $voucher->type->spendableOn() }}</div>
    @if ($voucher->workshop)
        <div class="fs-8 text-muted mt-1">Restricted to {{ $voucher->workshop->title }}.</div>
    @endif
</div>

<div class="row g-3 fs-7 mb-5">
    <div class="col-sm-6">
        <span class="text-muted d-block fs-8">Valid from</span>
        <span class="fw-semibold text-gray-900">
            {{ $voucher->valid_from?->format('l, j F Y') ?? 'Immediately' }}
        </span>
    </div>

    <div class="col-sm-6">
        <span class="text-muted d-block fs-8">Expires</span>
        <span class="fw-semibold {{ $voucher->hasExpired() ? 'text-danger' : 'text-gray-900' }}">
            {{ $voucher->expires_at?->format('l, j F Y') ?? 'No expiry' }}
        </span>
    </div>

    <div class="col-sm-6">
        <span class="text-muted d-block fs-8">Issued to</span>
        <span class="fw-semibold text-gray-900">{{ $voucher->issued_to_name ?? '—' }}</span>
        <span class="text-muted fs-8 d-block">{{ $voucher->issued_to_email }}</span>
    </div>

    @if ($voucher->reservation)
        <div class="col-sm-6">
            <span class="text-muted d-block fs-8">Earned by</span>
            <span class="fw-semibold text-gray-900">{{ $voucher->reservation->reference_code }}</span>
            @can('reservations.view')
                <a class="fs-8 d-block"
                    href="{{ route('admin.reservations.index', ['q' => $voucher->reservation->reference_code, 'status' => 'all', 'range' => 'all']) }}">
                    Open the reservation
                </a>
            @endcan
        </div>
    @endif
</div>

@if ($voucher->status === VoucherStatus::Redeemed)
    <div class="border border-gray-300 border-dashed rounded p-4 mb-5">
        <div class="fw-bold text-gray-800 mb-2">Redemption</div>
        <div class="fs-7 text-gray-700">
            Used {{ $voucher->redeemed_at?->format('l, j F Y, g:i A') }}
            @if ($voucher->redeemedBy)
                by {{ $voucher->redeemedBy->name }}
            @elseif ($voucher->redeemed_for_reservation_id)
                by the visitor, online
            @endif
            @if ($voucher->redeemedForReservation)
                against {{ $voucher->redeemedForReservation->reference_code }}
            @endif
        </div>
        @if ($voucher->redemption_note)
            <div class="text-muted fs-8 fst-italic mt-1">“{{ $voucher->redemption_note }}”</div>
        @endif
    </div>
@endif

@if ($voucher->note)
    <div class="mb-4">
        <div class="text-muted fs-8">Note</div>
        <div class="text-gray-700 fs-7">{{ $voucher->note }}</div>
    </div>
@endif

@if ($voucher->status === VoucherStatus::Cancelled && $voucher->cancellation_reason)
    <div class="mb-4">
        <div class="text-muted fs-8">Cancelled because</div>
        <div class="text-gray-700 fs-7">{{ $voucher->cancellation_reason }}</div>
    </div>
@endif

@canany(['redeem', 'update', 'cancel', 'delete'], $voucher)
    <div class="separator my-5"></div>

    <div class="d-flex flex-wrap gap-2">
        @can('redeem', $voucher)
            {{-- Drawn whenever the voucher is Active, including when it is
                 expired — the service refuses with a reason the person can read
                 out. A hidden button would leave staff unable to explain. --}}
            <button type="button" class="btn btn-sm btn-success" data-action="redeem-voucher"
                data-url="{{ route('admin.vouchers.redeem', $voucher) }}" data-code="{{ $voucher->code }}"
                data-value="{{ number_format((float) $voucher->value) }}">
                <i class="ki-outline ki-check-circle fs-5"></i>
                Mark as used
            </button>
        @endcan

        @can('update', $voucher)
            {{-- Hidden rather than disabled when the voucher cannot be edited,
                 unlike the redeem button above. Nobody is standing at a counter
                 waiting on an edit, so there is no sentence anyone needs read
                 out — see the note in VoucherPolicy. --}}
            <button type="button" class="btn btn-sm btn-light-primary" data-action="edit-voucher"
                data-url="{{ route('admin.vouchers.edit', $voucher) }}">
                <i class="ki-outline ki-pencil fs-5"></i>
                Edit
            </button>
        @endcan

        @can('cancel', $voucher)
            <button type="button" class="btn btn-sm btn-light-danger" data-action="cancel-voucher"
                data-url="{{ route('admin.vouchers.cancel', $voucher) }}" data-code="{{ $voucher->code }}">
                <i class="ki-outline ki-cross-circle fs-5"></i>
                Cancel it
            </button>
        @endcan

        @can('delete', $voucher)
            {{-- Last, and visually quietest of the destructive pair, because it
                 is almost never the right one. Cancelling leaves a row saying
                 why; this leaves nothing. It is for a voucher that should not
                 have existed, not for one somebody is holding. --}}
            <button type="button" class="btn btn-sm btn-light" data-action="delete-voucher"
                data-url="{{ route('admin.vouchers.destroy', $voucher) }}" data-code="{{ $voucher->code }}">
                <i class="ki-outline ki-trash fs-5"></i>
                Delete
            </button>
        @endcan
    </div>
@endcanany
