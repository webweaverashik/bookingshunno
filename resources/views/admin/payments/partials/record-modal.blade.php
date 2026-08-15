{{--
    Writing down money that arrived outside the gateway.

    This exists because the studio takes payment by hand and always will —
    bKash to a personal number, cash at the door — and Phase 13's gateway does
    not remove that. It is also what makes Phase 12 testable end to end before
    SSLCommerz is wired up at all.

    SSLCommerz and Gift voucher are deliberately absent from the method list.
    The first is written by the gateway callback after server-side verification,
    the second has to decrement a voucher; letting staff assert either by hand
    would let the books claim something that never happened.
--}}

<div class="modal fade" id="payment-record-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-550px sh-modal-scroll">
        <div class="modal-content">
            <form id="payment-record-form" action="">
                @csrf

                <div class="modal-header">
                    <h3 class="modal-title">Record a payment</h3>
                    <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal">
                        <i class="ki-outline ki-cross fs-1"></i>
                    </div>
                </div>

                <div class="modal-body">
                    <div class="fs-7 text-gray-700 mb-4">
                        <span id="payment-record-reference" class="fw-bold"></span>
                        &middot; outstanding
                        <span class="fw-bold">BDT <span id="payment-record-outstanding">0</span></span>
                    </div>

                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="required form-label">Amount received (BDT)</label>
                            <input type="number" name="amount" id="payment-record-amount"
                                class="form-control form-control-solid border" min="1" step="0.01"
                                inputmode="decimal" />
                            <div class="form-text">
                                {{-- Part payment is allowed on purpose: a deposit now and the balance
                                     on the day is a real thing the studio does, and a form that
                                     refused it would push staff into entering the wrong figure. --}}
                                Less than the full amount is fine — the request stays open for the rest.
                            </div>
                            <div class="invalid-feedback d-block" data-error-for="amount"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="required form-label">How did it arrive?</label>
                            <select name="method" class="form-select form-select-solid border">
                                <option value="">Choose…</option>
                                @foreach (\App\Enums\PaymentMethod::manualOptions() as $method)
                                    <option value="{{ $method->value }}">{{ $method->label() }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback d-block" data-error-for="method"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Transaction ID</label>
                            <input type="text" name="reference" class="form-control form-control-solid border"
                                maxlength="100" placeholder="bKash TrxID, cheque number…" />
                            <div class="invalid-feedback d-block" data-error-for="reference"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">When</label>
                            {{-- Backdating allowed, forward-dating not: money taken on Friday and
                                 entered on Monday should read as Friday. Enforced server-side too. --}}
                            <input type="datetime-local" name="paid_at"
                                class="form-control form-control-solid border" max="{{ now()->format('Y-m-d\TH:i') }}" />
                            <div class="form-text">Leave blank for now.</div>
                            <div class="invalid-feedback d-block" data-error-for="paid_at"></div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Note</label>
                            <textarea name="note" rows="2" class="form-control form-control-solid border"
                                maxlength="500" placeholder="Paid at the studio, receipt 214"></textarea>
                            <div class="invalid-feedback d-block" data-error-for="note"></div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="payment-record-save">
                        <span class="indicator-label">Record it</span>
                        <span class="indicator-progress">
                            Saving… <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Withdrawing a request. Separate modal rather than a Swal prompt: the reason
     lands in the reservation's permanent history and needs a real textarea with
     real validation feedback, not a one-line browser dialog. --}}
<div class="modal fade" id="payment-cancel-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-500px sh-modal-scroll">
        <div class="modal-content">
            <form id="payment-cancel-form" action="">
                @csrf

                <div class="modal-header">
                    <h3 class="modal-title">Withdraw this request?</h3>
                    <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal">
                        <i class="ki-outline ki-cross fs-1"></i>
                    </div>
                </div>

                <div class="modal-body">
                    <div class="fs-7 text-gray-700 mb-3">
                        <span id="payment-cancel-reference" class="fw-bold"></span> will be cancelled and the
                        reservation will go back to <strong>Approved</strong>, ready for a new request.
                    </div>

                    <label class="required form-label">Why?</label>
                    <textarea name="reason" id="payment-cancel-reason" rows="3"
                        class="form-control form-control-solid border" maxlength="500"
                        placeholder="Wrong amount — the group dropped to three"></textarea>
                    <div class="invalid-feedback d-block" data-error-for="reason"></div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Back</button>
                    <button type="submit" class="btn btn-danger" id="payment-cancel-save">
                        <span class="indicator-label">Withdraw</span>
                        <span class="indicator-progress">
                            Saving… <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
