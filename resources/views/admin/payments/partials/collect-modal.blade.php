{{--
    Taking money at the counter.

    The gap this fills: the payments register lists payment REQUESTS, so once a
    booking fee settles there is no open request for that visit and no Record
    button anywhere — staff could see a reservation owing 500 taka and had no
    way to say it had been handed over. This starts from the reservation
    instead, and PaymentService::collect() decides whether that means recording
    against a live request or raising one for the balance first.

    The picker is Select2 in remote mode. A search rather than a list because
    the answer is one reservation out of however many the studio has open, and
    the person at the till knows the name or the reference. When nothing is
    outstanding at all, the notice replaces the form entirely rather than
    leaving an empty dropdown looking broken.

    SSLCommerz and Gift voucher are absent from the method list for the same
    reason as the Record modal: the first is written by the gateway after
    server-side verification, the second has to decrement a coupon, and letting
    staff assert either by hand would let the books claim something that never
    happened.
--}}

<div class="modal fade" id="payment-collect-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-600px sh-modal-scroll">
        <div class="modal-content">
            <form id="payment-collect-form" action="{{ route('admin.payments.collect') }}" novalidate>
                @csrf

                <div class="modal-header">
                    <div>
                        <h3 class="modal-title">Take a payment</h3>
                        <div class="text-muted fs-8 mt-1">Money handed over at the studio.</div>
                    </div>
                    <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal">
                        <i class="ki-outline ki-cross fs-1"></i>
                    </div>
                </div>

                <div class="modal-body">

                    {{-- Shown while the picker is loading its first page, and
                         replaced by either the form or the notice. --}}
                    <div class="text-center py-10" data-collect="loading">
                        <span class="spinner-border spinner-border-sm text-primary"></span>
                        <div class="text-muted fs-8 mt-2">Looking for reservations with a balance…</div>
                    </div>

                    {{-- The empty case. An answer, not a failure: it means every
                         approved and confirmed visit is paid up. --}}
                    <div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-4"
                        data-collect="notice" hidden>
                        <i class="ki-outline ki-information fs-2 text-primary me-3"></i>
                        <div class="fs-7 text-gray-700" data-collect="notice-text"></div>
                    </div>

                    <div data-collect="body" hidden>
                        <div class="mb-5">
                            <label class="required form-label">Which reservation?</label>
                            {{-- data-dropdown-parent is the modal, not the body:
                                 Select2 appends its dropdown to <body> by
                                 default, which puts it behind the modal
                                 backdrop and makes it unclickable. --}}
                            <select name="reservation_id" id="payment-collect-reservation"
                                class="form-select form-select-solid border" data-placeholder="Search name or reference">
                                <option></option>
                            </select>
                            <div class="invalid-feedback d-block" data-error-for="reservation_id"></div>
                        </div>

                        {{-- Filled by the server when a reservation is chosen —
                             see collect-summary.blade.php. --}}
                        <div class="mb-5" data-collect="summary" hidden></div>

                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="required form-label">Amount received (BDT)</label>
                                <input type="number" name="amount" id="payment-collect-amount"
                                    class="form-control form-control-solid border" min="1" step="0.01"
                                    inputmode="decimal" />
                                <div class="form-text">
                                    {{-- Prefilled with the balance and editable: part payment at the
                                         counter is a real thing, and a form that refused it would push
                                         staff into entering the wrong figure. --}}
                                    Prefilled with the outstanding amount. Less is fine — the rest stays owed.
                                </div>
                                <div class="invalid-feedback d-block" data-error-for="amount"></div>
                            </div>

                            <div class="col-md-6">
                                <label class="required form-label">How did it arrive?</label>
                                <select name="method" class="form-select form-select-solid border">
                                    <option value="">Choose…</option>
                                    @foreach (\App\Enums\Payment\PaymentMethod::manualOptions() as $method)
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
                                    class="form-control form-control-solid border"
                                    max="{{ now()->format('Y-m-d\TH:i') }}" />
                                <div class="form-text">Leave blank for now.</div>
                                <div class="invalid-feedback d-block" data-error-for="paid_at"></div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Note</label>
                                <textarea name="note" rows="2" class="form-control form-control-solid border" maxlength="500"
                                    placeholder="Balance paid at the studio on arrival"></textarea>
                                <div class="invalid-feedback d-block" data-error-for="note"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" data-collect="save" hidden>
                        <span class="indicator-label">Take it</span>
                        <span class="indicator-progress">
                            Working…
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
