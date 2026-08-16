{{--
    Redeeming and cancelling.

    Both are real modals rather than a browser confirm(). Redemption is single
    use and irreversible — spending a 300 taka coupon on a 180 taka order
    forfeits the rest — so it deserves a moment's pause and a place to record
    what it was spent on. Cancellation writes a reason that stays on the record.
--}}

<div class="modal fade" id="voucher-redeem-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-500px sh-modal-scroll">
        <div class="modal-content">
            <form id="voucher-redeem-form" action="">
                @csrf

                <div class="modal-header">
                    <h3 class="modal-title">Mark as used</h3>
                    <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal">
                        <i class="ki-outline ki-cross fs-1"></i>
                    </div>
                </div>

                <div class="modal-body">
                    <div class="fs-7 text-gray-700 mb-3">
                        <span id="voucher-redeem-code" class="fw-bold"></span>
                        &middot; BDT <span id="voucher-redeem-value" class="fw-bold"></span>
                    </div>

                    {{-- Said plainly, because it is the one thing staff most need
                         to know before clicking and the one thing that cannot be
                         undone afterwards. --}}
                    <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-3 mb-4">
                        <i class="ki-outline ki-information-5 fs-4 text-warning me-3"></i>
                        <div class="fs-8 text-gray-700">
                            This uses the whole voucher in one go. Anything not spent is lost, and this
                            cannot be reversed.
                        </div>
                    </div>

                    <label class="form-label">What was it spent on?</label>
                    <textarea name="note" id="voucher-redeem-note" rows="2"
                        class="form-control form-control-solid border" maxlength="500"
                        placeholder="Two coffees and a slice of cake"></textarea>
                    <div class="form-text">Optional, but it makes the record worth reading later.</div>
                    <div class="invalid-feedback d-block" data-error-for="note"></div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Back</button>
                    <button type="submit" class="btn btn-success" id="voucher-redeem-save">
                        <span class="indicator-label">Mark it used</span>
                        <span class="indicator-progress">
                            Saving… <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="voucher-cancel-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-500px sh-modal-scroll">
        <div class="modal-content">
            <form id="voucher-cancel-form" action="">
                @csrf

                <div class="modal-header">
                    <h3 class="modal-title">Cancel this voucher?</h3>
                    <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal">
                        <i class="ki-outline ki-cross fs-1"></i>
                    </div>
                </div>

                <div class="modal-body">
                    <div class="fs-7 text-gray-700 mb-3">
                        <span id="voucher-cancel-code" class="fw-bold"></span> will stop working
                        immediately. If somebody is holding it, they will be turned away.
                    </div>

                    <label class="required form-label">Why?</label>
                    <textarea name="reason" id="voucher-cancel-reason" rows="3"
                        class="form-control form-control-solid border" maxlength="500"
                        placeholder="Issued in error — wrong value"></textarea>
                    <div class="invalid-feedback d-block" data-error-for="reason"></div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Back</button>
                    <button type="submit" class="btn btn-danger" id="voucher-cancel-save">
                        <span class="indicator-label">Cancel it</span>
                        <span class="indicator-progress">
                            Saving… <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
