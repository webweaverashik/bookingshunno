{{--
    Rendered server-side and dropped into the drawer. HTML rather than JSON so
    the status badges, money and dates keep using the same Blade formatting as
    every other screen instead of being re-implemented in JavaScript.
--}}

<div class="d-flex align-items-center mb-6">
    <div class="symbol symbol-60px me-4">
        <span class="symbol-label bg-light-primary text-primary fw-bold fs-2">
            {{ Str::upper(Str::substr($visitor->name, 0, 1)) }}
        </span>
    </div>
    <div>
        <div class="fs-4 fw-bold text-gray-900">{{ $visitor->name }}</div>
        <div class="text-muted fs-7">{{ $visitor->email }}</div>
        <div class="text-muted fs-7">
            {{ $visitor->phone ?: 'No phone' }}
            @if ($visitor->whatsapp && $visitor->whatsapp !== $visitor->phone)
                &middot; WhatsApp {{ $visitor->whatsapp }}
            @endif
        </div>
    </div>
    <div class="ms-auto">
        <span class="badge {{ $visitor->is_active ? 'badge-light-success' : 'badge-light-danger' }}">
            {{ $visitor->is_active ? 'Active' : 'Deactivated' }}
        </span>
    </div>
</div>

<div class="row g-3 mb-6">
    @foreach ([
        ['Requests', $visitor->total_reservations],
        ['Attended', $attended],
        ['Cancelled / no show', $cancelled],
        ['Lifetime value', 'BDT ' . number_format($lifetime)],
        ['Paid to date', 'BDT ' . number_format($paid)],
    ] as [$label, $value])
        <div class="col-6 col-md-4">
            <div class="border border-gray-300 border-dashed rounded p-3 text-center">
                <div class="fs-5 fw-bold text-gray-900">{{ $value }}</div>
                <div class="fs-8 text-muted">{{ $label }}</div>
            </div>
        </div>
    @endforeach
</div>

{{-- LIFETIME VALUE counts confirmed and completed reservations only: a pending
     request is not money, and treating it as such would overstate every visitor
     on the list.

     PAID TO DATE is a different figure and deliberately so — what has actually
     been received, across every reservation including cancelled ones. On a 50%
     booking fee the two differ by half, and the gap is the balance still due at
     the studio. Money taken against a booking later cancelled still shows here,
     because it is still money the studio is holding. --}}

<h4 class="fs-6 fw-bold text-gray-800 mb-3">Reservation history</h4>

@forelse ($visitor->reservations as $reservation)
    <div class="d-flex align-items-start border border-gray-300 border-dashed rounded p-4 mb-3">
        <div class="flex-grow-1">
            <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                <span class="fw-bold text-gray-900">
                    {{ $reservation->items->first()?->title_snapshot ?? 'Visit' }}
                </span>
                <span class="badge badge-light-{{ $reservation->status->colour() }}">
                    {{ $reservation->status->label() }}
                </span>
            </div>

            <div class="text-muted fs-7">
                {{ $reservation->reserved_date->format('D j M Y') }}
                at {{ \Carbon\CarbonImmutable::createFromTimeString($reservation->start_time)->format('g:i A') }}
                &middot; {{ $reservation->participants }}
                {{ Str::plural('person', $reservation->participants) }}
            </div>

            @if ($reservation->purposes->isNotEmpty())
                <div class="text-muted fs-8 mt-1">
                    {{ $reservation->purposes->pluck('name')->join(', ') }}
                </div>
            @endif

            @if ($reservation->special_requests)
                <div class="text-gray-600 fs-8 fst-italic mt-2">
                    “{{ Str::limit($reservation->special_requests, 160) }}”
                </div>
            @endif
        </div>

        <div class="text-end ms-4">
            <div class="fw-bold text-gray-900">BDT {{ number_format((float) $reservation->total_amount) }}</div>
            <div class="text-muted fs-8">{{ $reservation->reference_code }}</div>
        </div>
    </div>
@empty
    <p class="text-muted fs-7">No reservations yet.</p>
@endforelse
