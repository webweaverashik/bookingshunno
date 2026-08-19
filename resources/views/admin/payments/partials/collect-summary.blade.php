{{--
    What the studio is about to take money for.

    Rendered server-side and swapped in whole by the browser when a reservation
    is chosen in the Take payment picker. Every figure on it is formatted here:
    the modal has an outstanding amount, a total and a paid-so-far on it, and
    none of those may be worked out in JavaScript.

    Deliberately shows the arithmetic rather than only the balance. Somebody at
    the till needs to be able to say "your visit is 1,000, you have paid 500,
    so that is 500 now" out loud, and a card showing only the answer makes them
    open another tab to check it.
--}}

<div class="bg-light-primary rounded p-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <div class="fw-bold text-gray-900">{{ $reservation->title() }}</div>
            <div class="text-muted fs-8">
                {{ $reservation->reference_code }}
                &middot; {{ $reservation->reserved_date->format('l, j F Y') }}
                &middot; {{ \Carbon\CarbonImmutable::createFromTimeString($reservation->start_time)->format('g:i A') }}
            </div>
            <div class="text-muted fs-8">
                {{ $reservation->user?->name }}
                @if ($reservation->user?->phone)
                    &middot; {{ $reservation->user->phone }}
                @endif
            </div>
        </div>

        <span class="badge badge-light-{{ $reservation->status->colour() }}">
            {{ $reservation->status->label() }}
        </span>
    </div>

    <div class="d-flex flex-wrap gap-6">
        <div>
            <div class="text-muted fs-8">Visit total</div>
            <div class="fw-bold text-gray-900">BDT {{ number_format($reservation->payableTotal()) }}</div>
        </div>
        <div>
            <div class="text-muted fs-8">Paid so far</div>
            <div class="fw-bold text-gray-900">BDT {{ number_format($reservation->amountPaid()) }}</div>
        </div>
        <div>
            <div class="text-muted fs-8">Outstanding</div>
            <div class="fw-bold text-danger">BDT {{ number_format($reservation->outstandingTotal()) }}</div>
        </div>
    </div>

    {{-- Which of the two paths in PaymentService::collect() this will take.
         Said out loud because it changes what the visitor receives: recording
         against a live request settles the link they already have, whereas a
         fresh one is raised silently and only the receipt goes out. --}}
    @if ($reservation->payments()->open()->exists())
        <div class="text-muted fs-8 mt-3">
            There is an open payment request for this visit. This will be recorded against it, and the
            link the visitor already has will settle.
        </div>
    @else
        <div class="text-muted fs-8 mt-3">
            No open request. One will be raised for the balance and settled straight away — no payment
            link is emailed, only the receipt.
        </div>
    @endif
</div>
