{{--
    Changing your own password.

    PHASE 19 — the current-password field is gone, at your instruction. What
    that costs is recorded once, in the docblock on UpdatePasswordRequest,
    rather than repeated here.

    Saving still signs out every OTHER session. That is not a side effect to
    apologise for — somebody changing their password has usually just realised a
    session is open somewhere it should not be, and leaving those alive makes
    the change cosmetic.
--}}

<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title flex-column align-items-start">
            <h3 class="fw-bold m-0">Password</h3>
            <span class="text-muted fs-7 mt-1">Signing in also needs a code sent to your email, so this is one of
                two factors.</span>
        </div>
    </div>

    <form class="form" id="password-form">
        <div class="card-body pt-0">
            <div class="row g-5">

                <div class="col-md-6">
                    <label class="required form-label">New password</label>
                    {{--
                        The eye toggle is a real accessibility gain, not a
                        flourish: a masked field with no way to check it is the
                        main reason people pick short passwords they can type
                        blind. Delegated from Shunno.initPasswordToggles(), so
                        the button only has to say which field it points at.

                        aria-pressed is set by the handler, so a screen reader
                        announces the state rather than just "button".
                    --}}
                    <div class="position-relative">
                        <input type="password" name="password" id="new-password"
                            class="form-control form-control-solid border pe-12" autocomplete="new-password" />
                        <button type="button"
                            class="btn btn-sm btn-icon position-absolute translate-middle-y top-50 end-0 me-2"
                            data-password-toggle="#new-password" aria-label="Show password" aria-pressed="false">
                            <i class="ki-outline ki-eye fs-2"></i>
                        </button>
                    </div>
                    <div class="form-text">
                        At least 8 characters, and checked against known breached passwords. No
                        symbol-and-capital rules — length is what makes a password hard to guess.
                    </div>
                    <div class="invalid-feedback d-block" data-error-for="password"></div>
                </div>

                <div class="col-md-6">
                    <label class="required form-label">Repeat new password</label>
                    <div class="position-relative">
                        <input type="password" name="password_confirmation" id="new-password-confirm"
                            class="form-control form-control-solid border pe-12" autocomplete="new-password" />
                        <button type="button"
                            class="btn btn-sm btn-icon position-absolute translate-middle-y top-50 end-0 me-2"
                            data-password-toggle="#new-password-confirm" aria-label="Show password"
                            aria-pressed="false">
                            <i class="ki-outline ki-eye fs-2"></i>
                        </button>
                    </div>
                </div>

                <div class="col-12">
                    <div class="notice d-flex bg-light-primary rounded border-primary border border-dashed p-5">
                        <i class="ki-outline ki-information-5 fs-2tx text-primary me-4"></i>
                        <div class="fs-7">
                            Saving this signs you out everywhere else — other browsers, other devices. This one
                            stays open.
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="card-footer d-flex justify-content-end py-4">
            <button type="submit" class="btn btn-primary" data-submit>
                <span class="indicator-label">Change password</span>
                <span class="indicator-progress">Saving…
                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
            </button>
        </div>
    </form>
</div>
