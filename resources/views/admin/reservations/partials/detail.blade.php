{{--
    The full record, rendered server-side and dropped into the drawer. HTML
    rather than JSON so status badges, money and dates keep the same Blade
    formatting as every other screen.

    Decisions are deliberately only available here and not from a list row:
    approving a request without having read the notes, the party size and the
    visitor's history is exactly the mistake a one-click row button invites.
--}}

@php
    use App\Enums\ReservationStatus;

    $item = $reservation->items->first();
    $start = \Carbon\CarbonImmutable::createFromTimeString($reservation->start_time);
    $end = \Carbon\CarbonImmutable::createFromTimeString($reservation->end_time);

    $canOverride = auth()->user()->can('overrideAvailability', $reservation);
    $escalation = $reservation->statusHistory->firstWhere('to_status', ReservationStatus::Escalated);
@endphp

<div class="d-flex align-items-start flex-wrap gap-3 mb-6">
    <div>
        <div class="fs-3 fw-bold text-gray-900">{{ $reservation->reference_code }}</div>
        <div class="text-muted fs-7">
            Requested {{ $reservation->created_at->format('j M Y, g:i A') }}
            &middot; {{ $reservation->source?->label() ?? 'Web' }}
        </div>
    </div>
    <div class="ms-auto text-end">
        <span class="badge badge-light-{{ $reservation->status->colour() }} fs-7">
            {{ $reservation->status->label() }}
        </span>
        @if ($reservation->isMoneyLocked())
            <div class="text-muted fs-8 mt-1">Visit details locked</div>
        @endif
    </div>
</div>

{{-- PHASE 10A. An escalated request is waiting on a specific person's
     judgement, and the reason it was escalated is the most important thing on
     the screen for whoever opens it next. --}}
@if ($reservation->status === ReservationStatus::Escalated)
    <div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-4 mb-5">
        <i class="ki-outline ki-arrow-up-right fs-2 text-primary me-3"></i>
        <div class="fs-7 text-gray-700">
            <strong>Waiting on an Admin decision.</strong>
            @if ($escalation)
                <div class="mt-1">{{ $escalation->note }}</div>
                <div class="text-muted fs-8 mt-1">
                    Escalated by {{ $escalation->actorName() }},
                    {{ $escalation->created_at->diffForHumans() }}
                </div>
            @endif
        </div>
    </div>
@endif

{{-- Live availability verdict. Only shown while the request is undecided —
     re-checking a declined one tells nobody anything, and a confirmed one would
     flag its own seats as a conflict with itself. --}}
@if ($availability['checked'] && !$availability['ok'])
    <div class="notice d-flex bg-light-danger rounded border-danger border border-dashed p-4 mb-5">
        <i class="ki-outline ki-information-5 fs-2 text-danger me-3"></i>
        <div class="fs-7 text-gray-700">
            <strong>This slot is no longer available.</strong>
            {{ $availability['reason'] }}
            <div class="text-muted fs-8 mt-1">
                Move the booking with Edit, or
                @if ($canOverride)
                    approve it anyway if that is deliberate.
                @else
                    escalate it — an Admin can approve it regardless.
                @endif
            </div>
        </div>
    </div>
@elseif ($availability['checked'])
    <div class="d-flex align-items-center text-success fs-8 mb-5">
        <i class="ki-outline ki-check-circle fs-4 me-2"></i>
        The slot is still free for this party size.
    </div>
@endif

{{-- ================= Visit ================= --}}
<div class="border border-gray-300 border-dashed rounded p-4 mb-5">
    <div class="fw-bold text-gray-800 mb-3">The visit</div>

    <div class="row g-3 fs-7">
        <div class="col-sm-6">
            <span class="text-muted d-block fs-8">Session</span>
            <span class="text-gray-900 fw-semibold">{{ $item?->title_snapshot ?? 'Visit' }}</span>
            @if (!$item?->workshop)
                <span class="text-muted fs-8 d-block">This workshop has since been removed.</span>
            @endif
        </div>

        <div class="col-sm-6">
            <span class="text-muted d-block fs-8">Date</span>
            <span class="text-gray-900 fw-semibold">
                {{ $reservation->reserved_date->format('l, j F Y') }}
            </span>
        </div>

        <div class="col-sm-6">
            <span class="text-muted d-block fs-8">Time</span>
            <span class="text-gray-900 fw-semibold">
                {{ $start->format('g:i A') }} &ndash; {{ $end->format('g:i A') }}
                <span class="text-muted fw-normal">({{ $item?->duration_minutes }} min)</span>
            </span>
        </div>

        <div class="col-sm-6">
            <span class="text-muted d-block fs-8">Party size</span>
            <span class="text-gray-900 fw-semibold">{{ $reservation->participants }}</span>
        </div>
    </div>

    @if ($reservation->purposes->isNotEmpty())
        <div class="separator separator-dashed my-4"></div>
        <span class="text-muted d-block fs-8 mb-2">What brings them</span>
        @foreach ($reservation->purposes as $purpose)
            <span class="badge badge-light me-1 mb-1">{{ $purpose->name }}</span>
        @endforeach
    @endif

    @if ($reservation->special_requests)
        <div class="separator separator-dashed my-4"></div>
        <span class="text-muted d-block fs-8 mb-1">Notes from the visitor</span>
        <p class="text-gray-700 fs-7 mb-0">{{ $reservation->special_requests }}</p>
    @endif
</div>

{{-- ================= Visitor ================= --}}
<div class="border border-gray-300 border-dashed rounded p-4 mb-5">
    <div class="d-flex align-items-center">
        <div class="symbol symbol-45px me-3">
            <span class="symbol-label bg-light-primary text-primary fw-bold fs-3">
                {{ Str::upper(Str::substr($reservation->user?->name ?? '?', 0, 1)) }}
            </span>
        </div>
        <div class="flex-grow-1">
            <div class="fw-bold text-gray-900">{{ $reservation->user?->name ?? 'Unknown visitor' }}</div>
            <div class="text-muted fs-7">
                {{ $reservation->user?->email }}
                @if ($reservation->user?->phone)
                    &middot; {{ $reservation->user->phone }}
                @endif
            </div>
        </div>

        @if ($reservation->user)
            <div class="text-end">
                <span
                    class="badge {{ $reservation->user->total_reservations > 1 ? 'badge-light-success' : 'badge-light' }}">
                    {{ $reservation->user->total_reservations }}
                    {{ Str::plural('request', $reservation->user->total_reservations) }}
                </span>
                @can('visitors.view')
                    <a href="{{ route('admin.visitors.index', ['q' => $reservation->user->email]) }}"
                        class="d-block fs-8 mt-1">Open visitor</a>
                @endcan
            </div>
        @endif
    </div>
</div>

{{-- ================= Money ================= --}}
<div class="border border-gray-300 border-dashed rounded p-4 mb-5">
    <div class="fw-bold text-gray-800 mb-3">Money</div>

    <div class="d-flex justify-content-between fs-7 mb-1">
        <span class="text-muted">
            {{ $reservation->participants }} &times; BDT {{ number_format((float) ($item?->unit_price ?? 0)) }}
        </span>
        <span class="text-gray-800">BDT {{ number_format((float) $reservation->subtotal) }}</span>
    </div>

    @if ((float) $reservation->discount_amount > 0)
        <div class="d-flex justify-content-between fs-7 mb-1">
            <span class="text-muted">{{ $reservation->discount_reason ?: 'Discount' }}</span>
            <span class="text-success">&minus; BDT {{ number_format((float) $reservation->discount_amount) }}</span>
        </div>
    @endif

    <div class="separator separator-dashed my-3"></div>

    @if ($reservation->hasManualPrice())
        {{-- PHASE 10A. Both figures, always, and the gap between them. A single
             number here would make an agreed price indistinguishable from the
             price list — and would hide an override left stale by a later
             change to the party size, which amend() deliberately does not
             clear on the visitor's behalf. --}}
        <div class="d-flex justify-content-between fs-7 mb-1">
            <span class="text-muted">Calculated total</span>
            <span class="text-muted text-decoration-line-through">
                BDT {{ number_format($reservation->calculatedTotal()) }}
            </span>
        </div>

        <div class="d-flex justify-content-between align-items-start">
            <div>
                <span class="fw-bold text-gray-800 d-block">Agreed price</span>
                <span class="text-muted fs-8">{{ $reservation->total_override_reason }}</span>
            </div>
            <div class="text-end">
                <span class="fw-bold text-gray-900 fs-5 d-block">
                    BDT {{ number_format($reservation->payableTotal()) }}
                </span>
                @if (abs($reservation->manualPriceDelta()) >= 0.01)
                    <span class="fs-8 text-{{ $reservation->manualPriceDelta() < 0 ? 'success' : 'warning' }}">
                        {{ $reservation->manualPriceDelta() < 0 ? '&minus;' : '+' }}
                        BDT {{ number_format(abs($reservation->manualPriceDelta())) }}
                    </span>
                @endif
            </div>
        </div>
    @else
        <div class="d-flex justify-content-between">
            <span class="fw-bold text-gray-800">Reservation total</span>
            <span class="fw-bold text-gray-900 fs-5">
                BDT {{ number_format((float) $reservation->total_amount) }}
            </span>
        </div>
    @endif

    {{-- ================= Payments (Phase 12A) ================= --}}
    @php
        $latestPayment = $reservation->latestPayment();
        $paid = $reservation->amountPaid();
        $outstanding = $reservation->outstandingTotal();
    @endphp

    @if ($latestPayment)
        <div class="separator separator-dashed my-3"></div>

        <div class="d-flex justify-content-between fs-7 mb-1">
            <span class="text-muted">
                {{ $latestPayment->type->describe($latestPayment->percentage) }} requested
                <span class="badge badge-light-{{ $latestPayment->status->colour() }} fs-8 ms-1">
                    {{ $latestPayment->status->label() }}
                </span>
            </span>
            <span class="text-gray-800">BDT {{ number_format((float) $latestPayment->amount_due) }}</span>
        </div>

        <div class="d-flex justify-content-between fs-7 mb-1">
            <span class="text-muted">Received</span>
            <span class="{{ $paid > 0 ? 'text-success fw-semibold' : 'text-muted' }}">
                BDT {{ number_format($paid) }}
            </span>
        </div>

        <div class="d-flex justify-content-between fs-7">
            <span class="text-muted">Outstanding on the visit</span>
            <span class="{{ $outstanding > 0 ? 'text-gray-800 fw-semibold' : 'text-success fw-semibold' }}">
                BDT {{ number_format($outstanding) }}
            </span>
        </div>

        <div class="d-flex align-items-center flex-wrap gap-2 mt-3">
            <span class="text-muted fs-8">
                {{ $latestPayment->reference }}
                @if ($latestPayment->isOpen())
                    &middot;
                    <span class="{{ $latestPayment->isOverdue() ? 'text-danger fw-semibold' : '' }}">
                        due {{ $latestPayment->due_at->format('j M, g:i A') }}{{ $latestPayment->isOverdue() ? ' — overdue' : '' }}
                    </span>
                @endif
            </span>

            {{-- Recording and withdrawing live on the payments register, not
                 here. One screen owns the money actions, which keeps the audit
                 trail and the error messages in one place rather than
                 duplicated across two drawers that would drift. --}}
            @can('payments.view')
                <a class="fs-8 ms-auto"
                    href="{{ route('admin.payments.index', ['q' => $latestPayment->reference, 'status' => 'all']) }}">
                    Open in Payments
                </a>
            @endcan
        </div>
    @else
        <div class="text-muted fs-8 mt-3">Nothing has been requested or received yet.</div>
    @endif

    @can('requestPayment', $reservation)
        <div class="mt-4">
            <button type="button" class="btn btn-sm btn-primary" data-action="request-payment"
                data-url="{{ route('admin.payments.create', $reservation) }}">
                <i class="ki-outline ki-credit-cart fs-5"></i>
                Request payment
            </button>
            <div class="text-muted fs-8 mt-2">
                {{-- PHASE 12B replaces this once the visitor's payment page and its
                     emails exist. Better to say so than to leave an admin assuming
                     a link went out. --}}
                Creates the request and moves this to <strong>Payment requested</strong>. The visitor is
                not emailed yet — that arrives with the payment page in the next phase.
            </div>
        </div>
    @endcan
</div>

{{-- ================= History ================= --}}
<div class="fw-bold text-gray-800 mb-3">History</div>

<div class="timeline mb-5">
    @forelse ($reservation->statusHistory as $entry)
        <div class="d-flex align-items-start mb-4">
            <span class="bullet bullet-vertical bg-{{ $entry->to_status->colour() }} h-40px me-4 mt-1"></span>
            <div class="flex-grow-1">
                <div class="fs-7 fw-bold text-gray-900">
                    @if ($entry->from_status && $entry->from_status !== $entry->to_status)
                        {{ $entry->from_status->label() }} &rarr; {{ $entry->to_status->label() }}
                    @elseif ($entry->from_status)
                        Edited &middot; {{ $entry->to_status->label() }}
                    @else
                        {{ $entry->to_status->label() }}
                    @endif
                </div>

                @if ($entry->note)
                    <div class="fs-7 text-gray-700">{{ $entry->note }}</div>
                @endif

                <div class="fs-8 text-muted">
                    {{ $entry->actorName() }} &middot;
                    {{ $entry->created_at->format('j M Y, g:i A') }}
                </div>
            </div>
        </div>
    @empty
        <p class="text-muted fs-7">No history recorded.</p>
    @endforelse
</div>

{{-- ================= Decisions ================= --}}
@php
    /*
     | Every button is gated by the policy, which asks both "may this person"
     | and "does the lifecycle allow it from here". A Manager sees Escalate
     | where an Admin sees Approve, because approval now needs
     | reservations.approve and Manager no longer holds it.
     |
     | data-* carries what the shared decision modal needs. The JavaScript sets
     | text and a form action from these; it does not build markup.
     */
    $actions = collect([
        [
            'ability' => 'approve',
            'url' => route('admin.reservations.approve', $reservation),
            'label' => 'Approve',
            'icon' => 'check-circle',
            'class' => 'btn-success',
            'title' => 'Approve this request?',
            'prompt' => 'Anything worth recording? (optional)',
            'placeholder' => 'Confirmed the group size by phone',
            'confirm' => 'Approve',
            'required' => false,
            'override' => $canOverride && $availability['checked'] && !$availability['ok'],
        ],
        [
            'ability' => 'escalate',
            'url' => route('admin.reservations.escalate', $reservation),
            'label' => 'Escalate to Admin',
            'icon' => 'arrow-up-right',
            'class' => 'btn-primary',
            'title' => 'Send this to an Admin?',
            'prompt' => 'What does the Admin need to decide? They have not spoken to the visitor.',
            'placeholder' => 'Group of 14 wants the pottery session on a Friday — needs a price and a staffing call',
            'confirm' => 'Escalate',
            'required' => true,
            'override' => false,
        ],
        [
            'ability' => 'requestInfo',
            'url' => route('admin.reservations.request-info', $reservation),
            'label' => 'Request more information',
            'icon' => 'message-question',
            'class' => 'btn-light-warning',
            'title' => 'What do you need from the visitor?',
            'prompt' => 'This message is what they will be asked for.',
            'placeholder' => 'Could you confirm how many of the group are children?',
            'confirm' => 'Send request',
            'required' => true,
            'override' => false,
        ],
        [
            'ability' => 'returnToReview',
            'url' => route('admin.reservations.return-to-review', $reservation),
            'label' => 'Back to review queue',
            'icon' => 'arrow-circle-left',
            'class' => 'btn-light-primary',
            'title' => 'Put this back in the queue?',
            'prompt' => 'What changed? (optional)',
            'placeholder' => 'Visitor replied: four adults, no children',
            'confirm' => 'Return to queue',
            'required' => false,
            'override' => false,
        ],
        [
            'ability' => 'decline',
            'url' => route('admin.reservations.decline', $reservation),
            'label' => 'Decline',
            'icon' => 'cross-circle',
            'class' => 'btn-light-danger',
            'title' => 'Decline this request?',
            'prompt' => 'Reason. The visitor will be told this.',
            'placeholder' => 'The studio is closed that week for an exhibition install',
            'confirm' => 'Decline',
            'required' => true,
            'override' => false,
        ],
        [
            'ability' => 'cancel',
            'url' => route('admin.reservations.cancel', $reservation),
            'label' => 'Cancel',
            'icon' => 'trash',
            'class' => 'btn-light-danger',
            'title' => 'Cancel this reservation?',
            'prompt' => 'Why is it being cancelled?',
            'placeholder' => 'Visitor called to cancel',
            'confirm' => 'Cancel reservation',
            'required' => true,
            'override' => false,
        ],
    ])->filter(fn($action) => auth()->user()->can($action['ability'], $reservation));

    /*
     | PHASE 10B — who each VISIBLE decision writes to, derived rather than
     | stated.
     |
     | The paragraph below used to name approving, declining and cancelling
     | unconditionally. A Manager can now do none of the three, so it described
     | three buttons that were not on screen — which reads as a bug to the
     | person who cannot find them.
     |
     | Grouping earns its keep for an Admin too. "Back to review queue" emails
     | nobody, and that is worth knowing before you reach for it instead of
     | asking the visitor for information.
     */
    $audience = [
        'approve' => 'visitor',
        'decline' => 'visitor',
        'cancel' => 'visitor',
        'requestInfo' => 'visitor',
        'escalate' => 'staff',
        'returnToReview' => 'silent',
    ];

    $notify = $actions
        ->groupBy(fn($action) => $audience[$action['ability']] ?? 'silent')
        ->map(fn($group) => $group->pluck('label')->join(', '));
@endphp

@if ($actions->isNotEmpty())
    <div class="separator my-5"></div>

    <div class="fw-bold text-gray-800 mb-1">Decision</div>
    <div class="text-muted fs-8 mb-4">
        {{-- PHASE 11 replaced the "nothing is emailed yet" warning that stood
             here. Saying which decisions write to whom is worth the line: an
             admin about to type a decline reason should know the visitor is
             going to read it. PHASE 10B made it reflect what is on screen. --}}
        @if ($notify->has('visitor'))
            <span class="fw-semibold text-gray-700">Emails the visitor:</span> {{ $notify['visitor'] }}.
        @endif

        @if ($notify->has('staff'))
            <span class="fw-semibold text-gray-700">Emails the Admins:</span> {{ $notify['staff'] }}.
        @endif

        @if ($notify->has('silent'))
            <span class="fw-semibold text-gray-700">Tells nobody:</span> {{ $notify['silent'] }}.
        @endif

        @if ($notify->has('visitor') || $notify->has('staff'))
            Whatever you write below goes into that email.
        @else
            Nothing here sends an email.
        @endif
    </div>

    <div class="d-flex flex-wrap gap-2">
        @foreach ($actions as $action)
            <button type="button" class="btn btn-sm {{ $action['class'] }}" data-action="decide"
                data-url="{{ $action['url'] }}" data-title="{{ $action['title'] }}"
                data-prompt="{{ $action['prompt'] }}" data-placeholder="{{ $action['placeholder'] }}"
                data-confirm="{{ $action['confirm'] }}" data-required="{{ $action['required'] ? '1' : '0' }}"
                data-override="{{ $action['override'] ? '1' : '0' }}" data-tone="{{ $action['class'] }}">
                <i class="ki-outline ki-{{ $action['icon'] }} fs-5"></i>
                {{ $action['label'] }}
            </button>
        @endforeach
    </div>
@else
    <div class="separator my-5"></div>
    <div class="text-muted fs-7">
        @if ($reservation->status->allowedNext() === [])
            {{ $reservation->status->label() }} is a closed state — there is nothing further to decide here.
        @else
            You do not have the permissions to decide this one. An Admin does.
        @endif
    </div>
@endif
