{{--
    One modal for all five decisions.

    The buttons in the drawer carry the wording in data-* attributes and the
    JavaScript sets the text and the form action from them. Five separate modals
    would be five places to change one layout, and a modal built in JavaScript
    would put markup in the browser, which this panel does not do anywhere else.

    The override switch is rendered but hidden by default; it is only revealed
    for an action whose button says it applies, which for now means approving a
    reservation whose slot is no longer available, for someone who holds
    availability.update.
--}}

<div class="modal fade" id="reservation-decision-modal" tabindex="-1" aria-hidden="true">
    {{-- sh-modal-scroll for the same reason as the others: this body is short
         today, but a long escalation note in a small viewport should scroll
         rather than push the Confirm button off screen. --}}
    <div class="modal-dialog modal-dialog-centered mw-550px sh-modal-scroll">
        <div class="modal-content">
            <form id="reservation-decision-form" action="">
                @csrf

                <div class="modal-header">
                    <h3 class="modal-title" id="reservation-decision-title">Are you sure?</h3>
                    <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal">
                        <i class="ki-outline ki-cross fs-1"></i>
                    </div>
                </div>

                <div class="modal-body">
                    <div class="fs-7 text-gray-700 mb-2" id="reservation-decision-prompt"></div>

                    <textarea name="note" id="reservation-decision-note" class="form-control form-control-solid" rows="4"
                        maxlength="1000"></textarea>
                    <div class="invalid-feedback d-block" data-error-for="note"></div>

                    <div class="mt-4" id="reservation-decision-override-wrap" hidden>
                        <label class="form-check form-switch form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="override" value="1"
                                id="reservation-decision-override" />
                            <span class="form-check-label fw-semibold text-gray-800">
                                Approve anyway, even though the slot is unavailable
                            </span>
                        </label>
                        <div class="form-text">Recorded in the history against your name.</div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Back</button>
                    <button type="submit" class="btn btn-primary" id="reservation-decision-save">
                        <span class="indicator-label">Confirm</span>
                        <span class="indicator-progress">
                            Saving… <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
