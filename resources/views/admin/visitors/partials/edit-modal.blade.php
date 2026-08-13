<div class="modal fade" id="visitor-edit-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-550px sh-modal-scroll">
        <div class="modal-content">

            <div class="modal-header">
                <h3 class="modal-title">Edit visitor</h3>
                <button type="button" class="btn btn-icon btn-sm btn-active-light-primary ms-2"
                    data-bs-dismiss="modal" aria-label="Close">
                    <i class="ki-outline ki-cross fs-1"></i>
                </button>
            </div>

            <form id="visitor-form" action="" novalidate>
                @csrf

                <div class="modal-body py-8 px-9">

                    <div class="mb-6">
                        <label class="required form-label">Name</label>
                        <input type="text" name="name" class="form-control form-control-solid" maxlength="120" />
                        <div class="invalid-feedback d-block" data-error-for="name"></div>
                    </div>

                    <div class="mb-6">
                        <label class="required form-label">Email</label>
                        <input type="email" name="email" class="form-control form-control-solid" maxlength="190" />
                        {{-- Not a cosmetic warning: resolveVisitor() matches on
                             email, so this decides which account future
                             reservations attach to. --}}
                        <div class="form-text">
                            Reservations are matched to a visitor by email. Changing it means future requests from
                            the old address will create a new visitor record.
                        </div>
                        <div class="invalid-feedback d-block" data-error-for="email"></div>
                    </div>

                    <div class="row g-5 mb-6">
                        <div class="col-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control form-control-solid" maxlength="20" />
                            <div class="invalid-feedback d-block" data-error-for="phone"></div>
                        </div>
                        <div class="col-6">
                            <label class="form-label">WhatsApp</label>
                            <input type="text" name="whatsapp" class="form-control form-control-solid" maxlength="20" />
                            <div class="invalid-feedback d-block" data-error-for="whatsapp"></div>
                        </div>
                    </div>

                    <label class="form-check form-switch form-check-custom form-check-solid">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" />
                        <span class="form-check-label fw-semibold">Account active</span>
                    </label>
                    <div class="form-text">
                        Deactivating prevents sign-in. It does not block reservation requests — see the Phase 8 notes.
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="visitor-save">
                        <span class="indicator-label">Save changes</span>
                        <span class="indicator-progress">Saving…
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>
