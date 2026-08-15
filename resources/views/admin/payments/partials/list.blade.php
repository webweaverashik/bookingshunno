{{--
    The table body, swapped whole by payments.js.

    Sortable headers rather than a client-side table library, for the reason
    Phase 9 settled: a browser-side table has to hold every row before it can
    sort one, which defeats the paging it sits on top of. The header links carry
    the whole current filter state so a sort does not silently reset the search.

    DEBUGGING THIS FILE. Blade comments compile to nothing, newlines included,
    so a line number in a Laravel stack trace does NOT line up with this file —
    it is the line in the COMPILED view under storage/framework/views. Every
    comment above a failure shifts it. The row markup below is deliberately kept
    free of comment blocks so that drift stays small; put new notes up here.
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
        'reference' => 'Reference',
        'due' => 'Due',
        'amount' => 'Asked for',
        'paid' => 'Received',
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
                        <a href="{{ $sort['url'] }}" data-payments-sort
                            class="text-hover-primary {{ $sort['active'] ? 'text-gray-900 fw-bolder' : 'text-muted' }}">
                            {{ $label }}
                            <i class="ki-outline ki-{{ $sort['icon'] }} fs-8 ms-1"></i>
                        </a>
                    </th>
                @endforeach
                <th class="min-w-150px">Visitor</th>
                <th class="text-end min-w-70px">Open</th>
            </tr>
        </thead>

        <tbody class="text-gray-700">
            @forelse ($payments as $payment)
                <tr>
                    <td>
                        <span class="fw-bold text-gray-900 d-block">{{ $payment->reference }}</span>
                        <span class="text-muted fs-8">
                            {{ $payment->reservation?->reference_code }}
                            &middot; {{ $payment->type->describe((int) $payment->percentage) }}
                        </span>
                    </td>

                    <td>
                        <span class="{{ $payment->isOverdue() ? 'text-danger fw-bold' : 'text-gray-800' }}">
                            {{ $payment->due_at?->format('j M Y') ?? '—' }}
                        </span>
                        <span class="d-block fs-8 {{ $payment->isOverdue() ? 'text-danger' : 'text-muted' }}">
                            {{ $payment->isOverdue() ? 'Overdue · ' : '' }}{{ $payment->due_at?->format('g:i A') }}
                        </span>
                    </td>

                    <td>
                        <span class="fw-semibold text-gray-900">
                            BDT {{ number_format((float) $payment->amount_due) }}
                        </span>
                        @if ($payment->type->leavesBalance())
                            <span class="d-block text-muted fs-8">
                                of {{ number_format((float) $payment->reservation_total) }}
                            </span>
                        @endif
                    </td>

                    <td>
                        @if ((float) $payment->amount_paid > 0)
                            <span class="fw-semibold text-success">
                                BDT {{ number_format((float) $payment->amount_paid) }}
                            </span>
                            @if ($payment->isPartiallyPaid())
                                <span class="d-block text-warning fs-8">
                                    {{ number_format($payment->outstanding()) }} still to come
                                </span>
                            @elseif ($payment->method)
                                <span class="d-block text-muted fs-8">{{ $payment->method->label() }}</span>
                            @endif
                        @else
                            <span class="text-muted">&mdash;</span>
                        @endif
                    </td>

                    <td>
                        <span class="badge badge-light-{{ $payment->status->colour() }}">
                            {{ $payment->status->label() }}
                        </span>
                    </td>

                    <td>
                        <span
                            class="text-gray-800 d-block">{{ $payment->reservation?->user?->name ?? 'Unknown' }}</span>
                        <span class="text-muted fs-8">{{ $payment->reservation?->user?->phone }}</span>
                    </td>

                    <td class="text-end">
                        <button type="button" class="btn btn-icon btn-light btn-active-light-primary btn-sm"
                            data-action="view-payment" data-url="{{ route('admin.payments.show', $payment) }}"
                            title="Open this payment">
                            <i class="ki-outline ki-eye fs-4"></i>
                        </button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-10">
                        @if ($filters['q'] !== '' || $filters['status'] !== 'open')
                            No payment matches those filters.
                        @else
                            Nothing awaiting payment. Requests appear here once an Admin asks a visitor to pay.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($payments->total() > 0)
    <div class="d-flex flex-stack flex-wrap gap-3 pt-3" data-payments-pagination>
        <div class="text-muted fs-7">
            Showing {{ $payments->firstItem() }}&ndash;{{ $payments->lastItem() }}
            of {{ number_format($payments->total()) }}
        </div>
        {{ $payments->links() }}
    </div>
@endif
