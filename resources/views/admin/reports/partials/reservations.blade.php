{{--
    Reservations report table.

    Columns chosen to answer "what happened, for whom, and was it paid" without
    opening anything. The reference links through to the register, so a row that
    raises a question is one click from the drawer that answers it.

    DEBUGGING: Blade comments compile away, newlines included, so a line number
    in a stack trace does not line up with this file — it is the line in the
    compiled view under storage/framework/views. Notes stay up here for that
    reason; the row markup below is left free of them.
--}}

<div class="table-responsive">
    <table class="table align-middle table-row-dashed fs-7 gy-4">
        <thead>
            <tr class="text-start text-muted fw-bold fs-8 text-uppercase gs-0">
                <th class="min-w-125px">Reference</th>
                <th class="min-w-100px">Visit</th>
                <th class="min-w-150px">Experience</th>
                <th class="min-w-150px">Visitor</th>
                <th class="text-center min-w-60px">Guests</th>
                <th class="min-w-110px">Status</th>
                <th class="text-end min-w-90px">Total</th>
                <th class="text-end min-w-90px">Paid</th>
                <th class="text-end min-w-90px">Outstanding</th>
            </tr>
        </thead>

        <tbody class="text-gray-700">
            @forelse ($rows as $reservation)
                <tr>
                    <td>
                        <a href="{{ route('admin.reservations.index', ['q' => $reservation->reference_code]) }}"
                            class="fw-bold text-gray-900 text-hover-primary">{{ $reservation->reference_code }}</a>
                    </td>

                    <td>
                        <span class="d-block">{{ $reservation->reserved_date->format('j M Y') }}</span>
                        <span class="text-muted fs-8">{{ substr((string) $reservation->start_time, 0, 5) }}</span>
                    </td>

                    <td>{{ $reservation->title() }}</td>

                    <td>
                        <span class="d-block">{{ $reservation->user?->name ?? '—' }}</span>
                        <span class="text-muted fs-8">{{ $reservation->user?->phone }}</span>
                    </td>

                    <td class="text-center">{{ $reservation->participants }}</td>

                    <td>
                        <span class="badge badge-light-{{ $reservation->status->colour() }}">
                            {{ $reservation->status->label() }}
                        </span>
                    </td>

                    <td class="text-end">{{ number_format($reservation->payableTotal()) }}</td>
                    <td class="text-end">{{ number_format($reservation->amountPaid()) }}</td>
                    <td class="text-end {{ $reservation->outstandingTotal() > 0 ? 'text-danger fw-bold' : 'text-muted' }}">
                        {{ number_format($reservation->outstandingTotal()) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-10">
                        No reservations in this window.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('admin.reports.partials.pager', ['rows' => $rows])
