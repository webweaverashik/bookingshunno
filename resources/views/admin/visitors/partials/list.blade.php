{{--
    Table and paginator together, replaced as one container on every search,
    filter or page change. The paginator links carry the current filters via
    withQueryString(); the JavaScript intercepts them and fetches instead of
    navigating, so a page change keeps the drawer and scroll position.
--}}
<div class="table-responsive">
    <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-4">
        <thead>
            <tr class="fw-bold text-muted text-uppercase fs-7">
                <th class="min-w-250px">Visitor</th>
                <th class="min-w-150px">Contact</th>
                <th class="min-w-100px text-center">Visits</th>
                <th class="min-w-150px">Last reservation</th>
                <th class="min-w-100px text-center">Account</th>
                <th class="min-w-100px text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($visitors as $visitor)
                <tr data-visitor-row>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-40px me-4">
                                <span class="symbol-label bg-light-primary text-primary fw-bold">
                                    {{ Str::upper(Str::substr($visitor->name, 0, 1)) }}
                                </span>
                            </div>
                            <div class="d-flex flex-column">
                                <a href="#" class="text-gray-900 fw-bold fs-6 text-hover-primary"
                                    data-action="view-visitor"
                                    data-url="{{ route('admin.visitors.show', $visitor->id) }}">
                                    {{ $visitor->name }}
                                </a>
                                <span class="text-muted fs-8">
                                    Joined {{ $visitor->created_at->format('j M Y') }}
                                    @if ($visitor->source)
                                        &middot; via {{ $visitor->source->value }}
                                    @endif
                                </span>
                            </div>
                        </div>
                    </td>

                    <td>
                        <span class="text-gray-700 d-block fs-7">{{ $visitor->email }}</span>
                        <span class="text-muted fs-8">{{ $visitor->phone ?: '—' }}</span>
                    </td>

                    <td class="text-center">
                        <span class="badge {{ $visitor->total_reservations > 1 ? 'badge-light-success' : 'badge-light' }}">
                            {{ $visitor->total_reservations }}
                        </span>
                    </td>

                    <td>
                        @if ($visitor->last_reservation_at)
                            <span class="text-gray-700 fs-7 d-block">
                                {{ $visitor->last_reservation_at->format('j M Y') }}
                            </span>
                            <span class="text-muted fs-8">
                                {{ $visitor->last_reservation_at->diffForHumans() }}
                            </span>
                        @else
                            <span class="text-muted fs-8">Never</span>
                        @endif
                    </td>

                    <td class="text-center">
                        <span class="badge {{ $visitor->is_active ? 'badge-light-success' : 'badge-light-danger' }}">
                            {{ $visitor->is_active ? 'Active' : 'Deactivated' }}
                        </span>
                    </td>

                    <td class="text-end">
                        <button type="button" class="btn btn-icon btn-light btn-active-light-primary btn-sm me-1"
                            data-action="view-visitor"
                            data-url="{{ route('admin.visitors.show', $visitor->id) }}"
                            title="View history">
                            <i class="ki-outline ki-eye fs-4"></i>
                        </button>

                        @can('visitors.update')
                            <button type="button" class="btn btn-icon btn-light btn-active-light-primary btn-sm"
                                data-action="edit-visitor"
                                data-url="{{ route('admin.visitors.edit', $visitor->id) }}"
                                title="Edit details">
                                <i class="ki-outline ki-pencil fs-4"></i>
                            </button>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-10">
                        @if ($filters['q'] !== '' || $filters['status'] !== 'all')
                            No visitor matches those filters.
                        @else
                            No visitors yet. They are created automatically when a reservation request comes in.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($visitors->hasPages())
    <div class="d-flex flex-stack flex-wrap pt-4" data-visitors-pagination>
        <div class="fs-7 text-muted">
            Showing {{ $visitors->firstItem() }}–{{ $visitors->lastItem() }} of {{ $visitors->total() }}
        </div>
        {{ $visitors->onEachSide(1)->links('pagination::bootstrap-5') }}
    </div>
@endif
