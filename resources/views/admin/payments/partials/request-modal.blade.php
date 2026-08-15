{{--
    Asking the visitor for money. Opened from the reservation drawer.

    The two payment types and their figures come from the server in one payload
    — PaymentService::preview() — and switching between them swaps text that is
    already in the DOM. The browser does no arithmetic at all: a split
    percentage is a business rule, and §17 keeps those off the client. It also
    means the preview and the record eventually written cannot round differently.

    Note there is no amount field. The server derives the figure from the
    reservation and the type; a payload that could name its own amount would let
    anyone reaching this endpoint charge whatever they liked.
--}}

<div class="modal fade" id="payment-request-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-600px sh-modal-scroll">
        <div class="modal-content">
            <form id="payment-request-form" action="">
                @csrf

                <div class="modal-header">
                    <h3 class="modal-title">Request payment</h3>
                    <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal">
                        <i class="ki-outline ki-cross fs-1"></i>
                    </div>
                </div>

                <div class="modal-body">
                    <div class="fs-7 text-muted mb-4">
                        <span id="payment-request-visitor" class="fw-bold text-gray-800"></span>
                        &middot; <span id="payment-request-reference"></span>
                    </div>

                    {{-- Shown only when an Admin has agreed a figure that is not the
                         price-list one. The split is taken from the agreed total, and
                         somebody about to send a payment link should know that. --}}
                    <div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-3 mb-4"
                        id="payment-request-agreed" hidden>
                        <i class="ki-outline ki-information-5 fs-4 text-primary me-3"></i>
                        <div class="fs-8 text-gray-700">
                            This reservation has an agreed price. The figures below are based on it, not on
                            the price list.
                        </div>
                    </div>

                    <label class="required form-label">What are you asking for?</label>

                    <div class="row g-3 mb-4">
                        @foreach ([\App\Enums\PaymentType::BookingFee, \App\Enums\PaymentType::Full] as $type)
                            <div class="col-md-6">
                                <label class="btn btn-outline btn-outline-dashed btn-active-light-primary d-flex text-start p-4 w-100"
                                    data-payment-type-option="{{ $type->value }}">
                                    <input class="form-check-input me-3" type="radio" name="type"
                                        value="{{ $type->value }}" @checked($type === \App\Enums\PaymentType::BookingFee) />
                                    <span class="d-block">
                                        <span class="fw-bold text-gray-900 d-block"
                                            data-payment-type-label="{{ $type->value }}">
                                            {{ $type->label() }}
                                        </span>
                                        <span class="fw-bold fs-4 text-gray-900 d-block">
                                            BDT <span data-payment-type-payable="{{ $type->value }}">0</span>
                                        </span>
                                        <span class="text-muted fs-8 d-block"
                                            data-payment-type-remaining="{{ $type->value }}"></span>
                                    </span>
                                </label>
                            </div>
                        @endforeach
                    </div>

                    <div class="invalid-feedback d-block mb-3" data-error-for="type"></div>

                    <div class="separator separator-dashed my-4"></div>

                    <div class="d-flex justify-content-between fs-7 mb-1">
                        <span class="text-muted">Reservation total</span>
                        <span class="fw-semibold text-gray-900">
                            BDT <span id="payment-request-total">0</span>
                        </span>
                    </div>

                    <div class="row g-4 mt-1">
                        <div class="col-md-6">
                            <label class="form-label">Pay within (hours)</label>
                            <input type="number" name="deadline_hours" id="payment-request-hours"
                                class="form-control form-control-solid border" min="1" max="720" step="1"
                                inputmode="numeric" />
                            <div class="form-text">
                                Due <span id="payment-request-due">—</span>.
                                {{-- Only recomputed by the server. The hint below is the
                                     configured default; a changed number is confirmed when the
                                     request is actually created. --}}
                                Leave as-is to use the studio default.
                            </div>
                            <div class="invalid-feedback d-block" data-error-for="deadline_hours"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Note for the visitor</label>
                            <textarea name="note" rows="2" class="form-control form-control-solid border"
                                maxlength="500" placeholder="Optional"></textarea>
                            <div class="invalid-feedback d-block" data-error-for="note"></div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="payment-request-save">
                        <span class="indicator-label">Create the request</span>
                        <span class="indicator-progress">
                            Saving… <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
