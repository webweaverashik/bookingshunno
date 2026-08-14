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
    $locked = !$reservation->isEditable();
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

@if ($locked)
    {{-- Not a disabled form with no explanation: the admin needs to know why,
         and that they can still leave a note. --}}
    <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-4 mb-5">
        <i class="ki-outline ki-information fs-2 text-warning me-3"></i>
        <div class="fs-7 text-gray-700">
            This reservation has reached <strong>{{ $reservation->status->label() }}</strong>, so the date,
            time, party size and price are locked — the visitor has been quoted a figure and changing it
            here would silently re-price what they were asked to pay. Notes can still be edited.
        </div>
    </div>
@else
    <div class="row g-5 mb-5">
        <div class="col-md-6">
            <label class="form-label required">Date</label>
            <input type="date" name="reserved_date" id="reservation-date" class="form-control form-control-solid"
                value="{{ $reservation->reserved_date->toDateString() }}">
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
@endif

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
