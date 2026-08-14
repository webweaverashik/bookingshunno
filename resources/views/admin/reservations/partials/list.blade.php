{{--
    The whole list container: table plus paginator. Swapped wholesale by
    reservations.js after any search, filter, page change or edit — nothing in
    the browser builds a row.
--}}

<div class="table-responsive">
    <table class="table align-middle table-row-dashed fs-6 gy-4 mb-0">
        <thead>
            <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                <th class="min-w-125px">Reference</th>
                <th class="min-w-150px">Visitor</th>
                <th class="min-w-150px">Session</th>
                <th class="min-w-125px">When</th>
                <th class="text-center min-w-70px">People</th>
                <th class="text-end min-w-100px">Total</th>
                <th class="min-w-100px">Status</th>
                <th class="text-end min-w-100px">Actions</th>
            </tr>
        </thead>

        <tbody class="text-gray-700 fw-semibold">
            @forelse ($reservations as $reservation)
                <tr>
                    <td>
                        <span class="text-gray-900 fw-bold d-block">{{ $reservation->reference_code }}</span>
                        <span class="text-muted fs-8">
                            {{ $reservation->source?->label() ?? 'Web' }} &middot;
                            {{ $reservation->created_at->diffForHumans() }}
                        </span>
                    </td>

                    <td>
                        <span class="text-gray-900 d-block">{{ $reservation->user?->name ?? 'Unknown' }}</span>
                        <span class="text-muted fs-8">{{ $reservation->user?->phone ?: $reservation->user?->email }}</span>
                    </td>

                    <td>
                        <span class="d-block">{{ $reservation->items->first()?->title_snapshot ?? 'Visit' }}</span>
                    </td>

                    <td>
                        <span class="text-gray-900 d-block">
                            {{ $reservation->reserved_date->format('j M Y') }}
                        </span>
                        <span class="text-muted fs-8">
                            {{ \Carbon\CarbonImmutable::createFromTimeString($reservation->start_time)->format('g:i A') }}
                            &ndash;
                            {{ \Carbon\CarbonImmutable::createFromTimeString($reservation->end_time)->format('g:i A') }}
                        </span>
                    </td>

                    <td class="text-center">{{ $reservation->participants }}</td>

                    <td class="text-end">
                        <span class="text-gray-900 d-block">BDT {{ number_format((float) $reservation->total_amount) }}</span>
                        @if ((float) $reservation->discount_amount > 0)
                            <span class="text-success fs-8">
                                &minus;{{ number_format((float) $reservation->discount_amount) }} off
                            </span>
                        @endif
                    </td>

                    <td>
                        <span class="badge badge-light-{{ $reservation->status->colour() }}">
                            {{ $reservation->status->label() }}
                        </span>
                    </td>

                    <td class="text-end">
                        <button type="button" class="btn btn-icon btn-light btn-active-light-primary btn-sm me-1"
                            data-action="view-reservation"
                            data-url="{{ route('admin.reservations.show', $reservation) }}"
                            title="View full record">
                            <i class="ki-outline ki-eye fs-4"></i>
                        </button>

                        @can('update', $reservation)
                            <button type="button" class="btn btn-icon btn-light btn-active-light-primary btn-sm"
                                data-action="edit-reservation"
                                data-url="{{ route('admin.reservations.edit', $reservation) }}"
                                title="{{ $reservation->isEditable() ? 'Edit the visit' : 'Add a note (visit is locked)' }}">
                                <i class="ki-outline ki-pencil fs-4"></i>
                            </button>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-10">
                        @if ($filters['q'] !== '' || $filters['status'] !== 'open' || $filters['range'] !== 'upcoming' || $filters['workshop'] !== 'all')
                            No reservation matches those filters.
                        @else
                            Nothing open and upcoming. New requests from the website appear here as
                            <em>Pending review</em>.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($reservations->hasPages() || $reservations->total() > 0)
    <div class="d-flex flex-stack flex-wrap pt-4" data-reservations-pagination>
        <div class="fs-7 text-muted">
            Showing {{ $reservations->firstItem() }}&ndash;{{ $reservations->lastItem() }}
            of {{ number_format($reservations->total()) }}
        </div>

        {{ $reservations->onEachSide(1)->links('pagination::bootstrap-5') }}
    </div>
@endif
