{{--
    Visitors report table.

    Sorted by what they spent, descending — the studio's own question when it
    opens this is "who are our regulars", and that is the honest answer to it.

    No pager. This is an aggregate over the range rather than a page of database
    rows; see ReportService::visitors() for the size bound that makes that safe
    and for the note on when it would need rewriting.
--}}

<div class="table-responsive">
    <table class="table align-middle table-row-dashed fs-7 gy-4">
        <thead>
            <tr class="text-start text-muted fw-bold fs-8 text-uppercase gs-0">
                <th class="min-w-175px">Visitor</th>
                <th class="min-w-150px">Contact</th>
                <th class="text-center min-w-80px">Visits</th>
                <th class="text-center min-w-80px">Guests</th>
                <th class="text-end min-w-100px">Billed</th>
                <th class="text-end min-w-100px">Paid</th>
                <th class="min-w-110px">Last visit</th>
                <th class="text-end min-w-90px">Lifetime</th>
            </tr>
        </thead>

        <tbody class="text-gray-700">
            @forelse ($rows as $row)
                <tr>
                    <td>
                        @if ($row->user)
                            <a href="{{ route('admin.visitors.index', ['q' => $row->user->email]) }}"
                                class="fw-bold text-gray-900 text-hover-primary">{{ $row->user->name }}</a>
                        @else
                            <span class="text-muted">Account removed</span>
                        @endif

                        @if ($row->visits > 1)
                            <span class="badge badge-light-success ms-2">Returning</span>
                        @endif
                    </td>

                    <td>
                        <span class="d-block">{{ $row->user?->email ?? '—' }}</span>
                        <span class="text-muted fs-8">{{ $row->user?->phone }}</span>
                    </td>

                    <td class="text-center fw-bold">{{ $row->visits }}</td>
                    <td class="text-center">{{ $row->participants }}</td>

                    <td class="text-end">{{ number_format($row->billed) }}</td>
                    <td class="text-end fw-bold text-gray-900">{{ number_format($row->paid) }}</td>

                    <td>{{ $row->last ? \Carbon\CarbonImmutable::parse($row->last)->format('j M Y') : '—' }}</td>

                    <td class="text-end text-muted">
                        {{ number_format((int) ($row->user?->total_reservations ?? 0)) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-10">
                        Nobody visited in this window.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($rows->isNotEmpty())
    <div class="pt-4">
        <span class="text-muted fs-7">
            {{ number_format($rows->count()) }} {{ \Illuminate\Support\Str::plural('visitor', $rows->count()) }}
            in this window. Download the CSV for the full list.
        </span>
    </div>
@endif
