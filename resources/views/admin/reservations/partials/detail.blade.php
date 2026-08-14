{{--
    The full record, rendered server-side and dropped into the drawer. HTML
    rather than JSON so status badges, money and dates keep the same Blade
    formatting as every other screen.
--}}

@php
    $item = $reservation->items->first();
    $start = \Carbon\CarbonImmutable::createFromTimeString($reservation->start_time);
    $end = \Carbon\CarbonImmutable::createFromTimeString($reservation->end_time);
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
                <span class="badge {{ $reservation->user->total_reservations > 1 ? 'badge-light-success' : 'badge-light' }}">
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

    <div class="d-flex justify-content-between">
        <span class="fw-bold text-gray-800">Reservation total</span>
        <span class="fw-bold text-gray-900 fs-5">BDT {{ number_format((float) $reservation->total_amount) }}</span>
    </div>

    {{-- Payment figures deliberately absent until Phase 12 builds them. An
         empty "Paid: BDT 0" row would read as a fact rather than a gap. --}}
    <div class="text-muted fs-8 mt-3">
        Nothing has been requested or received yet — payment handling arrives in a later phase.
    </div>
</div>

{{-- ================= History ================= --}}
<div class="fw-bold text-gray-800 mb-3">History</div>

<div class="timeline">
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
