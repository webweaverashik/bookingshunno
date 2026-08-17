{{--
    The staff table. Swapped wholesale by users.js when a filter changes.

    "Last seen" is the column people actually open this page for — a staff
    account nobody has used for months is either a person who left or a
    credential sitting unused, and both are worth noticing.
--}}

<div class="table-responsive">
    <table class="table align-middle table-row-dashed fs-7 gy-4">
        <thead>
            <tr class="text-start text-muted fw-bold fs-8 text-uppercase gs-0">
                <th class="min-w-200px">Name</th>
                <th class="min-w-125px">Role</th>
                <th class="min-w-150px">Contact</th>
                <th class="min-w-175px">Last seen</th>
                <th class="min-w-100px">Status</th>
                <th class="text-end min-w-100px">Actions</th>
            </tr>
        </thead>

        <tbody class="text-gray-700">
            @forelse ($users as $user)
                @php($login = $user->latestLoginActivity)
                <tr @class(['opacity-75' => !$user->is_active])>
                    <td>
                        <div class="d-flex align-items-center">
                            <span class="symbol symbol-35px me-3">
                                <span class="symbol-label bg-light-primary text-primary fw-bold">
                                    {{ \Illuminate\Support\Str::of($user->name)->substr(0, 1)->upper() }}
                                </span>
                            </span>
                            <div class="min-w-0">
                                <span class="fw-bold text-gray-900 d-block">{{ $user->name }}</span>
                                <span class="text-muted fs-8">{{ $user->email }}</span>
                            </div>
                            @if ($user->id === auth()->id())
                                <span class="badge badge-light ms-2">You</span>
                            @endif
                        </div>
                    </td>

                    <td>
                        @foreach ($user->roles as $role)
                            <span
                                class="badge badge-light-{{ $role->name === 'Admin' ? 'success' : 'info' }}">{{ $role->name }}</span>
                        @endforeach
                    </td>

                    <td>
                        <span class="d-block">{{ $user->phone ?: '—' }}</span>
                        @if ($user->whatsapp && $user->whatsapp !== $user->phone)
                            <span class="text-muted fs-8">WhatsApp {{ $user->whatsapp }}</span>
                        @endif
                    </td>

                    <td>
                        @if ($login)
                            <span class="d-block">{{ $login->created_at?->diffForHumans() }}</span>
                            <span class="text-muted fs-8">
                                {{ $login->created_at?->format('j M Y, g:i A') }} &middot; {{ $login->device }}
                            </span>
                        @else
                            <span class="text-muted">Never signed in</span>
                        @endif
                    </td>

                    <td>
                        @if ($user->is_active)
                            <span class="badge badge-light-success">Active</span>
                        @else
                            <span class="badge badge-light-danger">Deactivated</span>
                        @endif
                    </td>

                    <td class="text-end">
                        <div class="d-flex justify-content-end gap-1">
                            @can('users.update')
                                <button type="button" class="btn btn-sm btn-icon btn-light-primary"
                                    data-user-edit="{{ $user->id }}" title="Edit">
                                    <i class="ki-outline ki-pencil fs-4"></i>
                                </button>

                                {{-- Hidden entirely for your own row rather than
                                     shown and refused. The server refuses it
                                     either way; offering a button that always
                                     fails is just a worse way to say no. --}}
                                @if ($user->id !== auth()->id())
                                    <button type="button"
                                        class="btn btn-sm btn-icon btn-light-{{ $user->is_active ? 'warning' : 'success' }}"
                                        data-user-toggle="{{ $user->id }}"
                                        data-user-name="{{ $user->name }}"
                                        data-user-active="{{ $user->is_active ? 1 : 0 }}"
                                        title="{{ $user->is_active ? 'Deactivate' : 'Activate' }}">
                                        <i
                                            class="ki-outline ki-{{ $user->is_active ? 'lock-2' : 'lock-3' }} fs-4"></i>
                                    </button>
                                @endif
                            @endcan

                            @can('users.delete')
                                @if ($user->id !== auth()->id())
                                    <button type="button" class="btn btn-sm btn-icon btn-light-danger"
                                        data-user-delete="{{ $user->id }}" data-user-name="{{ $user->name }}"
                                        title="Remove">
                                        <i class="ki-outline ki-trash fs-4"></i>
                                    </button>
                                @endif
                            @endcan
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-10">No staff match that.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($users->hasPages())
    <div class="d-flex flex-stack flex-wrap gap-3 pt-4">
        <span class="text-muted fs-7">
            Showing {{ $users->firstItem() }}–{{ $users->lastItem() }} of {{ number_format($users->total()) }}
        </span>
        <div data-users-pager>{{ $users->onEachSide(1)->links() }}</div>
    </div>
@endif
