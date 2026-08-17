{{--
    SMTP.

    THE PASSWORD FIELD IS ALWAYS EMPTY, AND THAT IS CORRECT. The stored value is
    encrypted with APP_KEY and never sent to the browser — not masked, not
    echoed, not present in the HTML at all. Leaving it blank saves everything
    else and keeps the existing password; see SettingController::updateMail().

    The fields are pre-filled from the EFFECTIVE configuration rather than from
    the settings table, so on a fresh install they show what .env is actually
    using instead of blanks beside a working mailer.
--}}

<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title flex-column align-items-start">
            <h3 class="fw-bold m-0">Outgoing email</h3>
            <span class="text-muted fs-7 mt-1">
                Every approval, payment link and receipt goes through this. Send a test after changing anything.
            </span>
        </div>
    </div>

    <div class="card-body pt-0">

        @if ($mailFromEnv)
            <div class="alert alert-primary d-flex align-items-center p-5 mb-6">
                <i class="ki-outline ki-information-5 fs-2hx text-primary me-4"></i>
                <div>
                    <h5 class="mb-1">Currently reading from <code>.env</code></h5>
                    <span class="fs-7">
                        Nothing has been saved here yet, so these fields show what the server file is using.
                        Saving this form takes over — after that, <code>.env</code> becomes the fallback for
                        anything left blank.
                    </span>
                </div>
            </div>
        @endif

        @if ($mailer === 'log')
            {{-- Worth shouting about. MAIL_MAILER=log is the local development
                 setting; on a live server it means every email is written to a
                 file and nobody ever receives one, with no error anywhere. --}}
            <div class="alert alert-warning d-flex align-items-center p-5 mb-6">
                <i class="ki-outline ki-shield-cross fs-2hx text-warning me-4"></i>
                <div>
                    <h5 class="mb-1">Email is going to the log, not to anybody</h5>
                    <span class="fs-7">
                        The mailer is set to <code>log</code>, which writes messages to
                        <code>storage/logs</code> and sends nothing. Correct for local development. On the live
                        server it means visitors are receiving nothing at all — fill in a host below and save.
                    </span>
                </div>
            </div>
        @endif

        <form class="form" data-settings-form action="{{ route('admin.settings.mail') }}">
            <div class="row g-5">

                <div class="col-md-8">
                    <label class="required form-label">SMTP host</label>
                    <input type="text" name="host" class="form-control form-control-solid border"
                        placeholder="smtp.example.com" value="{{ $mail['host'] }}" />
                    <div class="invalid-feedback d-block" data-error-for="host"></div>
                </div>

                <div class="col-md-4">
                    <label class="required form-label">Port</label>
                    <input type="number" name="port" min="1" max="65535" step="1"
                        class="form-control form-control-solid border" value="{{ $mail['port'] }}" />
                    <div class="form-text">465 with SSL, 587 with TLS, usually.</div>
                    <div class="invalid-feedback d-block" data-error-for="port"></div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control form-control-solid border"
                        autocomplete="off" value="{{ $mail['username'] }}" />
                    <div class="invalid-feedback d-block" data-error-for="username"></div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Password</label>
                    {{-- autocomplete="new-password" so the browser does not fill
                         the admin's own saved password into the studio's mail
                         account field. --}}
                    <div class="position-relative">
                        <input type="password" name="password" id="smtp-password"
                            class="form-control form-control-solid border pe-12" autocomplete="new-password"
                            placeholder="{{ $mail['has_password'] ? 'Set — leave blank to keep it' : 'Not set' }}" />
                        <button type="button"
                            class="btn btn-sm btn-icon position-absolute translate-middle-y top-50 end-0 me-2"
                            data-password-toggle="#smtp-password" aria-label="Show password" aria-pressed="false">
                            <i class="ki-outline ki-eye fs-2"></i>
                        </button>
                    </div>
                    <div class="form-text">Stored encrypted, never shown again. Blank means unchanged.</div>
                    <div class="invalid-feedback d-block" data-error-for="password"></div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Encryption</label>
                    <select name="encryption" class="form-select form-select-solid border">
                        <option value="" @selected(!$mail['encryption'])>None</option>
                        <option value="tls" @selected(in_array($mail['encryption'], ['tls', 'smtp'], true))>TLS
                        </option>
                        <option value="ssl" @selected(in_array($mail['encryption'], ['ssl', 'smtps'], true))>SSL
                        </option>
                    </select>
                    <div class="invalid-feedback d-block" data-error-for="encryption"></div>
                </div>

                <div class="col-md-6">
                    <label class="required form-label">Send emails from</label>
                    <input type="email" name="from_address" class="form-control form-control-solid border"
                        value="{{ $mail['from_address'] }}" />
                    <div class="form-text">
                        Should be on the same domain as the host above, or messages land in spam.
                    </div>
                    <div class="invalid-feedback d-block" data-error-for="from_address"></div>
                </div>

                <div class="col-12">
                    <label class="required form-label">Sender name</label>
                    <input type="text" name="from_name" class="form-control form-control-solid border"
                        value="{{ $mail['from_name'] }}" />
                    <div class="form-text">What a visitor sees in their inbox before they open anything.</div>
                    <div class="invalid-feedback d-block" data-error-for="from_name"></div>
                </div>

            </div>

            <div class="d-flex justify-content-end pt-8">
                <button type="submit" class="btn btn-primary" data-submit>
                    <span class="indicator-label">Save mail settings</span>
                    <span class="indicator-progress">Saving…
                        <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                </button>
            </div>
        </form>

        <div class="separator separator-dashed my-6"></div>

        {{--
            THE TEST SEND — deliberately OUTSIDE the form above.

            Nesting a form inside another is invalid HTML and browsers silently
            unnest it, which would leave this input submitting with the settings
            payload and Save firing the test send.

            PHASE 19 — the recipient is typed rather than fixed to the signed-in
            Admin, at your request. The inbox you need to test against is usually
            a Gmail account or the client's, not the one you happen to be signed
            in as.

            THE MESSAGE BODY IS FIXED. Nothing typed here reaches it. That is
            what keeps a chooseable recipient from being an open relay — the
            address alone is worth very little without control of the text.
        --}}
        <div class="d-flex justify-content-between align-items-end flex-wrap gap-3">
            <div class="text-muted fs-8 flex-grow-1">
                Save first. The test sends through whatever is <strong>currently stored</strong>, not what is
                typed above — so an unsaved change will not be what you are testing.
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap">
                <input type="email" id="settings-test-email"
                    class="form-control form-control-solid border w-250px" placeholder="Send a test to…"
                    value="{{ auth()->user()->email }}" autocomplete="off" />
                <button type="button" class="btn btn-light-primary" id="settings-test-mail">
                    <i class="ki-outline ki-send fs-3"></i>
                    Send test
                </button>
            </div>
        </div>
    </div>
</div>
