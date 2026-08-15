/*
|------------------------------------------------------------------------------
| Payments
|------------------------------------------------------------------------------
| Depends on js/admin/shunno.js.
|
| Loaded on TWO pages, and does a different job on each:
|
|   admin/payments      the register — search, filters, sorting, paging, the
|                       drawer, and the record/withdraw actions
|   admin/reservations  the request-payment modal only, opened from the
|                       reservation drawer
|
| One file rather than two because the two halves share the modal plumbing and
| the money formatting, and because a second file would have to be loaded
| conditionally by a Blade template that has no other reason to know about
| payments. Each half guards on the elements it needs, so loading it on a page
| with neither costs one null check.
|
| No arithmetic anywhere in here. Every figure shown was calculated by
| PricingService and arrived over the wire finished.
*/
(function () {
    'use strict';

    var config = window.PaymentsConfig || {};

    /* =====================================================================
       Shared
       ===================================================================== */

    function money(value) {
        var number = Number(value || 0);

        // en-BD gives the digit grouping Bangladesh actually uses (1,00,000)
        // where en-US would render 100,000. Falls back gracefully on the
        // handful of browsers that do not carry the locale.
        try {
            return number.toLocaleString('en-BD', { maximumFractionDigits: 0 });
        } catch (e) {
            return number.toLocaleString();
        }
    }

    /* =====================================================================
       PART ONE — the register
       ===================================================================== */

    var listEl = document.getElementById('payments-list');

    if (listEl) {
        var search = document.getElementById('payments-search');
        var status = document.getElementById('payments-status');
        var perPage = document.getElementById('payments-per-page');

        var detailModalEl = document.getElementById('payment-modal');
        var detailBody = document.getElementById('payment-modal-body');
        var detailTitle = document.getElementById('payment-modal-title');

        var recordModalEl = document.getElementById('payment-record-modal');
        var recordForm = document.getElementById('payment-record-form');
        var recordSave = document.getElementById('payment-record-save');

        var cancelModalEl = document.getElementById('payment-cancel-modal');
        var cancelForm = document.getElementById('payment-cancel-form');
        var cancelSave = document.getElementById('payment-cancel-save');

        var listRequest = 0;
        var openReference = null;

        function currentQuery(extra) {
            var params = new URLSearchParams(window.location.search);

            // Rebuilt from the controls rather than patched, so a cleared search
            // box actually clears the parameter instead of leaving a stale one.
            params.delete('q');
            params.delete('status');
            params.delete('per_page');
            params.delete('page');

            if (search && search.value.trim()) params.set('q', search.value.trim());
            if (status && status.value !== 'open') params.set('status', status.value);
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
                    // Typing fast produces overlapping requests; only the newest
                    // answer may touch the DOM.
                    if (ticket !== listRequest) return;

                    listEl.innerHTML = payload.data.html;

                    // Keep the address bar in step so a refresh or a shared link
                    // reproduces what is on screen.
                    var url = window.location.pathname + (query ? '?' + query : '');
                    window.history.replaceState({}, '', url);
                })
                .catch(function (error) {
                    if (error.handled) return;
                    Shunno.toast('error', error.message);
                })
                .finally(function () {
                    if (ticket === listRequest) listEl.classList.remove('opacity-50');
                });
        }

        /* ----- Filters ----- */

        if (search) {
            var typingTimer = null;

            search.addEventListener('input', function () {
                window.clearTimeout(typingTimer);
                typingTimer = window.setTimeout(function () {
                    loadList({ page: null });
                }, 350);
            });

            search.addEventListener('keydown', function (event) {
                if (event.key !== 'Enter') return;
                event.preventDefault();
                window.clearTimeout(typingTimer);
                loadList({ page: null });
            });
        }

        // Native listeners on plain selects. Metronic's Select2 would need
        // jQuery's .trigger() to reach and would not fire these — Phase 6
        // settled that short filter dropdowns stay native.
        [status, perPage].forEach(function (el) {
            if (!el) return;
            el.addEventListener('change', function () {
                loadList({ page: null });
            });
        });

        /* ----- Pagination, sorting, row actions ----- */

        listEl.addEventListener('click', function (event) {
            var sortLink = event.target.closest('a[data-payments-sort]');

            if (sortLink) {
                event.preventDefault();
                var sortUrl = new URL(sortLink.href, window.location.origin);
                loadList({
                    sort: sortUrl.searchParams.get('sort'),
                    dir: sortUrl.searchParams.get('dir'),
                    page: null
                });
                return;
            }

            var pageLink = event.target.closest('[data-payments-pagination] a.page-link');

            if (pageLink) {
                event.preventDefault();
                var page = new URL(pageLink.href, window.location.origin).searchParams.get('page');
                if (page) {
                    loadList({ page: page }).then(function () {
                        listEl.scrollIntoView({ block: 'start', behavior: 'smooth' });
                    });
                }
                return;
            }

            var trigger = event.target.closest('[data-action="view-payment"]');
            if (!trigger) return;

            event.preventDefault();
            openDetail(trigger.dataset.url);
        });

        /* ----- Drawer ----- */

        function openDetail(url) {
            if (!detailModalEl) return;

            detailBody.innerHTML = '<div class="text-center text-muted py-10">Loading…</div>';
            detailTitle.textContent = 'Payment';
            Shunno.modal(detailModalEl).show();

            return Shunno.request(url)
                .then(function (payload) {
                    detailBody.innerHTML = payload.data.html;
                    detailTitle.textContent = payload.data.reference;
                    openReference = payload.data.reference;
                })
                .catch(function (error) {
                    if (error.handled) return;
                    detailBody.innerHTML =
                        '<div class="text-center text-danger py-10">Could not load this payment.</div>';
                    Shunno.toast('error', error.message);
                });
        }

        // The action buttons live inside the drawer's server-rendered HTML, so
        // the listener sits on the body and reads what Blade put there.
        if (detailBody) {
            detailBody.addEventListener('click', function (event) {
                var trigger = event.target.closest('[data-action]');
                if (!trigger) return;

                event.preventDefault();

                if (trigger.dataset.action === 'record-payment') {
                    openRecord(trigger.dataset);
                } else if (trigger.dataset.action === 'cancel-payment') {
                    openCancel(trigger.dataset);
                }
            });
        }

        /* ----- Record ----- */

        function openRecord(data) {
            if (!recordForm) return;

            Shunno.clearErrors(recordForm);
            recordForm.reset();
            recordForm.action = data.url;

            document.getElementById('payment-record-reference').textContent = data.reference;
            document.getElementById('payment-record-outstanding').textContent = money(data.outstanding);

            var amount = document.getElementById('payment-record-amount');

            // Prefilled with the outstanding figure because settling in full is
            // the overwhelmingly common case; it stays editable for the deposit
            // case, which the server also accepts.
            amount.value = data.outstanding;
            amount.max = data.outstanding;

            Shunno.modal(recordModalEl).show();
            window.setTimeout(function () { amount.focus(); amount.select(); }, 250);
        }

        if (recordForm) {
            recordForm.addEventListener('submit', function (event) {
                event.preventDefault();

                Shunno.clearErrors(recordForm);
                Shunno.busy(recordSave, true);

                Shunno.request(recordForm.action, {
                    method: 'POST',
                    body: new FormData(recordForm)
                })
                    .then(function (payload) {
                        Shunno.modal(recordModalEl).hide();
                        Shunno.toast('success', payload.message);

                        detailBody.innerHTML = payload.data.html;
                        listEl.innerHTML = payload.data.list.html;
                    })
                    .catch(function (error) {
                        if (error.handled) return;

                        if (error.status === 422 && error.errors) {
                            Shunno.showErrors(recordForm, error.errors);
                        }

                        Shunno.toast('error', error.message);

                        // A 409 means the world moved underneath this form —
                        // somebody else recorded it, or withdrew it. Reloading
                        // the drawer is more useful than an error alone.
                        if (error.status === 409 && openReference) {
                            Shunno.modal(recordModalEl).hide();
                            loadList();
                        }
                    })
                    .finally(function () {
                        Shunno.busy(recordSave, false);
                    });
            });
        }

        /* ----- Withdraw ----- */

        function openCancel(data) {
            if (!cancelForm) return;

            Shunno.clearErrors(cancelForm);
            cancelForm.reset();
            cancelForm.action = data.url;

            document.getElementById('payment-cancel-reference').textContent = data.reference;

            Shunno.modal(cancelModalEl).show();
            window.setTimeout(function () {
                document.getElementById('payment-cancel-reason').focus();
            }, 250);
        }

        if (cancelForm) {
            cancelForm.addEventListener('submit', function (event) {
                event.preventDefault();

                var reason = document.getElementById('payment-cancel-reason');

                if (reason.value.trim() === '') {
                    Shunno.showErrors(cancelForm, { reason: ['Say why this request is being withdrawn.'] });
                    reason.focus();
                    return;
                }

                Shunno.clearErrors(cancelForm);
                Shunno.busy(cancelSave, true);

                Shunno.request(cancelForm.action, {
                    method: 'POST',
                    body: new FormData(cancelForm)
                })
                    .then(function (payload) {
                        Shunno.modal(cancelModalEl).hide();
                        Shunno.toast('success', payload.message);

                        detailBody.innerHTML = payload.data.html;
                        listEl.innerHTML = payload.data.list.html;
                    })
                    .catch(function (error) {
                        if (error.handled) return;

                        if (error.status === 422 && error.errors) {
                            Shunno.showErrors(cancelForm, error.errors);
                        }

                        Shunno.toast('error', error.message);
                    })
                    .finally(function () {
                        Shunno.busy(cancelSave, false);
                    });
            });
        }
    }

    /* =====================================================================
       PART TWO — requesting payment, from the reservation drawer
       ===================================================================== */

    var requestForm = document.getElementById('payment-request-form');

    if (requestForm) {
        var requestModalEl = document.getElementById('payment-request-modal');
        var requestSave = document.getElementById('payment-request-save');
        var hoursInput = document.getElementById('payment-request-hours');

        var preview = null;

        /*
         | Delegated from the document because the trigger is inside the
         | reservation drawer, which reservations.js replaces wholesale on every
         | decision. A listener bound to the button itself would survive exactly
         | one render.
         */
        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-action="request-payment"]');
            if (!trigger) return;

            event.preventDefault();
            openRequest(trigger.dataset.url);
        });

        function openRequest(url) {
            Shunno.clearErrors(requestForm);
            requestForm.reset();

            Shunno.request(url)
                .then(function (payload) {
                    preview = payload.data;

                    requestForm.action = preview.url;

                    document.getElementById('payment-request-visitor').textContent = preview.visitor || '';
                    document.getElementById('payment-request-reference').textContent = preview.reference;
                    document.getElementById('payment-request-total').textContent = money(preview.reservation_total);
                    document.getElementById('payment-request-due').textContent = preview.default_due_at;

                    document.getElementById('payment-request-agreed').hidden = !preview.has_manual_price;

                    hoursInput.value = preview.default_hours;

                    // Both cards are filled at once; choosing a type is then a
                    // pure display change with no round trip and no maths.
                    Object.keys(preview.types).forEach(function (key) {
                        var type = preview.types[key];

                        var label = requestForm.querySelector('[data-payment-type-label="' + key + '"]');
                        var payable = requestForm.querySelector('[data-payment-type-payable="' + key + '"]');
                        var remaining = requestForm.querySelector('[data-payment-type-remaining="' + key + '"]');

                        if (label) label.textContent = type.label;
                        if (payable) payable.textContent = money(type.payable);

                        if (remaining) {
                            remaining.textContent = type.remaining > 0
                                ? money(type.remaining) + ' payable at the studio'
                                : 'Nothing left to pay';
                        }
                    });

                    Shunno.modal(requestModalEl).show();
                })
                .catch(function (error) {
                    if (error.handled) return;
                    Shunno.toast('error', error.message);
                });
        }

        requestForm.addEventListener('submit', function (event) {
            event.preventDefault();

            Shunno.clearErrors(requestForm);
            Shunno.busy(requestSave, true);

            // Filters travel with the request so the refreshed reservation list
            // comes back matching what is on screen behind the modal, exactly
            // as the decision endpoints do.
            var query = window.location.search.replace(/^\?/, '');

            Shunno.request(requestForm.action + (query ? '?' + query : ''), {
                method: 'POST',
                body: new FormData(requestForm)
            })
                .then(function (payload) {
                    Shunno.modal(requestModalEl).hide();
                    Shunno.toast('success', payload.message);

                    // Owned by reservations.js; updated by id rather than by
                    // reaching into that module, which has no public surface.
                    var list = document.getElementById('reservations-list');
                    var drawer = document.getElementById('reservation-modal-body');

                    if (list && payload.data.list) list.innerHTML = payload.data.list.html;
                    if (drawer && payload.data.detail) drawer.innerHTML = payload.data.detail;
                })
                .catch(function (error) {
                    if (error.handled) return;

                    if (error.status === 422 && error.errors) {
                        Shunno.showErrors(requestForm, error.errors);
                    }

                    Shunno.toast('error', error.message);
                })
                .finally(function () {
                    Shunno.busy(requestSave, false);
                });
        });
    }
})();
