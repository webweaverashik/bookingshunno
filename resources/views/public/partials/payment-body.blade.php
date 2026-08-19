{{--
    Everything a voucher redemption can change: the flash line, the state
    banner, the money, the payment options and the receipts.

    Rendered on first load by the portal controller and again by the voucher
    controller after a successful redemption, which returns it as one fragment
    for the script to swap in. One template for both, so the page after a
    redemption cannot drift from the page before one.
--}}

{{-- Flash. Set by the gateway round trip on a normal request; written here by
     the script after an AJAX redemption. Kept above the fold either way:
     somebody returning from a failed payment needs to read this before they
     see the amount again. --}}
<div data-pay-flash>
    @if (session('payment_success'))
        <div class="pay-flash pay-flash--ok">{{ session('payment_success') }}</div>
    @elseif (session('payment_notice'))
        <div class="pay-flash pay-flash--note">{{ session('payment_notice') }}</div>
    @elseif (session('payment_error'))
        <div class="pay-flash pay-flash--bad">{{ session('payment_error') }}</div>
    @endif
</div>

@if ($withdrawn)
    {{-- Rendered rather than 404'd. A visitor following an old link needs to
         know what happened, not to be told the page does not exist. --}}
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
            @if (!$settled)
                <tr class="pay-table__due">
                    <td>Still to pay</td>
                    <td>BDT {{ number_format($payment->outstanding()) }}</td>
                </tr>
            @endif
        @endif
    </table>

    {{-- Only where a booking fee genuinely leaves a balance. On a full payment
         this would read "Remaining: BDT 0", which states a debt of nothing
         rather than nothing owed. --}}
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

        {{-- A real form, POSTing to our own route. The redirect to SSLCommerz
             happens server-side after a session is opened, so the gateway URL is
             never built in the browser and the amount is never something the
             page could be edited to change. Deliberately NOT intercepted by the
             script: it must leave this page. --}}
        @if ($canPayOnline)
            <form method="POST" action="{{ route('payment.gateway.start', $payment->token) }}" class="pay-option">
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

        {{-- Voucher redemption, in two steps. The second step is not ceremony:
             a voucher is single use and all or nothing, so a 2,000 taka gift
             against a 1,500 taka request loses 500, and taking that in one click
             without saying so would be a trap.

             Both steps are swapped in place by the script. The session preview
             is the fallback for a browser that never ran it. --}}
        <div data-voucher-panel>
            @include('public.partials.voucher-panel', ['preview' => session('voucher_preview')])
        </div>

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

     RECEIPTS ONLY, and the filter is not cosmetic. The transactions list also
     holds gateway attempts that failed, were abandoned, or are still in flight;
     those have a null received_at and a null balance_after by design.

     Failed attempts are deliberately not shown to the visitor at all. They are
     diagnostic detail for staff — a list reading "Failed, Failed, Abandoned"
     tells the person who just paid nothing useful and quite a lot that is
     alarming. The admin drawer shows them.
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
