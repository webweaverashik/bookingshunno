{{--
    Settings changes.

    Every row here is a change to something that can break the studio quietly.
    That is the whole selection criterion — a wrong phone number is visible on
    the site the moment it is saved, so it is not what this page is for; a
    gateway switched to sandbox is invisible until a bank statement disagrees a
    month later, so it is.

    Sensitive rows are marked rather than sorted to the top. Chronological order
    is what makes a log readable — "what happened that afternoon" is the
    question people arrive with — and a badge draws the eye without breaking it.
--}}

<div class="table-responsive">
    <table class="table align-middle table-row-dashed fs-7 gy-4">
        <thead>
            <tr class="text-start text-muted fw-bold fs-8 text-uppercase gs-0">
                <th class="min-w-150px">When</th>
                <th class="min-w-200px">Setting</th>
                <th class="min-w-250px">Change</th>
                <th class="min-w-150px">By</th>
            </tr>
        </thead>

        <tbody class="text-gray-700">
            @forelse ($rows as $change)
                <tr @class(['bg-light-warning bg-opacity-25' => $change->isSensitive()])>
                    <td>
                        <span class="d-block">{{ $change->created_at?->format('j M Y') }}</span>
                        <span class="text-muted fs-8">
                            {{ $change->created_at?->format('g:i A') }} &middot;
                            {{ $change->created_at?->diffForHumans() }}
                        </span>
                    </td>

                    <td>
                        <span class="fw-semibold text-gray-900 d-block">{{ $change->label() }}</span>
                        <span class="badge badge-light fs-9">{{ $change->group() }}</span>
                        @if ($change->isSensitive())
                            <span class="badge badge-light-warning fs-9">Worth a look</span>
                        @endif
                    </td>

                    <td>
                        @if ($change->is_secret)
                            {{-- Said plainly. The value was never written, so
                                 there is nothing withheld and nothing to imply
                                 was withheld. --}}
                            <span class="text-gray-700">
                                <i class="ki-outline ki-lock-2 fs-6 me-1 text-danger"></i>
                                {{ $change->describe() }}
                            </span>
                        @else
                            <span class="text-gray-700 text-break">{{ $change->describe() }}</span>
                        @endif
                    </td>

                    <td>
                        @if ($change->changedBy)
                            <span class="d-block">{{ $change->changedBy->name }}</span>
                        @else
                            {{-- Either a console command or a staff account that
                                 has since been removed. The foreign key is
                                 nullOnDelete precisely so the row outlives the
                                 person — that is the moment somebody looks. --}}
                            <span class="text-muted">System or removed account</span>
                        @endif
                        <span class="text-muted fs-8 font-monospace">{{ $change->ip_address ?? '—' }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center text-muted py-10">
                        No settings were changed in this window.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('admin.reports.partials.pager', ['rows' => $rows])
