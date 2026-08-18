{{--
    Creating and editing a gift voucher.

    Gift only. Café credit is issued by the system when a qualifying visit is
    paid for, and a form that could mint it by hand would decouple it from the
    visit that is meant to earn it — the unique (reservation_id, type)
    constraint would refuse most attempts anyway. The same rule keeps café
    credit out of the edit path: see Voucher::uneditableReason().

    ONE MODAL FOR BOTH. The fields are identical, the validation rules are
    identical bar the uniqueness ignore, and two modals would mean every future
    field had to be added twice — with the second copy discovered missing only
    when somebody edited a voucher and lost a value they had just typed.
    vouchers.js swaps the title, the action and the button label.
--}}

<div class="modal fade" id="voucher-form-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-600px sh-modal-scroll">
        <div class="modal-content">
            <form id="voucher-form" action="">
                @csrf

                <div class="modal-header">
                    <h3 class="modal-title" id="voucher-form-title">New gift voucher</h3>
                    <div class="btn btn-icon btn-sm btn-active-light-primary ms-2" data-bs-dismiss="modal">
                        <i class="ki-outline ki-cross fs-1"></i>
                    </div>
                </div>

                <div class="modal-body">
                    {{-- Shown on edit only, and only when the voucher went out by
                         email. Somebody is holding a copy of what this says, and
                         changing the code or the value behind them is a
                         different act from correcting a draft. --}}
                    <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-3 mb-4 d-none"
                        id="voucher-form-sent-warning">
                        <i class="ki-outline ki-information-5 fs-4 text-warning me-3"></i>
                        <div class="fs-8 text-gray-700">
                            This voucher was emailed when it was created. Changing the code or the value
                            will not reach the recipient — they still hold the old one.
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-12">
                            <label class="required form-label">Code</label>
                            <div class="d-flex gap-2">
                                {{-- Uppercased as it is typed, because that is how
                                     it will be printed, matched and read back. --}}
                                <input type="text" name="code"
                                    class="form-control form-control-solid border text-uppercase"
                                    maxlength="24" autocomplete="off" spellcheck="false"
                                    placeholder="EIDGIFT-2026" value="{{ $suggestedCode }}" />
                                <button type="button" class="btn btn-light flex-shrink-0" id="voucher-code-suggest"
                                    title="Use a generated code instead">
                                    <i class="ki-outline ki-arrows-circle fs-4"></i>
                                    Suggest
                                </button>
                            </div>
                            {{-- Filled by vouchers.js as the code is typed. The real
                                 uniqueness guard is the index on the column — this
                                 is here so nobody discovers a clash after filling
                                 in the rest of the form. --}}
                            <div class="form-text" id="voucher-code-feedback">
                                Letters, numbers and hyphens. 4–24 characters. Checked as you type.
                            </div>
                            <div class="invalid-feedback d-block" data-error-for="code"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="required form-label">Value (BDT)</label>
                            <input type="number" name="value" class="form-control form-control-solid border"
                                min="1" max="50000" step="1" inputmode="numeric" />
                            <div class="invalid-feedback d-block" data-error-for="value"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Expires</label>
                            {{-- Flatpickr, per the project convention for every date
                                 input. Optional: the client has not asked for a
                                 mandatory expiry, and inventing a default would
                                 quietly kill gifts meant to be open-ended. --}}
                            <input type="text" name="expires_at"
                                class="form-control form-control-solid border shunno-datepicker"
                                placeholder="No expiry" autocomplete="off" />
                            <div class="form-text">Leave blank for no expiry.</div>
                            <div class="invalid-feedback d-block" data-error-for="expires_at"></div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Only valid for</label>
                            {{-- Select2: a long, searchable list read at submit
                                 through FormData, which is exactly the case the
                                 Phase 6 rule reserves it for. --}}
                            <select name="workshop_id" class="form-select form-select-solid border"
                                data-control="select2" data-placeholder="Any experience" data-allow-clear="true">
                                <option></option>
                                @foreach ($workshops as $workshop)
                                    <option value="{{ $workshop->id }}">{{ $workshop->title }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Leave blank to let it be used against anything.</div>
                            <div class="invalid-feedback d-block" data-error-for="workshop_id"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Recipient name</label>
                            <input type="text" name="issued_to_name" class="form-control form-control-solid border"
                                maxlength="255" />
                            <div class="invalid-feedback d-block" data-error-for="issued_to_name"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Recipient email</label>
                            <input type="email" name="issued_to_email" class="form-control form-control-solid border"
                                maxlength="255" />
                            {{-- The email is what decides whether this is sent or
                                 written on a card, so the form says so rather than
                                 leaving staff to guess. --}}
                            <div class="form-text">Given an address, the voucher is emailed straight away.</div>
                            <div class="invalid-feedback d-block" data-error-for="issued_to_email"></div>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Note</label>
                            <textarea name="note" rows="2" class="form-control form-control-solid border"
                                maxlength="500" placeholder="Birthday gift from Rina"></textarea>
                            <div class="invalid-feedback d-block" data-error-for="note"></div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="voucher-form-save">
                        <span class="indicator-label" id="voucher-form-save-label">Create it</span>
                        <span class="indicator-progress">
                            Saving… <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
