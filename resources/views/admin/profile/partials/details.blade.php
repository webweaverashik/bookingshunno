{{--
    Your own details.

    THE CURRENT-PASSWORD FIELD APPEARS ONLY WHEN THE EMAIL IS CHANGED, revealed
    by profile.js watching the field against ProfileConfig.currentEmail.

    That is a convenience, not the check. UpdateProfileRequest re-compares the
    submitted address against the database and demands the password itself, so
    stripping the field out of the DOM achieves nothing.

    This is now the ONLY place the current password is asked for; the
    password-change form no longer wants it. Not an inconsistency. A password
    taken from you is recoverable, because the reset link goes to your address.
    An ADDRESS taken from you is not: the reset goes to the attacker and the
    account is gone. The check stays on the irreversible one.
--}}

<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title flex-column align-items-start">
            <h3 class="fw-bold m-0">Your details</h3>
            <span class="text-muted fs-7 mt-1">How the studio reaches you, and how you sign in.</span>
        </div>
    </div>

    <form class="form" id="profile-form">
        <div class="card-body pt-0">
            <div class="row g-5">

                <div class="col-md-6">
                    <label class="required form-label">Full name</label>
                    <input type="text" name="name" class="form-control form-control-solid border"
                        value="{{ $user->name }}" />
                    <div class="invalid-feedback d-block" data-error-for="name"></div>
                </div>

                <div class="col-md-6">
                    <label class="required form-label">Email address</label>
                    <input type="email" name="email" id="profile-email-input"
                        class="form-control form-control-solid border" value="{{ $user->email }}"
                        autocomplete="username" />
                    <div class="form-text">You sign in with this, and the login code is sent here.</div>
                    <div class="invalid-feedback d-block" data-error-for="email"></div>
                </div>

                <div class="col-md-6">
                    <label class="required form-label">Phone</label>
                    <input type="text" name="phone" class="form-control form-control-solid border"
                        value="{{ $user->phone }}" />
                    <div class="invalid-feedback d-block" data-error-for="phone"></div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">WhatsApp</label>
                    <input type="text" name="whatsapp" class="form-control form-control-solid border"
                        value="{{ $user->whatsapp }}" />
                    <div class="invalid-feedback d-block" data-error-for="whatsapp"></div>
                </div>

                <div class="col-12 d-none" id="profile-confirm-block">
                    <div class="alert alert-light-warning border border-warning d-flex align-items-start p-5 mb-4">
                        <i class="ki-outline ki-shield-tick fs-2hx text-warning me-4 mt-1"></i>
                        <div>
                            <h5 class="mb-1">You are changing the address you sign in with</h5>
                            <span class="fs-7">Confirm with your current password. From then on the login code
                                goes to the new address.</span>
                        </div>
                    </div>

                    <label class="required form-label">Current password</label>
                    <div class="position-relative">
                        <input type="password" name="current_password" id="profile-current-password"
                            class="form-control form-control-solid border pe-12" autocomplete="current-password" />
                        <button type="button"
                            class="btn btn-sm btn-icon position-absolute translate-middle-y top-50 end-0 me-2"
                            data-password-toggle="#profile-current-password" aria-label="Show password"
                            aria-pressed="false">
                            <i class="ki-outline ki-eye fs-2"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback d-block" data-error-for="current_password"></div>
                </div>

            </div>
        </div>

        <div class="card-footer d-flex justify-content-end py-4">
            <button type="submit" class="btn btn-primary" data-submit>
                <span class="indicator-label">Save details</span>
                <span class="indicator-progress">Saving…
                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
            </button>
        </div>
    </form>
</div>
