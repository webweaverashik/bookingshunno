{{--
    The whole list container: table, sortable headers, page-size choice and
    paginator. Swapped wholesale by reservations.js after any search, filter,
    sort, page change, edit or decision — nothing in the browser builds a row.

    Sorting is server-side, like the paging and the search already
    were. See the note in RendersReservations for why this is not DataTables.
--}}

@php
    // Clicking the current sort column flips it; clicking another starts that
    // one in the direction that column is usually wanted in.
    $sortLink = function (string $key, string $preferred = 'asc') use ($filters) {
        $active = $filters['sort'] === $key;
        $next = $active ? ($filters['dir'] === 'asc' ? 'desc' : 'asc') : $preferred;

        return ['active' => $active, 'dir' => $next, 'current' => $filters['dir']];
    };

    $columns = [
        ['key' => 'reference', 'label' => 'Reference', 'class' => 'min-w-125px', 'preferred' => 'desc'],
        ['key' => null, 'label' => 'Visitor', 'class' => 'min-w-150px'],
        ['key' => null, 'label' => 'Session', 'class' => 'min-w-150px'],
        ['key' => 'date', 'label' => 'When', 'class' => 'min-w-125px', 'preferred' => 'asc'],
        ['key' => 'people', 'label' => 'People', 'class' => 'text-center min-w-70px', 'preferred' => 'desc'],
        ['key' => 'total', 'label' => 'Total', 'class' => 'text-end min-w-100px', 'preferred' => 'desc'],
        ['key' => 'status', 'label' => 'Status', 'class' => 'min-w-100px', 'preferred' => 'asc'],
        ['key' => null, 'label' => 'Actions', 'class' => 'text-end min-w-100px'],
    ];
@endphp

<div class="table-responsive">
    <table class="table align-middle table-row-dashed fs-6 gy-4 mb-0">
        <thead>
            <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                @foreach ($columns as $column)
                    <th class="{{ $column['class'] }}">
                        @if ($column['key'])
                            @php $sort = $sortLink($column['key'], $column['preferred']); @endphp
                            <button type="button"
                                class="btn btn-link btn-flush text-muted text-uppercase fw-bold fs-7 p-0 {{ $sort['active'] ? 'text-primary' : '' }}"
                                data-sort="{{ $column['key'] }}" data-dir="{{ $sort['dir'] }}">
                                {{ $column['label'] }}
                                @if ($sort['active'])
                                    <i
                                        class="ki-outline ki-{{ $sort['current'] === 'asc' ? 'arrow-up' : 'arrow-down' }} fs-8 ms-1 text-primary"></i>
                                @endif
                            </button>
                        @else
                            {{ $column['label'] }}
                        @endif
                    </th>
                @endforeach
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
                        <span
                            class="text-muted fs-8">{{ $reservation->user?->phone ?: $reservation->user?->email }}</span>
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
                        <span class="text-gray-900 d-block">
                            BDT {{ number_format($reservation->payableTotal()) }}
                        </span>

                        @if ($reservation->hasManualPrice())
                            {{-- An agreed figure is not the price list, and the row should
                                 say so without anyone having to open the record. --}}
                            <span class="badge badge-light-primary fs-8"
                                title="Agreed price. Calculated total is BDT {{ number_format($reservation->calculatedTotal()) }}.">
                                Agreed price
                            </span>
                        @elseif ((float) $reservation->discount_amount > 0)
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
                            data-url="{{ route('admin.reservations.show', $reservation) }}" title="View full record">
                            <i class="ki-outline ki-eye fs-4"></i>
                        </button>

                        @can('update', $reservation)
                            <button type="button" class="btn btn-icon btn-light btn-active-light-primary btn-sm"
                                data-action="edit-reservation"
                                data-url="{{ route('admin.reservations.edit', $reservation) }}"
                                title="Edit the visit">
                                <i class="ki-outline ki-pencil fs-4"></i>
                            </button>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-10">
                        @if ($filters['q'] !== '' || $filters['status'] !== 'all' || $filters['range'] !== 'all' || $filters['workshop'] !== 'all')
                            No reservation matches those filters.
                        @else
                            No reservations yet. New requests from the website appear here as
                            <em>Pending review</em>.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($reservations->total() > 0)
    <div class="d-flex flex-stack flex-wrap pt-4" data-reservations-pagination>
        <div class="fs-7 text-muted">
            Showing {{ $reservations->firstItem() }}&ndash;{{ $reservations->lastItem() }}
            of {{ number_format($reservations->total()) }}
        </div>

        {{ $reservations->onEachSide(1)->links('pagination::bootstrap-5') }}
    </div>
@endif
