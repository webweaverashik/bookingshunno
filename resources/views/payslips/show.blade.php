{{--
    The payslip.

    ONE DOCUMENT PER PAYMENT REQUEST, not one per transaction. It used to be the
    latter, and a booking part-paid with a gift voucher and finished on a card
    produced two payslips and two emails describing halves of the same thing.
    Neither document showed the whole story, and the visitor had to add them up
    themselves to see what they had paid for.

    So this lists every receipt on the request — the coupon, the card payment,
    anything taken at the counter — and totals them. The URL still names a
    single transaction, because that is what the link in an email points at and
    old links have to keep working; it is used to mark which line the reader
    arrived for, and for nothing else.

    A standalone document rather than a page inside either layout: it is printed
    and filed, so it carries no navigation, no admin chrome and no site header.
    Print styling lives in public/css/payslip.css behind @media print.

    Figures come from the payment and from each transaction as recorded — never
    recomputed from live totals at render time. A receipt must say the same
    thing next month as it does today.

    No PDF library. Browsers print HTML to PDF perfectly well, it works
    identically for staff and visitors, and §24 says not to install packages
    that are not necessary.
--}}

@php
    $receipts = $payment->receipts()->sortBy('received_at');

    // What the coupons took off, kept separate from money that actually moved.
    // The client asked for the code and the discount to be visible, and a
    // voucher line sitting unlabelled among card payments would read as though
    // somebody had handed over cash.
    $vouchers = $receipts->where('channel', \App\Enums\Payment\PaymentChannel::Voucher);
    $voucherTotal = (float) $vouchers->sum('amount');

    // The last receipt carries the outcome: whether the request closed, and
    // which gateway settled it.
    $last = $receipts->last() ?? $transaction;
@endphp

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment receipt {{ $payment->reference }} — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="{{ asset('css/payslip.css') }}">
</head>

<body>
    <div class="slip">

        <header class="slip-head">
            <div class="slip-studio">
                {{-- Height-constrained with auto width so the same file works
                     whether the logo is square or wide. The onerror keeps a
                     missing file from leaving a broken-image icon on a document
                     somebody is about to print. --}}
                <img src="{{ asset('img/shunno-logo.png') }}" alt="{{ config('app.name') }}" class="slip-mark"
                    onerror="this.style.display='none'">
                <div>
                    <div class="slip-studio-name">{{ config('app.name') }}</div>
                    <div class="slip-studio-meta">
                        {{ $studio['address'] ?? '' }}<br>
                        {{ $studio['phone'] ?? '' }} &middot; {{ $studio['email'] ?? '' }}
                    </div>
                </div>
            </div>

            <div class="slip-title">
                <div class="slip-title-main">Payment receipt</div>
                <div class="slip-ref">{{ $payment->reference }}</div>
                <div class="slip-date">{{ $last->received_at?->format('j F Y, g:i A') }}</div>
            </div>
        </header>

        {{-- The one thing a person looks for first: everything received against
             this request, not only the line whose link they followed. --}}
        <div class="slip-amount">
            <span class="slip-amount-label">Received</span>
            <span class="slip-amount-value">BDT {{ number_format((float) $payment->amount_paid) }}</span>
            <span class="slip-amount-channel slip-channel--{{ $last->channel->value }}">
                @if ($receipts->count() > 1)
                    {{ $receipts->count() }} receipts
                @else
                    {{ $last->channel->label() }} &middot; {{ $last->method->label() }}
                @endif
            </span>
        </div>

        <section class="slip-grid">
            <div>
                <h2>Received from</h2>
                <p class="slip-strong">{{ $reservation?->user?->name ?? 'Visitor' }}</p>
                <p>
                    {{ $reservation?->user?->email }}
                    @if ($reservation?->user?->phone)
                        <br>{{ $reservation->user->phone }}
                    @endif
                </p>
            </div>

            <div>
                <h2>For</h2>
                <p class="slip-strong">{{ $reservation?->title() ?? 'Visit' }}</p>
                <p>
                    @if ($reservation)
                        {{ $reservation->reserved_date->format('l, j F Y') }}<br>
                        {{ \Carbon\CarbonImmutable::createFromTimeString($reservation->start_time)->format('g:i A') }}
                        &middot; {{ $reservation->participants }}
                        {{ \Illuminate\Support\Str::plural('person', $reservation->participants) }}<br>
                        Reservation {{ $reservation->reference_code }}
                    @endif
                </p>
            </div>
        </section>

        {{--
            The brief's payment summary, line for line. Reservation total,
            payment type, payment required, amount paid, remaining — kept in
            that order and with that wording so the document and the
            specification cannot drift apart.

            The voucher lines are an addition to it rather than a departure:
            they sit between what was asked for and what was paid, because that
            is where they belong in the arithmetic and where somebody reading
            down the column will look for the difference between the two.
        --}}
        <table class="slip-table">
            <tbody>
                <tr>
                    <td>Reservation total</td>
                    <td>BDT {{ number_format((float) $payment->reservation_total) }}</td>
                </tr>
                <tr>
                    <td>Payment type</td>
                    <td>{{ $payment->type->describe((int) $payment->percentage) }}</td>
                </tr>
                <tr>
                    <td>Payment required</td>
                    <td>BDT {{ number_format((float) $payment->amount_due) }}</td>
                </tr>

                @foreach ($vouchers as $voucher)
                    <tr class="slip-row--voucher">
                        <td>Voucher {{ $voucher->external_reference }}</td>
                        <td>&minus; BDT {{ number_format((float) $voucher->amount) }}</td>
                    </tr>
                @endforeach

                @if ($voucherTotal > 0)
                    <tr>
                        <td>Payable after voucher</td>
                        <td>BDT {{ number_format(max(0, (float) $payment->amount_due - $voucherTotal)) }}</td>
                    </tr>
                @endif

                <tr class="slip-row--paid">
                    <td>Paid in total</td>
                    <td>BDT {{ number_format((float) $payment->amount_paid) }}</td>
                </tr>
                <tr>
                    <td>Still to pay on this request</td>
                    <td>BDT {{ number_format($payment->outstanding()) }}</td>
                </tr>

                {{-- Only where a booking fee genuinely leaves a balance, and
                     only once this request is settled. On a full payment this
                     row would read "Remaining: BDT 0", which states a debt of
                     nothing rather than nothing owed. --}}
                @if ($payment->type->leavesBalance() && $payment->outstanding() <= 0)
                    <tr class="slip-row--balance">
                        <td>Payable at the studio on the day</td>
                        <td>BDT {{ number_format($payment->remainingOnReservation()) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>

        {{--
            Every receipt behind the figures above.

            Rendered even when there is only one, so the document has the same
            shape either way and nobody wonders whether a missing table means a
            missing payment. The row the reader followed a link to is marked,
            which is the only use the URL's transaction is put to.
        --}}
        <section class="slip-receipts">
            <h2>Receipts</h2>
            <table class="slip-table slip-table--rows">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th class="slip-num">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($receipts as $receipt)
                        <tr @class(['slip-row--this' => $receipt->is($transaction)])>
                            <td>{{ $receipt->received_at?->format('j M Y') }}</td>
                            <td>
                                {{ $receipt->method->label() }}
                                <span class="slip-muted">&middot; {{ $receipt->channel->label() }}</span>
                            </td>
                            <td>
                                {{ $receipt->reference }}
                                @if ($receipt->external_reference)
                                    <br><span class="slip-muted">{{ $receipt->external_reference }}</span>
                                @endif
                            </td>
                            <td class="slip-num">BDT {{ number_format((float) $receipt->amount) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="slip-notes">
            <p><strong>{{ $last->channel->assurance() }}</strong>
                @if ($last->external_reference)
                    Transaction reference {{ $last->external_reference }}.
                @endif
                Payment request {{ $payment->reference }}.
            </p>

            @if ($payment->outstanding() <= 0)
                <p>This request is settled in full. Your reservation is confirmed — we look forward to
                    seeing you.</p>
            @else
                <p>BDT {{ number_format($payment->outstanding()) }} remains on this request and your
                    reservation is not confirmed until it is paid.</p>
            @endif

            @foreach ($receipts as $receipt)
                @if ($receipt->note)
                    <p class="slip-muted">{{ $receipt->note }}</p>
                @endif
            @endforeach
        </section>

        <footer class="slip-foot">
            <div>
                {{ config('app.name') }} &middot; {{ $studio['address'] ?? '' }}
                <br>Issued {{ $last->created_at->format('j M Y') }}. This receipt is computer
                generated and valid without a signature.
            </div>

            {{-- Staff-only footnote. It can ADD context; it must never change or
                 remove a figure, or the studio and the visitor end up holding
                 two different documents. --}}
            @if ($staff)
                <div class="slip-internal">
                    Internal: recorded by {{ $last->recordedBy?->name ?? 'the system' }}
                    on {{ $last->created_at->format('j M Y, g:i A') }}.
                    @if ($last->received_at && $last->received_at->notEqualTo($last->created_at))
                        Backdated from {{ $last->created_at->format('j M') }}.
                    @endif
                </div>
            @endif
        </footer>

        <div class="slip-actions no-print">
            <button type="button" onclick="window.print()">Print or save as PDF</button>
        </div>
    </div>
</body>

</html>
