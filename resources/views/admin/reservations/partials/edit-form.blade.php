{{--
    The edit form body, rendered whole and dropped into the modal.

    Why the whole body and not Shunno.fill(): the time select's options depend
    on the date, on the session's length and on the availability rules. Building
    them in JavaScript would put slot logic in the browser, which Phase 7A
    deliberately took out of it.

    PHASE 10A — Manager keeps the date, time and party size; only an Admin sees
    the price field. That split is the escalation flow working as intended: a
    Manager prepares the request and hands the decision up.
--}}

@php
    // Phase 27 split these apart. isEditable() is now "the visit may still be
    // corrected", true until the reservation closes; isMoneyLocked() is "a
    // figure has been quoted or taken". They used to be one flag meaning both.
    $locked = !$reservation->isEditable();
    $priced = $reservation->isMoneyLocked();
    $item = $reservation->items->first();
@endphp

<div class="mb-5">
    <div class="d-flex align-items-center flex-wrap gap-2">
        <span class="fw-bold text-gray-900">{{ $reservation->reference_code }}</span>
        <span class="badge badge-light-{{ $reservation->status->colour() }}">
            {{ $reservation->status->label() }}
        </span>
    </div>
    <div class="text-muted fs-7">
        {{ $reservation->user?->name }} &middot; {{ $item?->title_snapshot ?? 'Visit' }}
    </div>
</div>

@if ($priced)
    {{-- The visit is still correctable here; the price is not. The studio does
         change confirmed bookings — somebody rings up, two of the six cannot
         come — and refusing to record that does not stop it happening, it just
         means the register stops describing the visit that will take place.

         What it CANNOT do quietly is move money, which is what this says. --}}
    <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-4 mb-5">
        <i class="ki-outline ki-information fs-2 text-warning me-3"></i>
        <div class="fs-7 text-gray-700">
            This reservation has reached <strong>{{ $reservation->status->label() }}</strong>. You can still
            correct the date, time and party size, and the change is written into the history — but changing
            the party size re-prices the visit, and the payment request already sent will not update itself.
            Check the balance in Payments afterwards.
        </div>
    </div>
@endif

@unless ($locked)
    <div class="row g-5 mb-5">
        <div class="col-md-6">
            <label class="form-label required">Date</label>
            {{-- Flatpickr, like every other date field in the panel. Submits
                 Y-m-d and shows "14 Aug 2026"; it fires a normal change event
                 on this input, so the slot reload below still hears it.

                 CLOSED DAYS ARE GREYED OUT. Sunday, and any date blocked for
                 the whole day, cannot be picked — the same rule the public
                 calendar follows, which the admin form was ignoring.

                 Two deliberate exceptions:

                 The reservation's OWN date stays selectable whatever the lists
                 say, via data-allow-date. A booking may sit on a day closed
                 since it was made, and refusing it would stop somebody
                 correcting the party size without also moving the visit.

                 Somebody who can override availability gets an unrestricted
                 calendar. The studio does open a closed day by arrangement, and
                 that person is exactly who is allowed to say so — the override
                 checkbox further down is what records it. For everyone else the
                 server refuses these dates anyway; this only stops the round
                 trip. --}}
            <input type="text" name="reserved_date" id="reservation-date"
                class="form-control form-control-solid shunno-datepicker"
                value="{{ $reservation->reserved_date->toDateString() }}"
                @unless ($canOverride)
                    @foreach (\App\Support\Availability\Closures::pickerAttributes($reservation->reserved_date->toDateString()) as $attribute => $value)
                        {{ $attribute }}="{{ $value }}"
                    @endforeach
                @endunless>
            <div class="invalid-feedback d-block" data-error-for="reserved_date"></div>
        </div>

        <div class="col-md-6">
            <label class="form-label required">Start time</label>
            <select name="start_time" id="reservation-time" class="form-select form-select-solid">
                @include('admin.reservations.partials.slot-options', [
                    'slots' => $slots,
                    'selected' => substr((string) $reservation->start_time, 0, 5),
                ])
            </select>
            <div class="form-text">Unavailable times are shown with the reason, and can still be chosen with an
                override.</div>
            <div class="invalid-feedback d-block" data-error-for="start_time"></div>
        </div>

        <div class="col-md-6">
            <label class="form-label required">Participants</label>
            <input type="number" name="participants" class="form-control form-control-solid" min="1"
                value="{{ $reservation->participants }}">
            <div class="form-text">Changing this re-prices the reservation at the rate it was booked at.</div>
            <div class="invalid-feedback d-block" data-error-for="participants"></div>
        </div>
    </div>
@endunless

@if (!$locked && $canSetPrice)
    {{-- PHASE 10A. Admin only, via reservations.discount-override. The
         calculated figure stays on screen beside it so an agreed price is
         always readable as a deliberate departure from the price list rather
         than as the price list. --}}
    <div class="border border-gray-300 border-dashed rounded p-4 mb-5">
        <div class="fw-bold text-gray-800 mb-1">Agreed price</div>
        <div class="text-muted fs-8 mb-4">
            Leave blank to charge the calculated total of
            <strong>BDT {{ number_format($reservation->calculatedTotal()) }}</strong>.
            Anything entered here overrides it, including 0 for a complimentary visit.
        </div>

        <div class="row g-5">
            <div class="col-md-5">
                <label class="form-label">Total to charge (BDT)</label>
                <input type="number" name="total_override" class="form-control form-control-solid" min="0"
                    step="0.01" placeholder="{{ number_format($reservation->calculatedTotal(), 2, '.', '') }}"
                    value="{{ $reservation->total_override !== null ? number_format((float) $reservation->total_override, 2, '.', '') : '' }}">
                <div class="invalid-feedback d-block" data-error-for="total_override"></div>
            </div>

            <div class="col-md-7">
                <label class="form-label">Why</label>
                <input type="text" name="total_override_reason" class="form-control form-control-solid"
                    maxlength="255" placeholder="Partner school rate agreed with the studio"
                    value="{{ $reservation->total_override_reason }}">
                <div class="invalid-feedback d-block" data-error-for="total_override_reason"></div>
            </div>
        </div>

        @if ($reservation->hasManualPrice())
            <div class="text-muted fs-8 mt-3">
                Currently {{ $reservation->manualPriceDelta() < 0 ? 'below' : 'above' }} the calculated total by
                BDT {{ number_format(abs($reservation->manualPriceDelta())) }}. Clear the field to go back to
                the price list.
            </div>
        @endif
    </div>
@endif

<div class="mb-5">
    <label class="form-label">Notes from the visitor</label>
    <textarea name="special_requests" class="form-control form-control-solid" rows="3"
        maxlength="1000">{{ $reservation->special_requests }}</textarea>
    <div class="invalid-feedback d-block" data-error-for="special_requests"></div>
</div>

<div class="mb-5">
    <label class="form-label">Why are you changing this?</label>
    <input type="text" name="note" class="form-control form-control-solid" maxlength="500"
        placeholder="Visitor called to move the date">
    <div class="form-text">Appended to the history so the next person to open this record knows.</div>
    <div class="invalid-feedback d-block" data-error-for="note"></div>
</div>

@if (!$locked && $canOverride)
    {{-- Admin only. The studio genuinely needs this — someone rings up and the
         owner agrees to open a blocked hour — but it overrides the rule the rest
         of the system trusts, so every use is written into the history. --}}
    <label class="form-check form-switch form-check-custom form-check-solid">
        <input class="form-check-input" type="checkbox" name="override" value="1" />
        <span class="form-check-label fw-semibold text-gray-800">
            Save anyway, even if the slot is unavailable
        </span>
    </label>
    <div class="form-text">Recorded in the history. Does not raise the session's maximum party size.</div>
@endif
