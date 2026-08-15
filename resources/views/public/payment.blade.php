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
            <div class="pay-studio">{{ config('app.name') }}</div>
            <div class="pay-ref">{{ $payment->reference }}</div>
        </header>

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
                <div class="pay-option is-pending">
                    <div>
                        <span class="pay-option__title">Pay online</span>
                        <span class="pay-option__sub">bKash, Nagad, card or internet banking</span>
                    </div>
                    <button type="button" disabled>Opening shortly</button>
                </div>

                <div class="pay-option is-pending">
                    <div>
                        <span class="pay-option__title">Redeem a gift voucher</span>
                        <span class="pay-option__sub">If you were given one</span>
                    </div>
                    <button type="button" disabled>Opening shortly</button>
                </div>

                <p class="pay-note">
                    Online payment is being switched on. In the meantime, reply to the email we sent you or
                    call us on {{ $contact['phone'] ?? '' }} and we will take payment directly.
                </p>
            </section>
        @endunless

        {{-- ============ Receipts ============ --}}
        @if ($payment->transactions->isNotEmpty())
            <section class="pay-card">
                <h2>Your receipts</h2>
                @foreach ($payment->transactions as $receipt)
                    <div class="pay-receipt">
                        <div>
                            <span class="pay-receipt__ref">{{ $receipt->reference }}</span>
                            <span class="pay-receipt__meta">
                                {{ $receipt->received_at->format('j M Y') }} &middot;
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
