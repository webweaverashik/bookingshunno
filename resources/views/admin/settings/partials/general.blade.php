{{--
    Studio identity and contact details.

    These are read by the public footer, the reservation page and every email
    template, which is why they were moved out of config/shunno.php: correcting
    a phone number should not need a deploy.
--}}

<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title flex-column align-items-start">
            <h3 class="fw-bold m-0">Studio details</h3>
            <span class="text-muted fs-7 mt-1">Shown on the public site and in every email footer.</span>
        </div>
    </div>

    <form class="form" data-settings-form action="{{ route('admin.settings.general') }}">
        <div class="card-body pt-0">
            <div class="row g-5">

                <div class="col-md-6">
                    <label class="required form-label">Studio name</label>
                    <input type="text" name="studio_name" class="form-control form-control-solid border"
                        value="{{ $values['studio.name'] ?? config('app.name') }}" />
                    <div class="invalid-feedback d-block" data-error-for="studio_name"></div>
                </div>

                <div class="col-md-6">
                    <label class="required form-label">Contact email</label>
                    <input type="email" name="contact_email" class="form-control form-control-solid border"
                        value="{{ $values['contact.email'] ?? config('shunno.contact.email') }}" />
                    <div class="form-text">Where staff notifications go, and what visitors are told to reply to.</div>
                    <div class="invalid-feedback d-block" data-error-for="contact_email"></div>
                </div>

                <div class="col-md-6">
                    <label class="required form-label">Phone</label>
                    <input type="text" name="contact_phone" class="form-control form-control-solid border"
                        value="{{ $values['contact.phone'] ?? config('shunno.contact.phone') }}" />
                    <div class="invalid-feedback d-block" data-error-for="contact_phone"></div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">WhatsApp</label>
                    <input type="text" name="contact_whatsapp" class="form-control form-control-solid border"
                        value="{{ $values['contact.whatsapp'] ?? config('shunno.contact.whatsapp') }}" />
                    <div class="form-text">Digits only, with the country code and no plus — that is what wa.me links need.</div>
                    <div class="invalid-feedback d-block" data-error-for="contact_whatsapp"></div>
                </div>

                <div class="col-12">
                    <label class="required form-label">Address</label>
                    <input type="text" name="contact_address" class="form-control form-control-solid border"
                        value="{{ $values['contact.address'] ?? config('shunno.contact.address') }}" />
                    <div class="invalid-feedback d-block" data-error-for="contact_address"></div>
                </div>

                {{--
                    The master switch for outbound reservation email.

                    Ships ON, because a booking system that silently tells nobody
                    anything is worse than one that occasionally sends a clumsy
                    email. Turn it off for a production database restored into
                    staging, where sending is actively harmful — it silences
                    EVERY reservation email, including the ones to staff.
                --}}
                <div class="col-12">
                    <div class="form-check form-switch form-check-custom form-check-solid">
                        <input class="form-check-input" type="checkbox" name="notifications_enabled" value="1"
                            id="notifications_enabled" @checked($values['notifications.enabled'] ?? true) />
                        <label class="form-check-label fw-semibold" for="notifications_enabled">
                            Send reservation emails
                            <span class="d-block text-muted fs-7 fw-normal">
                                Off silences every outbound reservation email, staff copies included. Use it on a
                                staging copy of live data.
                            </span>
                        </label>
                    </div>
                </div>

            </div>
        </div>

        <div class="card-footer d-flex justify-content-end py-4">
            <button type="submit" class="btn btn-primary" data-submit>
                <span class="indicator-label">Save studio details</span>
                <span class="indicator-progress">Saving…
                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
            </button>
        </div>
    </form>
</div>
