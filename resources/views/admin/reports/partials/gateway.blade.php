{{--
    Gateway log — every SSLCommerz attempt, successful or not.

    The failures are the point. The payments report shows only receipts, because
    a failed attempt is not income; this is the only place that can answer "the
    visitor says they paid and nothing arrived".
--}}

<div class="table-responsive">
    <table class="table align-middle table-row-dashed fs-7 gy-4">
        <thead>
            <tr class="text-start text-muted fw-bold fs-8 text-uppercase gs-0">
                <th class="min-w-150px">Attempted</th>
                <th class="min-w-150px">Reference</th>
                <th class="min-w-150px">Visitor</th>
                <th class="min-w-125px">Reservation</th>
                <th class="text-end min-w-100px">Amount</th>
                <th class="min-w-110px">Outcome</th>
            </tr>
        </thead>

        <tbody class="text-gray-700">
            @forelse ($rows as $attempt)
                <tr>
                    <td>
                        <span class="d-block">{{ $attempt->created_at?->format('j M Y') }}</span>
                        <span class="text-muted fs-8">{{ $attempt->created_at?->format('g:i A') }}</span>
                    </td>

                    <td>
                        <span class="font-monospace d-block">{{ $attempt->reference }}</span>
                        @if ($attempt->external_reference)
                            <span class="text-muted fs-8">{{ $attempt->external_reference }}</span>
                        @endif
                    </td>

                    <td>
                        <span class="d-block">{{ $attempt->payment?->reservation?->user?->name ?? '—' }}</span>
                        <span class="text-muted fs-8">{{ $attempt->payment?->reservation?->user?->email }}</span>
                    </td>

                    <td>
                        @if ($attempt->payment?->reservation)
                            <a href="{{ route('admin.reservations.index', ['q' => $attempt->payment->reservation->reference_code]) }}"
                                class="text-gray-800 text-hover-primary">
                                {{ $attempt->payment->reservation->reference_code }}
                            </a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>

                    <td class="text-end">{{ number_format((float) $attempt->amount) }}</td>

                    <td>
                        <span class="badge badge-light-{{ $attempt->status->colour() }}">
                            {{ $attempt->status->label() }}
                        </span>
                        @if ($attempt->failure_reason)
                            <span class="text-danger fs-8 d-block text-truncate mw-200px"
                                title="{{ $attempt->failure_reason }}">{{ $attempt->failure_reason }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-10">No gateway attempts in this window.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('admin.reports.partials.pager', ['rows' => $rows])
