{{--
    Payments report table — one row per RECEIPT, not per payment request.

    Failed gateway attempts share the payment_transactions table with these,
    because Phase 13 wanted the trail of a visitor who tried four times. They
    are filtered out by the receipts() scope before anything reaches here: an
    attempt is not income, and a report that counted them would overstate a
    month by whatever the gateway rejected.

    Voucher settlements ARE shown, badged as such. They settled a reservation,
    so they belong in the account of how it was settled — but no money moved,
    which is why the summary above keeps them in a tile of their own rather than
    adding them to the received figure.
--}}

<div class="table-responsive">
    <table class="table align-middle table-row-dashed fs-7 gy-4">
        <thead>
            <tr class="text-start text-muted fw-bold fs-8 text-uppercase gs-0">
                <th class="min-w-125px">Receipt</th>
                <th class="min-w-125px">Received</th>
                <th class="min-w-150px">Visitor</th>
                <th class="min-w-125px">Reservation</th>
                <th class="min-w-125px">How</th>
                <th class="text-end min-w-100px">Amount</th>
                <th class="min-w-125px">Recorded by</th>
            </tr>
        </thead>

        <tbody class="text-gray-700">
            @forelse ($rows as $transaction)
                <tr>
                    <td>
                        <span class="fw-bold text-gray-900 d-block">{{ $transaction->reference }}</span>
                        @if ($transaction->external_reference)
                            <span class="text-muted fs-8">{{ $transaction->external_reference }}</span>
                        @endif
                    </td>

                    <td>
                        <span class="d-block">{{ $transaction->received_at?->format('j M Y') }}</span>
                        <span class="text-muted fs-8">{{ $transaction->received_at?->format('g:i A') }}</span>
                    </td>

                    <td>
                        <span class="d-block">{{ $transaction->payment?->reservation?->user?->name ?? '—' }}</span>
                        <span class="text-muted fs-8">{{ $transaction->payment?->reservation?->user?->email }}</span>
                    </td>

                    <td>
                        @if ($transaction->payment?->reservation)
                            <a href="{{ route('admin.reservations.index', ['q' => $transaction->payment->reservation->reference_code]) }}"
                                class="text-gray-800 text-hover-primary">
                                {{ $transaction->payment->reservation->reference_code }}
                            </a>
                        @else
                            —
                        @endif
                        <span class="text-muted fs-8 d-block">{{ $transaction->payment?->reference }}</span>
                    </td>

                    <td>
                        <span class="badge badge-light-{{ $transaction->channel->colour() }}">
                            {{ $transaction->channel->label() }}
                        </span>
                        <span class="text-muted fs-8 d-block">{{ $transaction->method->label() }}</span>
                    </td>

                    <td class="text-end fw-bold text-gray-900">
                        {{ number_format((float) $transaction->amount) }}
                    </td>

                    <td class="text-muted">{{ $transaction->recordedBy?->name ?? 'Gateway' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-10">
                        No money arrived in this window.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('admin.reports.partials.pager', ['rows' => $rows])
