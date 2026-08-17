{{--
    SSLCommerz.

    PHASE 19 — this was a read-only panel. It is a form now: credentials moved
    out of .env and into the settings table, both stores, at your instruction.

    What is kept from the old arrangement:

      BOTH STORE PASSWORDS ARE ENCRYPTED with APP_KEY before they are written,
      and neither is ever sent back to this page. The fields render empty and an
      empty submission means unchanged — same rule as the SMTP password, and for
      the same reason: treating empty as "clear it" would mean correcting a
      store ID silently destroys the password and stops every online payment.

      SANDBOX AND LIVE ARE SEPARATE ROWS. They are different stores with
      different credentials, and keeping both means switching between them is a
      dropdown rather than a retype — which is what makes it safe to test.

    What is given up: the mode used to follow the environment, so nobody could
    put production into sandbox in two clicks. It is a setting now.
    GatewaySettingsRequest refuses a mode whose credentials are missing, and the
    banner below is the rest of the answer.
--}}

<div class="card mb-6">
    <div class="card-header border-0 pt-6">
        <div class="card-title flex-column align-items-start">
            <h3 class="fw-bold m-0">SSLCommerz</h3>
            <span class="text-muted fs-7 mt-1">Both stores are kept. The mode below decides which one
                transacts.</span>
        </div>
        <div class="card-toolbar">
            @if ($gateway['sandbox'])
                <span class="badge badge-light-warning fs-7 py-2 px-3">Sandbox mode</span>
            @else
                <span class="badge badge-light-success fs-7 py-2 px-3">Live mode</span>
            @endif
        </div>
    </div>

    <form class="form" data-settings-form data-confirm-live action="{{ route('admin.settings.gateway') }}">
        <div class="card-body pt-0">

            @unless ($gateway['configured'])
                <div class="alert alert-danger d-flex align-items-center p-5 mb-6">
                    <i class="ki-outline ki-shield-cross fs-2hx text-danger me-4"></i>
                    <div>
                        <h5 class="mb-1">Not usable yet</h5>
                        <span class="fs-7">
                            The <strong>{{ $gateway['mode'] }}</strong> store is missing an ID, a password, or
                            both. Online payment cannot work until it has both — payment requests, vouchers and
                            payments recorded at the counter all still do.
                        </span>
                    </div>
                </div>
            @endunless

            @if (!$gateway['sandbox'] && $gateway['configured'])
                <div class="alert alert-warning d-flex align-items-center p-5 mb-6">
                    <i class="ki-outline ki-information-5 fs-2hx text-warning me-4"></i>
                    <div>
                        <h5 class="mb-1">Live credentials are in use</h5>
                        <span class="fs-7">
                            Transactions move real money. Switching this to sandbox on a running site produces
                            payments that look successful to everyone and never settle — which is usually found at
                            month end, against a bank statement, long after the workshops have been run.
                        </span>
                    </div>
                </div>
            @endif

            <div class="row g-5">

                <div class="col-md-6">
                    <label class="required form-label">Mode</label>
                    {{-- Plain form-select: two options, and it drives a change
                         listener in settings.js for the live-mode warning. --}}
                    <select name="mode" id="gateway-mode" class="form-select form-select-solid border">
                        <option value="sandbox" @selected($gateway['mode'] !== 'live')>Sandbox — nothing is really
                            charged</option>
                        <option value="live" @selected($gateway['mode'] === 'live')>Live — real money moves</option>
                    </select>
                    <div class="invalid-feedback d-block" data-error-for="mode"></div>
                </div>

                <div class="col-md-6 d-flex align-items-end">
                    <div class="text-muted fs-8">
                        A mode cannot be selected until its store ID and password are both saved.
                    </div>
                </div>

                <div class="col-12">
                    <div class="separator separator-dashed my-3"></div>
                    <h4 class="fs-6 fw-bold text-gray-800 mb-0">Sandbox store</h4>
                    <span class="text-muted fs-8">Used for testing. Payments complete without money moving.</span>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Sandbox store ID</label>
                    <input type="text" name="sandbox_store_id" class="form-control form-control-solid border"
                        autocomplete="off" value="{{ $gateway['sandbox_store_id'] }}" />
                    <div class="invalid-feedback d-block" data-error-for="sandbox_store_id"></div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Sandbox store password</label>
                    <div class="position-relative">
                        <input type="password" name="sandbox_store_password" id="sandbox-store-password"
                            class="form-control form-control-solid border pe-12" autocomplete="new-password"
                            placeholder="{{ $gateway['sandbox_has_secret'] ? 'Set — leave blank to keep it' : 'Not set' }}" />
                        <button type="button"
                            class="btn btn-sm btn-icon position-absolute translate-middle-y top-50 end-0 me-2"
                            data-password-toggle="#sandbox-store-password" aria-label="Show password"
                            aria-pressed="false">
                            <i class="ki-outline ki-eye fs-2"></i>
                        </button>
                    </div>
                    <div class="form-text">Stored encrypted. Blank means unchanged.</div>
                    <div class="invalid-feedback d-block" data-error-for="sandbox_store_password"></div>
                </div>

                <div class="col-12">
                    <div class="separator separator-dashed my-3"></div>
                    <h4 class="fs-6 fw-bold text-gray-800 mb-0">Live store</h4>
                    <span class="text-muted fs-8">Real transactions. Only used when the mode above says
                        Live.</span>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Live store ID</label>
                    <input type="text" name="live_store_id" class="form-control form-control-solid border"
                        autocomplete="off" value="{{ $gateway['live_store_id'] }}" />
                    <div class="invalid-feedback d-block" data-error-for="live_store_id"></div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Live store password</label>
                    <div class="position-relative">
                        <input type="password" name="live_store_password" id="live-store-password"
                            class="form-control form-control-solid border pe-12" autocomplete="new-password"
                            placeholder="{{ $gateway['live_has_secret'] ? 'Set — leave blank to keep it' : 'Not set' }}" />
                        <button type="button"
                            class="btn btn-sm btn-icon position-absolute translate-middle-y top-50 end-0 me-2"
                            data-password-toggle="#live-store-password" aria-label="Show password"
                            aria-pressed="false">
                            <i class="ki-outline ki-eye fs-2"></i>
                        </button>
                    </div>
                    <div class="form-text">Stored encrypted. Blank means unchanged.</div>
                    <div class="invalid-feedback d-block" data-error-for="live_store_password"></div>
                </div>

                <div class="col-12">
                    <div class="form-check form-switch form-check-custom form-check-solid">
                        <input class="form-check-input" type="checkbox" disabled
                            @checked($values['payments.online_enabled'] ?? true) />
                        <label class="form-check-label fw-semibold">
                            Accept payment online
                            <span class="d-block text-muted fs-7 fw-normal">
                                A setting, not a credential — change it on the Payments tab. Off hides the Pay
                                online button during an outage without touching anything here.
                            </span>
                        </label>
                    </div>
                </div>

            </div>
        </div>

        <div class="card-footer d-flex justify-content-end py-4">
            <button type="submit" class="btn btn-primary" data-submit>
                <span class="indicator-label">Save gateway settings</span>
                <span class="indicator-progress">Saving…
                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
            </button>
        </div>
    </form>
</div>

{{--
    THE CALLBACK URLS.

    Registered with SSLCommerz exactly as printed. Generated with route() from
    APP_URL rather than typed, so what is shown is what the application will
    actually send in its initiation payload — if APP_URL is wrong, that is
    visible here rather than discovered on the first live transaction.
--}}
<div class="card">
    <div class="card-header border-0 pt-6">
        <div class="card-title flex-column align-items-start">
            <h3 class="fw-bold m-0">Callback URLs</h3>
            <span class="text-muted fs-7 mt-1">Register these in the SSLCommerz merchant panel, exactly as
                shown.</span>
        </div>
    </div>

    <div class="card-body pt-0">

        {{--
            THE KNOWN ISSUE, SURFACED RATHER THAN LEFT IN A DOCUMENT.

            SSLCommerz checks that requests arrive from the domain the store is
            registered to. This store was registered against the parent domain
            while the application transacts from the booking subdomain. Nothing
            in the code can read the registered value, so the comparison has to
            be handed to a person — but the application's own host is printed,
            which is half of it.
        --}}
        <div class="alert alert-light-warning border border-warning d-flex align-items-start p-5 mb-6">
            <i class="ki-outline ki-information-5 fs-2hx text-warning me-4 mt-1"></i>
            <div>
                <h5 class="mb-1">Check these against the registered store domain</h5>
                <span class="fs-7 d-block">
                    This application transacts from <strong>{{ $gateway['app_host'] }}</strong>. If the store was
                    registered against a different host, SSLCommerz rejects the session and the failure looks like
                    a broken integration rather than a mismatched setting. Ask SSLCommerz to update the registered
                    URL if they differ.
                </span>
            </div>
        </div>

        @foreach ([
        'IPN (instant payment notification)' => $gateway['urls']['ipn'],
        'Success' => $gateway['urls']['success'],
        'Fail' => $gateway['urls']['fail'],
        'Cancel' => $gateway['urls']['cancel'],
    ] as $label => $url)
            <div class="d-flex align-items-center flex-wrap gap-3 py-3 @if (!$loop->first) border-top @endif">
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold text-gray-800">{{ $label }}</div>
                    <div class="text-muted font-monospace fs-8 text-break">{{ $url }}</div>
                </div>
                <button type="button" class="btn btn-sm btn-light-primary flex-shrink-0" data-copy="{{ $url }}">
                    <i class="ki-outline ki-copy fs-4"></i> Copy
                </button>
            </div>
        @endforeach

        <div class="separator my-5"></div>

        <p class="text-muted fs-7 mb-0">
            The IPN is the one that matters. Success, fail and cancel are redirects in the visitor's own browser
            and can be typed by hand — a payment is only ever settled by a server-to-server validation, never by
            somebody arriving at the success URL.
        </p>
    </div>
</div>
