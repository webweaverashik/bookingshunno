{{--
    Money rules.

    THE THING TO KNOW: these figures are read when a payment request is BUILT,
    not when it is settled. Changing the booking fee does not re-price anything
    already sent — every Payment row carries the percentage and the totals it
    was created with, which is deliberate, because a visitor holding a link that
    said 5,000 taka should not open it tomorrow and find 6,000.
--}}

<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title flex-column align-items-start">
            <h3 class="fw-bold m-0">Payments and vouchers</h3>
            <span class="text-muted fs-7 mt-1">Applies to new payment requests. Requests already sent keep their
                own figures.</span>
        </div>
    </div>

    <form class="form" data-settings-form action="{{ route('admin.settings.payments') }}">
        <div class="card-body pt-0">
            <div class="row g-5">

                <div class="col-md-6">
                    <label class="required form-label">Booking fee (%)</label>
                    <div class="input-group">
                        <input type="number" name="booking_fee_percentage" min="1" max="100" step="1"
                            class="form-control form-control-solid border"
                            value="{{ $values['booking_fee_percentage'] ?? 50 }}" />
                        <span class="input-group-text">%</span>
                    </div>
                    <div class="form-text">The deposit share when an admin chooses Booking fee instead of Full
                        payment.</div>
                    <div class="invalid-feedback d-block" data-error-for="booking_fee_percentage"></div>
                </div>

                <div class="col-md-6">
                    <label class="required form-label">Hours to pay after approval</label>
                    <input type="number" name="payment_deadline_hours" min="1" max="720" step="1"
                        class="form-control form-control-solid border"
                        value="{{ $values['payment_deadline_hours'] ?? 48 }}" />
                    <div class="form-text">Deadlines over 12 hours round to a civil closing hour, so nobody is asked
                        to pay at 3am.</div>
                    <div class="invalid-feedback d-block" data-error-for="payment_deadline_hours"></div>
                </div>

                <div class="col-md-6">
                    <label class="required form-label">Group discount applies from</label>
                    <input type="number" name="discount_min_participants" min="2" max="100" step="1"
                        class="form-control form-control-solid border"
                        value="{{ $values['group_discount.min_participants'] ?? 4 }}" />
                    <div class="form-text">Party size at which the discount starts.</div>
                    <div class="invalid-feedback d-block" data-error-for="discount_min_participants"></div>
                </div>

                <div class="col-md-6">
                    <label class="required form-label">Group discount (%)</label>
                    <div class="input-group">
                        <input type="number" name="discount_percentage" min="0" max="50" step="1"
                            class="form-control form-control-solid border"
                            value="{{ $values['group_discount.percentage'] ?? 10 }}" />
                        <span class="input-group-text">%</span>
                    </div>
                    <div class="invalid-feedback d-block" data-error-for="discount_percentage"></div>
                </div>

                <div class="col-md-6">
                    <label class="required form-label">Café credit valid for (days)</label>
                    <input type="number" name="cafe_credit_validity_days" min="1" max="365" step="1"
                        class="form-control form-control-solid border"
                        value="{{ $values['cafe_credit.validity_days'] ?? 30 }}" />
                    <div class="form-text">
                        Counted from the VISIT date, not from when the credit was issued — credit lands when payment
                        does, which can be weeks before the day.
                    </div>
                    <div class="invalid-feedback d-block" data-error-for="cafe_credit_validity_days"></div>
                </div>

                {{--
                    The operational switch, and a different thing entirely from
                    the gateway credentials on the next tab. This answers
                    "should we be offering online payment right now" — a question
                    the studio needs to answer during an outage without waiting
                    for a deploy. Off hides the Pay online button; payment
                    requests, deadlines and offline recording all carry on.
                --}}
                <div class="col-12">
                    <div class="form-check form-switch form-check-custom form-check-solid">
                        <input class="form-check-input" type="checkbox" name="online_enabled" value="1"
                            id="online_enabled" @checked($values['payments.online_enabled'] ?? true) />
                        <label class="form-check-label fw-semibold" for="online_enabled">
                            Accept payment online
                            <span class="d-block text-muted fs-7 fw-normal">
                                Off hides the Pay online button during a gateway outage. Vouchers and payments
                                recorded at the counter keep working.
                            </span>
                        </label>
                    </div>
                </div>

            </div>
        </div>

        <div class="card-footer d-flex justify-content-end py-4">
            <button type="submit" class="btn btn-primary" data-submit>
                <span class="indicator-label">Save payment rules</span>
                <span class="indicator-progress">Saving…
                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
            </button>
        </div>
    </form>
</div>
