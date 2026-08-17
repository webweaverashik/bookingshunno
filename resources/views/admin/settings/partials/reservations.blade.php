{{--
    The rules the public booking form obeys.

    Every one of these is enforced server-side in AvailabilityService and
    StoreReservationRequest. Nothing here is a hint to the browser.
--}}

<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title flex-column align-items-start">
            <h3 class="fw-bold m-0">Reservation rules</h3>
            <span class="text-muted fs-7 mt-1">What the public booking form will and will not accept.</span>
        </div>
    </div>

    <form class="form" data-settings-form action="{{ route('admin.settings.reservations') }}">
        <div class="card-body pt-0">
            <div class="row g-5">

                <div class="col-md-6">
                    <label class="required form-label">Largest group accepted online</label>
                    <input type="number" name="max_participants" min="1" max="100" step="1"
                        class="form-control form-control-solid border"
                        value="{{ $values['reservation.max_participants'] ?? 30 }}" />
                    <div class="form-text">Larger parties have to phone. The form refuses anything above this.</div>
                    <div class="invalid-feedback d-block" data-error-for="max_participants"></div>
                </div>

                <div class="col-md-6">
                    <label class="required form-label">Minimum notice (hours)</label>
                    <input type="number" name="min_lead_hours" min="0" max="336" step="1"
                        class="form-control form-control-solid border"
                        value="{{ $values['availability.min_lead_hours'] ?? 24 }}" />
                    <div class="form-text">How far in advance a visit must be requested. The calendar greys out
                        anything sooner.</div>
                    <div class="invalid-feedback d-block" data-error-for="min_lead_hours"></div>
                </div>

                <div class="col-md-6">
                    <label class="required form-label">Calendar opens this far ahead (days)</label>
                    <input type="number" name="max_advance_days" min="7" max="730" step="1"
                        class="form-control form-control-solid border"
                        value="{{ $values['availability.max_advance_days'] ?? 120 }}" />
                    <div class="invalid-feedback d-block" data-error-for="max_advance_days"></div>
                </div>

                <div class="col-md-6">
                    <label class="required form-label">Start times every</label>
                    {{-- Plain form-select, per the Phase 6 rule: five fixed
                         options with nothing to search. --}}
                    <select name="slot_step_minutes" class="form-select form-select-solid border">
                        @foreach ([10, 15, 20, 30, 60] as $step)
                            <option value="{{ $step }}"
                                @selected(($values['availability.slot_step_minutes'] ?? 30) == $step)>{{ $step }} minutes
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text">Only divisors of an hour, so generated slots land on the clock.</div>
                    <div class="invalid-feedback d-block" data-error-for="slot_step_minutes"></div>
                </div>

                {{--
                    THE ONE TO BE CAREFUL WITH.

                    Capacity enforcement ships OFF because every seeded workshop
                    still carries the placeholder maximum of 12 from Phase 4.
                    Turning this on before the client's real per-session numbers
                    are entered starts refusing genuine bookings, and the studio
                    would see it as the form being broken.
                --}}
                <div class="col-12">
                    <div class="form-check form-switch form-check-custom form-check-solid">
                        <input class="form-check-input" type="checkbox" name="enforce_capacity" value="1"
                            id="enforce_capacity" @checked($values['availability.enforce_capacity'] ?? false) />
                        <label class="form-check-label fw-semibold" for="enforce_capacity">
                            Enforce per-session capacity
                            <span class="d-block text-muted fs-7 fw-normal">
                                Leave off until every workshop has its real maximum set. The seeded value is a
                                placeholder of 12, and enforcing against it will refuse real bookings.
                            </span>
                        </label>
                    </div>
                </div>

            </div>
        </div>

        <div class="card-footer d-flex justify-content-end py-4">
            <button type="submit" class="btn btn-primary" data-submit>
                <span class="indicator-label">Save reservation rules</span>
                <span class="indicator-progress">Saving…
                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
            </button>
        </div>
    </form>
</div>
