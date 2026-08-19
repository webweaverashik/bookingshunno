{{--
    One of two things: the code entry form, or the confirmation of a code that
    has been checked but not yet spent.

    Both states in one partial because the script swaps between them by
    replacing this markup wholesale — two partials would mean two endpoints
    returning two shapes, and a caller having to know which.

    Every figure here is formatted by PHP. The script never computes a taka
    amount; it only puts rendered HTML where the old HTML was.
--}}

@php($preview = $preview ?? null)

@if ($preview)
    <div class="pay-voucher-confirm">
        <div class="pay-voucher-confirm__head">
            <span class="pay-voucher-confirm__code">{{ $preview['code'] }}</span>
            <span class="pay-voucher-confirm__value">
                BDT {{ number_format($preview['value']) }}
            </span>
        </div>

        <p>
            This will pay <strong>BDT {{ number_format($preview['applies']) }}</strong>
            towards your reservation.
        </p>

        @if ($preview['forfeit'] > 0)
            {{-- The whole reason this step exists. Stated before the button, in
                 taka, not buried in small print. --}}
            <p class="pay-voucher-confirm__warn">
                Your voucher is worth more than you owe. Vouchers are used once, in one
                go, so the remaining
                <strong>BDT {{ number_format($preview['forfeit']) }}</strong>
                will be lost. You may prefer to keep this one for a larger booking.
            </p>
        @endif

        @if ($preview['remaining'] > 0)
            <p>
                <strong>BDT {{ number_format($preview['remaining']) }}</strong> will still
                be left to pay, which you can do online straight afterwards.
            </p>
        @endif

        <form method="POST" action="{{ route('payment.voucher.apply', $payment->token) }}" data-voucher-apply>
            @csrf
            <input type="hidden" name="code" value="{{ $preview['code'] }}">
            <button type="submit" class="pay-voucher-confirm__go">
                Use this voucher
            </button>
            {{-- Backing out must be as easy as going ahead, and it must not cost
                 a page load. Hidden without JavaScript, where the way back is
                 simply not submitting. --}}
            <button type="button" class="pay-voucher-confirm__cancel" data-voucher-cancel hidden>
                Not now
            </button>
        </form>
    </div>
@else
    <form method="POST" action="{{ route('payment.voucher.check', $payment->token) }}"
        class="pay-option pay-option--voucher" data-voucher-check>
        @csrf
        <div>
            <span class="pay-option__title">Redeem a gift voucher</span>
            <span class="pay-option__sub">If you were given one</span>
            {{-- Uppercased and spellcheck off: these codes are read off a card,
                 and a browser helpfully autocorrecting GIFT-2608-K4RT is not
                 help. --}}
            <input type="text" name="code" class="pay-voucher-code" placeholder="GIFT-2608-K4RT"
                autocomplete="off" spellcheck="false" autocapitalize="characters" maxlength="24">
        </div>
        <button type="submit">Apply</button>
    </form>
@endif
