{{--
    Rendered on first paint by index.blade.php and returned as HTML by every
    mutating endpoint. Keeping the markup here rather than in JavaScript means
    a row is described exactly once.
--}}
@forelse ($workshops as $workshop)
    <tr data-workshop-row data-id="{{ $workshop->id }}">

        <td>
            <div class="d-flex align-items-center">
                <div class="symbol symbol-50px me-4">
                    @if ($url = $workshop->imageUrl())
                        <img src="{{ $url }}" alt="" class="w-50px h-50px rounded object-fit-cover" loading="lazy">
                    @else
                        <span class="symbol-label bg-light-secondary">
                            <i class="ki-outline ki-picture fs-2 text-gray-500"></i>
                        </span>
                    @endif
                </div>
                <div class="d-flex flex-column">
                    <span class="text-gray-900 fw-bold fs-6">
                        {{ $workshop->title }}
                        @if ($workshop->is_featured)
                            <i class="ki-outline ki-star fs-6 text-warning ms-1" title="Featured"></i>
                        @endif
                    </span>
                    <span class="text-muted fw-semibold fs-7">
                        {{ $workshop->medium ?: '—' }}
                    </span>
                    <span class="text-muted fs-8">
                        {{ $workshop->slug }}
                        @if ($workshop->reservation_items_count)
                            &middot; {{ $workshop->reservation_items_count }}
                            {{ Str::plural('reservation', $workshop->reservation_items_count) }}
                        @endif
                    </span>
                </div>
            </div>
        </td>

        <td>
            <span class="badge {{ $workshop->category?->badgeClass() ?? 'badge-light' }}">
                {{ $workshop->categoryLabel() }}
            </span>
        </td>

        <td class="text-end">
            <span class="text-gray-900 fw-bold">{{ number_format((float) $workshop->price) }}</span>
            <span class="text-muted fs-8 d-block">
                BDT {{ $workshop->price_basis === 'per_session' ? 'per session' : 'per person' }}
            </span>
        </td>

        <td class="text-end text-gray-700 fw-semibold">
            {{ $workshop->durationLabel() }}
        </td>

        <td class="text-center text-gray-700 fw-semibold">
            {{ $workshop->min_participants }}–{{ $workshop->max_participants }}
        </td>

        <td class="text-center">
            @can('update', $workshop)
                <button type="button"
                    class="btn btn-sm btn-light py-1 px-3"
                    data-action="toggle"
                    data-active="{{ $workshop->is_active ? 1 : 0 }}"
                    data-url="{{ route('admin.workshops.toggle', $workshop->id) }}"
                    title="{{ $workshop->is_active ? 'Hide from the website' : 'Show on the website' }}">
                    <span class="badge {{ $workshop->is_active ? 'badge-light-success' : 'badge-light-danger' }}">
                        {{ $workshop->is_active ? 'Live' : 'Hidden' }}
                    </span>
                </button>
            @else
                <span class="badge {{ $workshop->is_active ? 'badge-light-success' : 'badge-light-danger' }}">
                    {{ $workshop->is_active ? 'Live' : 'Hidden' }}
                </span>
            @endcan
        </td>

        <td class="text-center text-muted fw-semibold">{{ $workshop->sort_order }}</td>

        <td class="text-end">
            @can('update', $workshop)
                <button type="button" class="btn btn-icon btn-light btn-active-light-primary btn-sm me-1"
                    data-action="edit"
                    data-id="{{ $workshop->id }}"
                    data-url="{{ route('admin.workshops.edit', $workshop->id) }}"
                    title="Edit">
                    <i class="ki-outline ki-pencil fs-4"></i>
                </button>
            @endcan

            {{-- No delete button, by decision rather than by permission: a
                 workshop is referenced by every reservation item that ever
                 quoted it, so removing one takes the explanation out of last
                 year's figures. The Live/Hidden toggle in the previous column
                 is the action people are actually reaching for — it takes the
                 session off the website and out of the booking form, and leaves
                 the history intact. See WorkshopPolicy::delete(). --}}

            @cannot('update', $workshop)
                <span class="text-muted fs-8">View only</span>
            @endcannot
        </td>
    </tr>
@empty
    <tr>
        <td colspan="8" class="text-center text-muted py-10">
            No workshops yet. Run <code>php artisan db:seed --class=WorkshopSeeder</code>
            to load the printed menu, or add one above.
        </td>
    </tr>
@endforelse
