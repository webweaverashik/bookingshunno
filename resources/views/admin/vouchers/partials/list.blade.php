{{--
    The voucher table, swapped whole by vouchers.js.

    Server-side sorting rather than a browser table library, per the Phase 9
    decision: a client-side table has to hold every row before it can sort one,
    which defeats the paging it sits on.

    Comments are kept out of the row below — Blade comments compile to nothing,
    newlines included, so each one shifts the line numbers in a stack trace away
    from the source. Notes belong up here.
--}}

@php
    $sortLink = function (string $key) use ($filters) {
        $active = $filters['sort'] === $key;
        $next = $active && $filters['dir'] === 'asc' ? 'desc' : 'asc';

        return [
            'url' => request()->fullUrlWithQuery(['sort' => $key, 'dir' => $next, 'page' => 1]),
            'active' => $active,
            'icon' => $active ? ($filters['dir'] === 'asc' ? 'arrow-up' : 'arrow-down') : 'arrow-up-down',
        ];
    };

    $columns = [
        'code' => 'Code',
        'value' => 'Value',
        'expires' => 'Expires',
        'status' => 'Status',
    ];
@endphp

<div class="table-responsive">
    <table class="table align-middle table-row-dashed fs-7 gy-4">
        <thead>
            <tr class="text-start text-muted fw-bold fs-8 text-uppercase gs-0">
                @foreach ($columns as $key => $label)
                    @php($sort = $sortLink($key))
                    <th class="min-w-100px">
                        <a href="{{ $sort['url'] }}" data-vouchers-sort
                            class="text-hover-primary {{ $sort['active'] ? 'text-gray-900 fw-bolder' : 'text-muted' }}">
                            {{ $label }}
                            <i class="ki-outline ki-{{ $sort['icon'] }} fs-8 ms-1"></i>
                        </a>
                    </th>
                @endforeach
                <th class="min-w-150px">Issued to</th>
                <th class="text-end min-w-70px">Open</th>
            </tr>
        </thead>

        <tbody class="text-gray-700">
            @forelse ($vouchers as $voucher)
                <tr>
                    <td>
                        <span class="fw-bold text-gray-900 d-block">{{ $voucher->code }}</span>
                        <span class="text-muted fs-8">
                            {{ $voucher->type->label() }}
                            @if ($voucher->workshop)
                                &middot; {{ $voucher->workshop->title }}
                            @elseif ($voucher->reservation)
                                &middot; from {{ $voucher->reservation->reference_code }}
                            @endif
                        </span>
                    </td>

                    <td>
                        <span class="fw-semibold text-gray-900">
                            BDT {{ number_format((float) $voucher->value) }}
                        </span>
                    </td>

                    <td>
                        @if ($voucher->expires_at)
                            <span class="{{ $voucher->hasExpired() ? 'text-danger' : 'text-gray-800' }}">
                                {{ $voucher->expires_at->format('j M Y') }}
                            </span>
                            @if ($voucher->notYetValid())
                                <span class="d-block text-warning fs-8">
                                    from {{ $voucher->valid_from->format('j M') }}
                                </span>
                            @endif
                        @else
                            <span class="text-muted">No expiry</span>
                        @endif
                    </td>

                    <td>
                        <span class="badge badge-light-{{ $voucher->displayColour() }}">
                            {{ $voucher->displayStatus() }}
                        </span>
                    </td>

                    <td>
                        <span class="text-gray-800 d-block">{{ $voucher->issued_to_name ?? '—' }}</span>
                        <span class="text-muted fs-8">{{ $voucher->issued_to_email }}</span>
                    </td>

                    <td class="text-end">
                        <button type="button" class="btn btn-icon btn-light btn-active-light-primary btn-sm"
                            data-action="view-voucher" data-url="{{ route('admin.vouchers.show', $voucher) }}"
                            title="Open this voucher">
                            <i class="ki-outline ki-eye fs-4"></i>
                        </button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-10">
                        @if ($filters['q'] !== '' || $filters['status'] !== 'usable' || $filters['type'] !== 'all')
                            No voucher matches those filters.
                        @else
                            No vouchers in circulation. Café credit appears here automatically once a
                            qualifying visit is paid for.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($vouchers->total() > 0)
    <div class="d-flex flex-stack flex-wrap gap-3 pt-3" data-vouchers-pagination>
        <div class="text-muted fs-7">
            Showing {{ $vouchers->firstItem() }}&ndash;{{ $vouchers->lastItem() }}
            of {{ number_format($vouchers->total()) }}
        </div>
        {{ $vouchers->links() }}
    </div>
@endif
