{{--
    One modal for both create and edit.

    Two would mean two copies of eight fields that must stay identical, and the
    only real differences — the title, the endpoint, whether the password is
    required — are three lines that users.js sets when it opens.

    The role select is NOT a Select2: two options, driving nothing that needs a
    search box.
--}}

<div class="modal fade" id="user-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered mw-650px">
        <div class="modal-content">

            <div class="modal-header">
                <h2 class="fw-bold" id="user-modal-title">Add staff</h2>
                <button type="button" class="btn btn-icon btn-sm btn-active-icon-primary" data-bs-dismiss="modal">
                    <i class="ki-outline ki-cross fs-1"></i>
                </button>
            </div>

            <form class="form" id="user-form">
                <div class="modal-body py-8 px-lg-12">
                    <div class="row g-5">

                        <div class="col-md-6">
                            <label class="required form-label">Full name</label>
                            <input type="text" name="name" class="form-control form-control-solid border" />
                            <div class="invalid-feedback d-block" data-error-for="name"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="required form-label">Email</label>
                            <input type="email" name="email" class="form-control form-control-solid border"
                                autocomplete="off" />
                            <div class="form-text">This is what they sign in with.</div>
                            <div class="invalid-feedback d-block" data-error-for="email"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control form-control-solid border" />
                            <div class="invalid-feedback d-block" data-error-for="phone"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">WhatsApp</label>
                            <input type="text" name="whatsapp" class="form-control form-control-solid border" />
                            <div class="invalid-feedback d-block" data-error-for="whatsapp"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="required form-label">Role</label>
                            <select name="role" class="form-select form-select-solid border">
                                <option value="Manager">Manager</option>
                                <option value="Admin">Admin</option>
                            </select>
                            <div class="form-text" id="user-role-hint">
                                Managers run the floor: they record payments and redeem vouchers, but cannot
                                approve, decline or reach settings.
                            </div>
                            <div class="invalid-feedback d-block" data-error-for="role"></div>
                        </div>

                        <div class="col-md-6 d-flex align-items-center">
                            <div class="form-check form-switch form-check-custom form-check-solid mt-6">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                    id="user-active" checked />
                                <label class="form-check-label fw-semibold" for="user-active">
                                    Can sign in
                                </label>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="separator separator-dashed my-2"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" id="user-password-label">Password</label>
                            <div class="position-relative">
                                <input type="password" name="password" id="user-password"
                                    class="form-control form-control-solid border pe-12" autocomplete="new-password" />
                                <button type="button"
                                    class="btn btn-sm btn-icon position-absolute translate-middle-y top-50 end-0 me-2"
                                    data-password-toggle="#user-password" aria-label="Show password"
                                    aria-pressed="false">
                                    <i class="ki-outline ki-eye fs-2"></i>
                                </button>
                            </div>
                            <div class="form-text" id="user-password-hint">
                                At least 8 characters, checked against known breached passwords.
                            </div>
                            <div class="invalid-feedback d-block" data-error-for="password"></div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Repeat password</label>
                            <div class="position-relative">
                                <input type="password" name="password_confirmation" id="user-password-confirm"
                                    class="form-control form-control-solid border pe-12" autocomplete="new-password" />
                                <button type="button"
                                    class="btn btn-sm btn-icon position-absolute translate-middle-y top-50 end-0 me-2"
                                    data-password-toggle="#user-password-confirm" aria-label="Show password"
                                    aria-pressed="false">
                                    <i class="ki-outline ki-eye fs-2"></i>
                                </button>
                            </div>
                        </div>

                        {{--
                            Said plainly, because it is the part people get
                            wrong. Nothing emails this password to anybody: there
                            is no invitation flow, and putting a working
                            credential in an inbox is how it ends up in a chat
                            thread forever.
                        --}}
                        <div class="col-12">
                            <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-4">
                                <i class="ki-outline ki-information-5 fs-2tx text-warning me-4"></i>
                                <div class="fs-8">
                                    The password is <strong>not emailed</strong>. Give it to them in person or over
                                    a channel you trust, and ask them to change it from their own profile page.
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" data-submit>
                        <span class="indicator-label" id="user-submit-label">Create account</span>
                        <span class="indicator-progress">Saving…
                            <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>
