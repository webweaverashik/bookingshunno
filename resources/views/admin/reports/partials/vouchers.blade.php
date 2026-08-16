{{--
    Vouchers report table.

    Status here is displayStatus(), not the database column. An Active voucher
    three months past its date is Active in the row and Expired to everybody
    looking at it, and a report saying otherwise would have staff honouring
    codes that are not good. The CSV writes the same derived value for the same
    reason.
--}}

<div class="table-responsive">
    <table class="table align-middle table-row-dashed fs-7 gy-4">
        <thead>
            <tr class="text-start text-muted fw-bold fs-8 text-uppercase gs-0">
                <th class="min-w-150px">Code</th>
                <th class="min-w-110px">Type</th>
                <th class="text-end min-w-90px">Value</th>
                <th class="min-w-110px">Issued</th>
                <th class="min-w-110px">Expires</th>
                <th class="min-w-150px">Issued to</th>
                <th class="min-w-110px">Status</th>
                <th class="min-w-125px">Spent on</th>
            </tr>
        </thead>

        <tbody class="text-gray-700">
            @forelse ($rows as $voucher)
                <tr>
                    <td>
                        <a href="{{ route('admin.vouchers.index', ['q' => $voucher->code]) }}"
                            class="fw-bold text-gray-900 text-hover-primary font-monospace">{{ $voucher->code }}</a>
                        @if ($voucher->reservation)
                            <span class="text-muted fs-8 d-block">
                                Earned by {{ $voucher->reservation->reference_code }}
                            </span>
                        @endif
                    </td>

                    <td>{{ $voucher->type->label() }}</td>

                    <td class="text-end fw-bold text-gray-900">{{ number_format((float) $voucher->value) }}</td>

                    <td>{{ $voucher->created_at?->format('j M Y') }}</td>

                    <td>
                        {{ $voucher->expires_at?->format('j M Y') ?? 'No expiry' }}
                    </td>

                    <td>
                        <span class="d-block">{{ $voucher->issued_to_name ?? '—' }}</span>
                        <span class="text-muted fs-8">{{ $voucher->issued_to_email }}</span>
                    </td>

                    <td>
                        <span class="badge badge-light-{{ $voucher->displayColour() }}">
                            {{ $voucher->displayStatus() }}
                        </span>
                    </td>

                    <td>
                        @if ($voucher->redeemedForReservation)
                            <span class="d-block">{{ $voucher->redeemedForReservation->reference_code }}</span>
                        @elseif ($voucher->redeemed_at)
                            <span class="d-block text-muted">At the counter</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif

                        @if ($voucher->redeemed_at)
                            <span class="text-muted fs-8">{{ $voucher->redeemed_at->format('j M Y') }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-10">
                        No vouchers issued in this window.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('admin.reports.partials.pager', ['rows' => $rows])
