{{--
    PHASE 13B — what was sent, and whether it left.

    Loaded on demand rather than rendered with the drawer. Most of the time
    nobody asks this question, and a query per drawer open to answer it would be
    a cost paid on every reservation view for the benefit of the few where
    delivery is in doubt.

    NOTE ON WORDING. Nothing here says "delivered". SMTP acceptance is not
    delivery — a message can be accepted and then bounce, or land in spam — and
    a badge reading Delivered would have staff telling a visitor their email
    definitely arrived when nobody can know that.
--}}

@forelse ($messages as $message)
    <div class="d-flex align-items-start flex-wrap gap-2 {{ !$loop->last ? 'border-bottom border-gray-300 border-dashed pb-3 mb-3' : '' }}">
        <div class="flex-grow-1">
            <div class="d-flex align-items-center flex-wrap gap-2">
                <span class="fw-semibold text-gray-900 fs-7">{{ $message->subject }}</span>
                <span class="badge badge-light-{{ $message->status->colour() }} fs-8">
                    {{ $message->status->label() }}
                </span>
                @if ($message->is_resend)
                    <span class="badge badge-light-info fs-8">Resent</span>
                @endif
            </div>

            <div class="text-muted fs-8">
                To {{ $message->to_email }}
                &middot; {{ $message->created_at->format('j M Y, g:i A') }}
                @if ($message->triggeredBy)
                    &middot; by {{ $message->triggeredBy->name }}
                @endif
            </div>

            @if ($message->error)
                <div class="text-danger fs-8 mt-1">{{ $message->error }}</div>
            @endif

            {{-- A row still queued long after it was written is the visible
                 symptom of a worker that is not running — on shared hosting,
                 driven by one cron entry, that is a real and recurring failure.
                 Worth naming rather than leaving as an unexplained badge. --}}
            @if ($message->status === \App\Enums\Communication\CommunicationStatus::Queued && $message->queued_at->lt(now()->subMinutes(15)))
                <div class="text-warning fs-8 mt-1">
                    Still waiting after {{ $message->queued_at->diffForHumans(null, true) }} — the queue
                    worker may not be running.
                </div>
            @endif
        </div>

        @can('resend', $message)
            <button type="button" class="btn btn-sm btn-light-primary" data-action="resend-message"
                data-url="{{ route('admin.communications.resend', $message) }}">
                <i class="ki-outline ki-send fs-5"></i>
                Send again
            </button>
        @endcan
    </div>
@empty
    <div class="text-muted fs-8">No emails have been sent about this yet.</div>
@endforelse
