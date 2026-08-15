{{--
    The payslip. Issued for every settlement, online or manual.

    A standalone document rather than a page inside either layout: it is printed
    and filed, so it carries no navigation, no admin chrome and no site header.
    Print styling lives in public/css/payslip.css behind @media print.

    Every figure here comes from the TRANSACTION, not from live totals. A
    receipt must say the same thing next month as it does today — see
    balance_after on payment_transactions for why that is snapshotted.

    No PDF library. Browsers print HTML to PDF perfectly well, it works
    identically for staff and visitors, and §24 says not to install packages
    that are not necessary. If the client later wants a PDF attached to the
    email rather than a link, that is when a renderer earns its place.
--}}

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $transaction->title() }} {{ $transaction->reference }} — {{ config('app.name') }}</title>
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
                <img src="{{ asset('img/shunno-logo.png') }}" alt="{{ config('app.name') }}"
                    class="slip-mark" onerror="this.style.display='none'">
                <div>
                    <div class="slip-studio-name">{{ config('app.name') }}</div>
                    <div class="slip-studio-meta">
                        {{ $studio['address'] ?? '' }}<br>
                        {{ $studio['phone'] ?? '' }} &middot; {{ $studio['email'] ?? '' }}
                    </div>
                </div>
            </div>

            <div class="slip-title">
                <div class="slip-title-main">{{ $transaction->title() }}</div>
                <div class="slip-ref">{{ $transaction->reference }}</div>
                <div class="slip-date">{{ $transaction->received_at->format('j F Y, g:i A') }}</div>
            </div>
        </header>

        {{-- The one thing a person looks for first. --}}
        <div class="slip-amount">
            <span class="slip-amount-label">Received</span>
            <span class="slip-amount-value">BDT {{ number_format((float) $transaction->amount) }}</span>
            <span class="slip-amount-channel slip-channel--{{ $transaction->channel->value }}">
                {{ $transaction->channel->label() }} &middot; {{ $transaction->method->label() }}
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
                <tr class="slip-row--paid">
                    <td>Paid on this receipt</td>
                    <td>BDT {{ number_format((float) $transaction->amount) }}</td>
                </tr>
                <tr>
                    <td>Still to pay on this request</td>
                    <td>BDT {{ number_format((float) $transaction->balance_after) }}</td>
                </tr>

                {{-- Only where a booking fee genuinely leaves a balance. On a full
                     payment this row would read "Remaining: BDT 0", which states a
                     debt of nothing rather than nothing owed. --}}
                @if ($payment->type->leavesBalance() && $transaction->settledInFull())
                    <tr class="slip-row--balance">
                        <td>Payable at the studio on the day</td>
                        <td>BDT {{ number_format($payment->remainingOnReservation()) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>

        <section class="slip-notes">
            <p><strong>{{ $transaction->channel->assurance() }}</strong>
                @if ($transaction->external_reference)
                    Transaction reference {{ $transaction->external_reference }}.
                @endif
                Payment request {{ $payment->reference }}.
            </p>

            @if ($transaction->settledInFull())
                <p>This request is settled in full. Your reservation is confirmed — we look forward to
                    seeing you.</p>
            @else
                <p>This is a part payment. BDT {{ number_format((float) $transaction->balance_after) }}
                    remains on this request and your reservation is not confirmed until it is paid.</p>
            @endif

            @if ($transaction->note)
                <p class="slip-muted">{{ $transaction->note }}</p>
            @endif
        </section>

        <footer class="slip-foot">
            <div>
                {{ config('app.name') }} &middot; {{ $studio['address'] ?? '' }}
                <br>Issued {{ $transaction->created_at->format('j M Y') }}. This receipt is computer
                generated and valid without a signature.
            </div>

            {{-- Staff-only footnote. It can ADD context; it must never change or
                 remove a figure, or the studio and the visitor end up holding
                 two different documents. --}}
            @if ($staff)
                <div class="slip-internal">
                    Internal: recorded by {{ $transaction->recordedBy?->name ?? 'the system' }}
                    on {{ $transaction->created_at->format('j M Y, g:i A') }}.
                    @if ($transaction->received_at->notEqualTo($transaction->created_at))
                        Backdated from {{ $transaction->created_at->format('j M') }}.
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
