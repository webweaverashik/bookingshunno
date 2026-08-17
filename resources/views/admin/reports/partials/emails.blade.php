{{--
    Email log.

    "Sent" here means the SMTP server ACCEPTED the message, not that it arrived.
    The wording is careful throughout for a practical reason: a log that claims
    delivery sends staff looking in the wrong place when a visitor says nothing
    came. Acceptance rules out our end; the spam folder is still the next
    question.
--}}

<div class="table-responsive">
    <table class="table align-middle table-row-dashed fs-7 gy-4">
        <thead>
            <tr class="text-start text-muted fw-bold fs-8 text-uppercase gs-0">
                <th class="min-w-150px">Queued</th>
                <th class="min-w-175px">To</th>
                <th class="min-w-200px">Subject</th>
                <th class="min-w-125px">About</th>
                <th class="min-w-110px">Status</th>
                <th class="min-w-125px">Triggered by</th>
            </tr>
        </thead>

        <tbody class="text-gray-700">
            @forelse ($rows as $message)
                <tr>
                    <td>
                        <span class="d-block">{{ $message->queued_at?->format('j M Y') }}</span>
                        <span class="text-muted fs-8">{{ $message->queued_at?->format('g:i A') }}</span>
                    </td>

                    <td>
                        <span class="d-block text-gray-900">{{ $message->to_email }}</span>
                        @if ($message->is_resend)
                            <span class="badge badge-light-info fs-9">Resend</span>
                        @endif
                    </td>

                    <td class="text-truncate mw-300px" title="{{ $message->subject }}">{{ $message->subject }}</td>

                    <td>
                        @if ($message->reservation)
                            <a href="{{ route('admin.reservations.index', ['q' => $message->reservation->reference_code]) }}"
                                class="text-gray-800 text-hover-primary">{{ $message->reservation->reference_code }}</a>
                        @elseif ($message->payment)
                            <span>{{ $message->payment->reference }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                        <span
                            class="text-muted fs-8 d-block">{{ $message->mailKind()?->label() ?? $message->kind }}</span>
                    </td>

                    <td>
                        <span class="badge badge-light-{{ $message->status->colour() }}">
                            {{ $message->status->label() }}
                        </span>

                        {{-- The error is the whole reason a failed row is worth
                             keeping, so it is shown rather than hidden behind a
                             detail view. --}}
                        @if ($message->error)
                            <span class="text-danger fs-8 d-block text-truncate mw-200px"
                                title="{{ $message->error }}">{{ $message->error }}</span>
                        @elseif ($message->sent_at)
                            <span class="text-muted fs-8 d-block">{{ $message->sent_at->format('g:i A') }}</span>
                        @endif
                    </td>

                    <td class="text-muted">{{ $message->triggeredBy?->name ?? 'System' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted py-10">No messages in this window.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@include('admin.reports.partials.pager', ['rows' => $rows])
