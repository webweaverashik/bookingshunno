@forelse ($blocks as $block)
    <tr data-block-row>
        <td>
            <span class="text-gray-900 fw-bold d-block">{{ $block->date->format('j M Y') }}</span>
            <span class="text-muted fs-8">{{ $block->date->format('l') }}</span>
        </td>

        <td>
            <span class="badge {{ $block->is_full_day ? 'badge-light-danger' : 'badge-light-warning' }}">
                {{ $block->windowLabel() }}
            </span>
        </td>

        <td class="text-gray-700">
            {{ $block->reason ?: '—' }}
        </td>

        <td class="text-muted fs-7">
            {{ $block->creator?->name ?? 'System' }}
        </td>

        <td class="text-end">
            @can('update', $block)
                <button type="button" class="btn btn-icon btn-light btn-active-light-primary btn-sm me-1"
                    data-action="edit-block"
                    data-url="{{ route('admin.availability.blocked.edit', $block->id) }}"
                    title="Edit">
                    <i class="ki-outline ki-pencil fs-4"></i>
                </button>
            @endcan

            @can('delete', $block)
                <button type="button" class="btn btn-icon btn-light btn-active-light-danger btn-sm"
                    data-action="delete-block"
                    data-label="{{ $block->date->format('j M Y') }}"
                    data-url="{{ route('admin.availability.blocked.destroy', $block->id) }}"
                    title="Remove">
                    <i class="ki-outline ki-trash fs-4"></i>
                </button>
            @endcan

            @cannot('update', $block)
                @cannot('delete', $block)
                    <span class="text-muted fs-8">View only</span>
                @endcannot
            @endcannot
        </td>
    </tr>
@empty
    <tr>
        <td colspan="5" class="text-center text-muted py-10">
            No upcoming closures. Sundays are handled by the opening hours above and do not need blocking.
        </td>
    </tr>
@endforelse
