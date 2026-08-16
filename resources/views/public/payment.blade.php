{{--
    The visitor's payment page.

    Standalone rather than inside the public layout: somebody arriving here has
    one thing to do, and a site header offering them the landing page and the
    reservation popup is an invitation to wander off mid-payment.

    Two ways to settle, per the client: pay online, or redeem a voucher. There
    is deliberately NO manual-payment panel — no bank details, no bKash number.
    Staff record offline payments from the admin panel when they take them;
    publishing an account number here would invite visitors to transfer money
    that nobody is watching for.
--}}

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Complete your payment — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/payment-portal.css') }}">
</head>

<body>
    <main class="pay">

        <header class="pay-head">
            {{-- Logo with the studio name as its alt text, so a blocked or
                 missing image degrades to the name rather than to nothing. --}}
            <img src="{{ asset('img/shunno-logo.png') }}" alt="{{ config('app.name') }}" class="pay-logo">
            <div class="pay-ref">{{ $payment->reference }}</div>
        </header>

        {{-- Set by the gateway controller on the way back. Kept above the fold:
             somebody returning from a failed payment needs to know that before
             they read the amount again. --}}
        @if (session('payment_success'))
            <div class="pay-flash pay-flash--ok">{{ session('payment_success') }}</div>
        @endif

        @if (session('payment_error'))
            <div class="pay-flash pay-flash--bad">{{ session('payment_error') }}</div>
        @endif

        @if ($withdrawn)
            {{-- Rendered rather than 404'd. A visitor following an old link needs
                 to know what happened, not to be told the page does not exist. --}}
            <div class="pay-state pay-state--off">
                <h1>This payment request has been withdrawn</h1>
                <p>
                    Nothing is owed on this link. If you were expecting to pay for
                    {{ $reservation?->reference_code }}, please get in touch and we will sort it out.
                </p>
            </div>
        @elseif ($settled)
            <div class="pay-state pay-state--done">
                <h1>Paid — thank you</h1>
                <p>
                    We received BDT {{ number_format((float) $payment->amount_paid) }} and your visit is
                    confirmed. We look forward to seeing you.
                </p>
            </div>
        @else
            <div class="pay-lede">
                <h1>Complete your payment</h1>
                <p>
                    Hello {{ $reservation?->user?->name }} — your visit is approved and this is the last
                    step.
                </p>
            </div>
        @endif

        {{-- ============ What the visit is ============ --}}
        @if ($reservation)
            <section class="pay-card">
                <h2>Your visit</h2>
                <dl class="pay-dl">
                    <div>
                        <dt>What</dt>
                        <dd>{{ $reservation->title() }}</dd>
                    </div>
                    <div>
                        <dt>When</dt>
                        <dd>
                            {{ $reservation->reserved_date->format('l, j F Y') }}<br>
                            {{ \Carbon\CarbonImmutable::createFromTimeString($reservation->start_time)->format('g:i A') }}
                        </dd>
                    </div>
                    <div>
                        <dt>Who</dt>
                        <dd>
                            {{ $reservation->participants }}
                            {{ \Illuminate\Support\Str::plural('person', $reservation->participants) }}
                        </dd>
                    </div>
                    <div>
                        <dt>Reference</dt>
                        <dd>{{ $reservation->reference_code }}</dd>
                    </div>
                </dl>
            </section>
        @endif

        {{-- ============ The money ============ --}}
        <section class="pay-card">
            <h2>What to pay</h2>

            <table class="pay-table">
                <tr>
                    <td>Reservation total</td>
                    <td>BDT {{ number_format((float) $payment->reservation_total) }}</td>
                </tr>
                <tr>
                    <td>Payment type</td>
                    <td>{{ $payment->type->describe((int) $payment->percentage) }}</td>
                </tr>
                <tr class="pay-table__due">
                    <td>Payment required</td>
                    <td>BDT {{ number_format((float) $payment->amount_due) }}</td>
                </tr>
                @if ((float) $payment->amount_paid > 0)
                    <tr>
                        <td>Already received</td>
                        <td>BDT {{ number_format((float) $payment->amount_paid) }}</td>
                    </tr>
                    <tr class="pay-table__due">
                        <td>Still to pay</td>
                        <td>BDT {{ number_format($payment->outstanding()) }}</td>
                    </tr>
                @endif
            </table>

            {{-- Only where a booking fee genuinely leaves a balance. On a full
                 payment this would read "Remaining: BDT 0", which states a debt
                 of nothing rather than nothing owed. --}}
            @if ($payment->type->leavesBalance())
                <p class="pay-note">
                    The remaining BDT {{ number_format($payment->remainingOnReservation()) }} is payable at
                    the studio on the day of your visit.
                </p>
            @endif

            @if ($payment->note)
                <p class="pay-note pay-note--quote">{{ $payment->note }}</p>
            @endif

            @unless ($settled || $withdrawn)
                <p class="pay-deadline {{ $overdue ? 'is-late' : '' }}">
                    @if ($overdue)
                        This was due {{ $payment->due_at->format('l, j F') }}. You can still pay, but please
                        check with us first that your slot is free.
                    @else
                        Please pay by <strong>{{ $payment->due_at->format('l, j F, g:i A') }}</strong>.
                    @endif
                </p>
            @endunless
        </section>

        {{-- ============ How to pay ============ --}}
        @unless ($settled || $withdrawn)
            <section class="pay-card pay-card--actions">
                <h2>How would you like to pay?</h2>

                {{--
                    Both buttons are inert in this phase and say so. An amount
                    with no way to pay it is more confusing than a button that
                    explains itself; a button that looks live and does nothing is
                    worse than either.
                --}}
                {{--
                    A real form, POSTing to our own route. The redirect to
                    SSLCommerz happens server-side after a session is opened, so
                    the gateway URL is never built in the browser and the amount
                    is never something the page could be edited to change.
                --}}
                @if ($canPayOnline)
                    <form method="POST" action="{{ route('payment.gateway.start', $payment->token) }}"
                        class="pay-option">
                        @csrf
                        <div>
                            <span class="pay-option__title">Pay online</span>
                            <span class="pay-option__sub">bKash, Nagad, card or internet banking</span>
                        </div>
                        <button type="submit">Pay BDT {{ number_format($payment->outstanding()) }}</button>
                    </form>
                @else
                    <div class="pay-option is-pending">
                        <div>
                            <span class="pay-option__title">Pay online</span>
                            <span class="pay-option__sub">Temporarily unavailable</span>
                        </div>
                        <button type="button" disabled>Unavailable</button>
                    </div>
                @endif

                {{--
                    PHASE 14C — voucher redemption, in two steps.

                    The second step is not ceremony. A voucher is single use and
                    all or nothing, so a 2,000 taka gift against a 1,500 taka
                    request loses 500 — and taking that in one click without
                    saying so would be a trap. The confirmation panel below spells
                    it out before anything is spent.

                    No JavaScript anywhere in this page, so this is two plain form
                    posts. A checkout that quietly stops working when a script
                    fails to load is worse than one extra page render.
                --}}
                @if (session('voucher_preview'))
                    @php($preview = session('voucher_preview'))

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
                            {{-- The whole reason this step exists. Stated before the
                                 button, in taka, not buried in small print. --}}
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

                        <form method="POST" action="{{ route('payment.voucher.apply', $payment->token) }}">
                            @csrf
                            <input type="hidden" name="code" value="{{ $preview['code'] }}">
                            <button type="submit" class="pay-voucher-confirm__go">
                                Use this voucher
                            </button>
                        </form>
                    </div>
                @else
                    <form method="POST" action="{{ route('payment.voucher.check', $payment->token) }}"
                        class="pay-option pay-option--voucher">
                        @csrf
                        <div>
                            <span class="pay-option__title">Redeem a gift voucher</span>
                            <span class="pay-option__sub">If you were given one</span>
                            {{-- Uppercased and spellcheck off: these codes are read
                                 off a card, and a browser helpfully autocorrecting
                                 GIFT-2608-K4RT is not help. --}}
                            <input type="text" name="code" class="pay-voucher-code"
                                placeholder="GIFT-2608-K4RT" autocomplete="off" spellcheck="false"
                                autocapitalize="characters" maxlength="24">
                        </div>
                        <button type="submit">Apply</button>
                    </form>
                @endif

                <p class="pay-note">
                    @if ($canPayOnline)
                        Payments are handled by SSLCommerz. You will be taken to their secure page and
                        brought back here afterwards.
                    @else
                        Online payment is unavailable just now. Please call us on
                        {{ $contact['phone'] ?? '' }} or reply to the email we sent you, and we will take
                        payment directly.
                    @endif
                </p>
            </section>
        @endunless

        {{-- ============ Receipts ============

             RECEIPTS ONLY, and the filter is not cosmetic. Since Phase 13 the
             transactions list also holds gateway attempts that failed, were
             abandoned, or are still in flight; those have a null received_at
             and a null balance_after by design, and rendering one here is what
             put a ->format() call on a null.

             Failed attempts are deliberately not shown to the visitor at all.
             They are diagnostic detail for staff — a list reading "Failed,
             Failed, Abandoned" tells the person who just paid nothing useful
             and quite a lot that is alarming. The admin drawer shows them.
        --}}
        @php($receipts = $payment->receipts())

        @if ($receipts->isNotEmpty())
            <section class="pay-card">
                <h2>Your receipts</h2>
                @foreach ($receipts as $receipt)
                    <div class="pay-receipt">
                        <div>
                            <span class="pay-receipt__ref">{{ $receipt->reference }}</span>
                            <span class="pay-receipt__meta">
                                {{ $receipt->received_at?->format('j M Y') ?? '—' }} &middot;
                                {{ $receipt->method->label() }}
                            </span>
                        </div>
                        <div class="pay-receipt__right">
                            <span class="pay-receipt__amount">
                                BDT {{ number_format((float) $receipt->amount) }}
                            </span>
                            <a target="_blank"
                                href="{{ route('payslip', ['token' => $payment->token, 'transaction' => $receipt]) }}">
                                View payslip
                            </a>
                        </div>
                    </div>
                @endforeach
            </section>
        @endif

        <footer class="pay-foot">
            <p>
                {{ config('app.name') }} &middot; {{ $contact['address'] ?? '' }}<br>
                {{ $contact['phone'] ?? '' }} &middot; {{ $contact['email'] ?? '' }}
            </p>
            <p class="pay-foot__small">
                This link is personal to your reservation. Please do not forward it.
            </p>
        </footer>
    </main>
</body>

</html>
