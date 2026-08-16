/*
|------------------------------------------------------------------------------
| Vouchers
|------------------------------------------------------------------------------
| Depends on js/admin/shunno.js.
|
| Three jobs, and the first is the one that matters most in practice:
|
|   the counter    type a code, read whether it is good, mark it used
|   the register   search, filter, sort, page, drawer
|   creating       gift vouchers only
|
| No arithmetic anywhere. Values, validity and the reason a code is refused all
| arrive from the server already decided — a browser that worked out for itself
| whether a voucher had expired would eventually disagree with the service that
| actually refuses it.
*/
(function () {
    'use strict';

    var config = window.VouchersConfig || {};

    var listEl = document.getElementById('vouchers-list');
    if (!listEl) return;

    var search = document.getElementById('vouchers-search');
    var typeFilter = document.getElementById('vouchers-type');
    var statusFilter = document.getElementById('vouchers-status');
    var perPage = document.getElementById('vouchers-per-page');

    var drawerEl = document.getElementById('voucher-modal');
    var drawerBody = document.getElementById('voucher-modal-body');
    var drawerTitle = document.getElementById('voucher-modal-title');

    var listRequest = 0;

    /* =====================================================================
       The counter — look a code up without spending it
       ===================================================================== */

    var lookupInput = document.getElementById('voucher-lookup');
    var lookupGo = document.getElementById('voucher-lookup-go');
    var lookupResult = document.getElementById('voucher-lookup-result');

    if (lookupInput) {
        lookupGo.addEventListener('click', runLookup);

        lookupInput.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            runLookup();
        });

        // Codes are printed and read in upper case and the server uppercases
        // before matching; doing it here too means what is typed matches what
        // is on the card.
        lookupInput.addEventListener('input', function () {
            var start = this.selectionStart;
            this.value = this.value.toUpperCase();
            this.setSelectionRange(start, start);
        });
    }

    function runLookup() {
        var code = (lookupInput.value || '').trim();

        if (code === '') {
            lookupInput.focus();
            return;
        }

        lookupResult.hidden = false;
        lookupResult.innerHTML = '<div class="text-muted fs-7">Checking…</div>';

        Shunno.busy(lookupGo, true);

        Shunno.request(config.lookupUrl + '?code=' + encodeURIComponent(code))
            .then(function (payload) {
                renderLookup(payload.data);
            })
            .catch(function (error) {
                if (error.handled) return;

                // 404 means no such code, which is a normal answer at a counter
                // rather than an error — somebody mistyped, or the card is from
                // somewhere else. Rendered in the panel, not as a toast that
                // disappears while they are still reading it.
                lookupResult.innerHTML =
                    '<div class="notice d-flex bg-light-danger rounded border-danger border border-dashed p-4">' +
                    '<i class="ki-outline ki-information-5 fs-2 text-danger me-3"></i>' +
                    '<div class="fs-7 text-gray-700"></div></div>';
                lookupResult.querySelector('.fs-7').textContent = error.message;
            })
            .finally(function () {
                Shunno.busy(lookupGo, false);
            });
    }

    /**
     * Built from a template already in the DOM would be neater, but this panel
     * is the one place the "Blade renders, JS swaps" rule is bent — and only
     * because the shape is fixed and carries no user-authored HTML. Every value
     * below is set with textContent, never innerHTML, so a voucher note or a
     * recipient name cannot inject anything.
     */
    function renderLookup(data) {
        var tone = data.usable ? 'success' : 'danger';

        lookupResult.innerHTML =
            '<div class="notice d-flex bg-light-' + tone + ' rounded border-' + tone +
            ' border border-dashed p-4 align-items-center flex-wrap gap-3">' +
            '<div class="flex-grow-1">' +
            '<div class="fs-4 fw-bold text-gray-900" data-code></div>' +
            '<div class="fs-7 text-gray-700" data-line></div>' +
            '<div class="fs-8 text-muted" data-sub></div>' +
            '</div>' +
            '<div class="text-end"><div class="fs-2 fw-bold text-gray-900" data-value></div></div>' +
            '<div data-actions></div>' +
            '</div>';

        lookupResult.querySelector('[data-code]').textContent = data.code;
        lookupResult.querySelector('[data-value]').textContent = 'BDT ' + Number(data.value).toLocaleString();
        lookupResult.querySelector('[data-line]').textContent =
            data.usable ? data.type + ' — good to use' : (data.reason || data.status);

        var sub = [data.spend_on];
        if (data.holder) sub.push('Held by ' + data.holder);
        if (data.workshop) sub.push('Only for ' + data.workshop);
        if (data.expires) sub.push('Expires ' + data.expires);
        lookupResult.querySelector('[data-sub]').textContent = sub.join(' · ');

        var actions = lookupResult.querySelector('[data-actions]');

        if (data.usable && data.can_redeem) {
            var redeem = document.createElement('button');
            redeem.type = 'button';
            redeem.className = 'btn btn-success';
            redeem.textContent = 'Mark as used';
            redeem.dataset.action = 'redeem-voucher';
            redeem.dataset.url = data.redeem_url;
            redeem.dataset.code = data.code;
            redeem.dataset.value = Number(data.value).toLocaleString();
            actions.appendChild(redeem);
        }

        var open = document.createElement('button');
        open.type = 'button';
        open.className = 'btn btn-light ms-2';
        open.textContent = 'Open';
        open.dataset.action = 'view-voucher';
        open.dataset.url = data.show_url;
        actions.appendChild(open);
    }

    /* =====================================================================
       Register
       ===================================================================== */

    function currentQuery(extra) {
        var params = new URLSearchParams(window.location.search);

        // Rebuilt rather than patched, so clearing the search box actually
        // clears the parameter instead of leaving a stale one behind.
        ['q', 'type', 'status', 'per_page', 'page'].forEach(function (key) {
            params.delete(key);
        });

        if (search && search.value.trim()) params.set('q', search.value.trim());
        if (typeFilter && typeFilter.value !== 'all') params.set('type', typeFilter.value);
        if (statusFilter && statusFilter.value !== 'usable') params.set('status', statusFilter.value);
        if (perPage && perPage.value !== '25') params.set('per_page', perPage.value);

        if (extra) {
            Object.keys(extra).forEach(function (key) {
                if (extra[key] === null) {
                    params.delete(key);
                } else {
                    params.set(key, extra[key]);
                }
            });
        }

        return params.toString();
    }

    function loadList(extra) {
        var ticket = ++listRequest;
        var query = currentQuery(extra);

        listEl.classList.add('opacity-50');

        return Shunno.request(config.listUrl + (query ? '?' + query : ''))
            .then(function (payload) {
                if (ticket !== listRequest) return;      // A newer request won.

                listEl.innerHTML = payload.data.html;
                window.history.replaceState({}, '', window.location.pathname + (query ? '?' + query : ''));
            })
            .catch(function (error) {
                if (error.handled) return;
                Shunno.toast('error', error.message);
            })
            .finally(function () {
                if (ticket === listRequest) listEl.classList.remove('opacity-50');
            });
    }

    if (search) {
        var typing = null;

        search.addEventListener('input', function () {
            window.clearTimeout(typing);
            typing = window.setTimeout(function () { loadList({ page: null }); }, 350);
        });

        search.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            window.clearTimeout(typing);
            loadList({ page: null });
        });
    }

    // Native listeners on plain selects — Select2 would need jQuery's .trigger()
    // and would never fire these.
    [typeFilter, statusFilter, perPage].forEach(function (el) {
        if (!el) return;
        el.addEventListener('change', function () { loadList({ page: null }); });
    });

    listEl.addEventListener('click', function (event) {
        var sortLink = event.target.closest('a[data-vouchers-sort]');

        if (sortLink) {
            event.preventDefault();
            var url = new URL(sortLink.href, window.location.origin);
            loadList({
                sort: url.searchParams.get('sort'),
                dir: url.searchParams.get('dir'),
                page: null
            });
            return;
        }

        var pageLink = event.target.closest('[data-vouchers-pagination] a.page-link');

        if (pageLink) {
            event.preventDefault();
            var page = new URL(pageLink.href, window.location.origin).searchParams.get('page');
            if (page) loadList({ page: page });
        }
    });

    /* =====================================================================
       Drawer
       ===================================================================== */

    // Delegated from the document: the trigger sits in the table, which is
    // replaced wholesale, and also in the lookup panel, which is built fresh on
    // every search.
    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-action="view-voucher"]');
        if (!trigger) return;

        event.preventDefault();
        openDrawer(trigger.dataset.url);
    });

    function openDrawer(url) {
        drawerBody.innerHTML = '<div class="text-center text-muted py-10">Loading…</div>';
        drawerTitle.textContent = 'Voucher';
        Shunno.modal(drawerEl).show();

        return Shunno.request(url)
            .then(function (payload) {
                drawerBody.innerHTML = payload.data.html;
                drawerTitle.textContent = payload.data.code;
            })
            .catch(function (error) {
                if (error.handled) return;
                drawerBody.innerHTML =
                    '<div class="text-center text-danger py-10">Could not load this voucher.</div>';
                Shunno.toast('error', error.message);
            });
    }

    /* =====================================================================
       Creating
       ===================================================================== */

    var formModalEl = document.getElementById('voucher-form-modal');
    var form = document.getElementById('voucher-form');
    var formSave = document.getElementById('voucher-form-save');
    var createButton = document.getElementById('voucher-create');

    if (createButton && form) {
        createButton.addEventListener('click', function () {
            Shunno.clearErrors(form);
            form.reset();
            form.action = config.storeUrl;

            // Select2 keeps its own state and a native reset() does not reach
            // it, so the previous choice would survive into the next voucher.
            Shunno.syncSelects(form);

            Shunno.modal(formModalEl).show();
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            Shunno.clearErrors(form);
            Shunno.busy(formSave, true);

            Shunno.request(form.action, { method: 'POST', body: new FormData(form) })
                .then(function (payload) {
                    Shunno.modal(formModalEl).hide();
                    Shunno.toast('success', payload.message);
                    listEl.innerHTML = payload.data.list.html;
                })
                .catch(function (error) {
                    if (error.handled) return;
                    if (error.status === 422 && error.errors) Shunno.showErrors(form, error.errors);
                    Shunno.toast('error', error.message);
                })
                .finally(function () {
                    Shunno.busy(formSave, false);
                });
        });
    }

    /* =====================================================================
       Redeem and cancel
       ===================================================================== */

    var redeemModalEl = document.getElementById('voucher-redeem-modal');
    var redeemForm = document.getElementById('voucher-redeem-form');
    var redeemSave = document.getElementById('voucher-redeem-save');

    var cancelModalEl = document.getElementById('voucher-cancel-modal');
    var cancelForm = document.getElementById('voucher-cancel-form');
    var cancelSave = document.getElementById('voucher-cancel-save');

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-action="redeem-voucher"], [data-action="cancel-voucher"]');
        if (!trigger) return;

        event.preventDefault();

        if (trigger.dataset.action === 'redeem-voucher') {
            if (!redeemForm) return;

            Shunno.clearErrors(redeemForm);
            redeemForm.reset();
            redeemForm.action = trigger.dataset.url;

            document.getElementById('voucher-redeem-code').textContent = trigger.dataset.code;
            document.getElementById('voucher-redeem-value').textContent = trigger.dataset.value;

            Shunno.modal(redeemModalEl).show();
            return;
        }

        if (!cancelForm) return;

        Shunno.clearErrors(cancelForm);
        cancelForm.reset();
        cancelForm.action = trigger.dataset.url;
        document.getElementById('voucher-cancel-code').textContent = trigger.dataset.code;

        Shunno.modal(cancelModalEl).show();
    });

    if (redeemForm) {
        redeemForm.addEventListener('submit', function (event) {
            event.preventDefault();

            Shunno.busy(redeemSave, true);

            Shunno.request(redeemForm.action, { method: 'POST', body: new FormData(redeemForm) })
                .then(function (payload) {
                    Shunno.modal(redeemModalEl).hide();
                    Shunno.toast('success', payload.message);

                    listEl.innerHTML = payload.data.list.html;
                    if (drawerBody) drawerBody.innerHTML = payload.data.html;

                    // The looked-up code has just been spent, so the panel above
                    // is now wrong. Clearing it stops anybody redeeming twice
                    // from a stale result.
                    if (lookupResult) {
                        lookupResult.hidden = true;
                        lookupResult.innerHTML = '';
                        if (lookupInput) lookupInput.value = '';
                    }
                })
                .catch(function (error) {
                    if (error.handled) return;

                    // 409 means somebody else got there first, or it expired
                    // between the lookup and the click. The message says which.
                    Shunno.toast('error', error.message);
                })
                .finally(function () {
                    Shunno.busy(redeemSave, false);
                });
        });
    }

    if (cancelForm) {
        cancelForm.addEventListener('submit', function (event) {
            event.preventDefault();

            var reason = document.getElementById('voucher-cancel-reason');

            if (reason.value.trim() === '') {
                Shunno.showErrors(cancelForm, { reason: ['Say why this voucher is being cancelled.'] });
                reason.focus();
                return;
            }

            Shunno.clearErrors(cancelForm);
            Shunno.busy(cancelSave, true);

            Shunno.request(cancelForm.action, { method: 'POST', body: new FormData(cancelForm) })
                .then(function (payload) {
                    Shunno.modal(cancelModalEl).hide();
                    Shunno.toast('success', payload.message);

                    listEl.innerHTML = payload.data.list.html;
                    if (drawerBody) drawerBody.innerHTML = payload.data.html;
                })
                .catch(function (error) {
                    if (error.handled) return;
                    if (error.status === 422 && error.errors) Shunno.showErrors(cancelForm, error.errors);
                    Shunno.toast('error', error.message);
                })
                .finally(function () {
                    Shunno.busy(cancelSave, false);
                });
        });
    }
})();
