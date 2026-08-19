{{--
    Pushed to the layout's @stack('modals'), outside #kt_app_root — Metronic's
    wrapper transforms break position:fixed for anything rendered inside it.
--}}
<div class="modal fade" id="block-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-550px sh-modal-scroll">
        <div class="modal-content">

            <div class="modal-header">
                <h3 class="modal-title" id="block-modal-title">Block a date</h3>
                <button type="button" class="btn btn-icon btn-sm btn-active-light-primary ms-2"
                    data-bs-dismiss="modal" aria-label="Close">
                    <i class="ki-outline ki-cross fs-1"></i>
                </button>
            </div>

            <form id="block-form" action="{{ route('admin.availability.blocked.store') }}" novalidate>
                @csrf
                {{-- Set by the JS only after the admin confirms a clash with
                     existing reservations. Reset on every open. --}}
                <input type="hidden" name="acknowledge" value="0">

                <div class="modal-body py-8 px-9">

                    <div class="mb-6">
                        <label class="required form-label">Date</label>
                        {{-- Flatpickr, like every other date field in the panel.
                             Submits Y-m-d, shows "14 Aug 2026". data-min-date
                             stops somebody closing a day that has already been
                             and gone — the server refuses it anyway, but the
                             picker should not offer it. --}}
                        <input type="text" name="date" class="form-control form-control-solid shunno-datepicker"
                            data-min-date="{{ now()->toDateString() }}" placeholder="Choose a date" />
                        <div class="invalid-feedback d-block" data-error-for="date"></div>
                    </div>

                    <div class="mb-6">
                        <label class="form-check form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="is_full_day" value="1"
                                id="block-full-day" checked />
                            <span class="form-check-label fw-semibold">Closed all day</span>
                        </label>
                        <div class="form-text">Untick to block only part of the evening.</div>
                    </div>

                    <div class="row g-5 mb-6" id="block-times" hidden>
                        <div class="col-6">
                            <label class="required form-label">From</label>
                            {{-- Half-hour steps, matching the slot grid. A
                                 native time input happily accepts 18:07 and
                                 leaves the server to reject it afterwards. --}}
                            <input type="text" name="starts_at"
                                class="form-control form-control-solid shunno-timepicker" placeholder="From" />
                            <div class="invalid-feedback d-block" data-error-for="starts_at"></div>
                        </div>
                        <div class="col-6">
                            <label class="required form-label">Until</label>
                            <input type="text" name="ends_at"
                                class="form-control form-control-solid shunno-timepicker" placeholder="Until" />
                            <div class="invalid-feedback d-block" data-error-for="ends_at"></div>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Reason</label>
                        <input type="text" name="reason" class="form-control form-control-solid" maxlength="190"
                            placeholder="Private hire, holiday, installation…" />
                        <div class="form-text">Shown to visitors on the reservation form, so keep it public-facing.</div>
                        <div class="invalid-feedback d-block" data-error-for="reason"></div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="block-save">
                        <span class="indicator-label">Save</span>
                        <span class="indicator-progress">Saving…
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>
