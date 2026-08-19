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

        // Status, date range and page size live in the shared filter menu now.
        // Created further down, once loadList() exists for it to call.
        var filters = null;

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
            // box or a reset filter actually drops the parameter instead of
            // leaving a stale one in the address bar.
            params.delete('q');
            params.delete('status');
            params.delete('from');
            params.delete('to');
            params.delete('per_page');
            params.delete('page');

            if (search && search.value.trim()) params.set('q', search.value.trim());

            // changed() returns only the filters that are NOT at their default,
            // so the server's own defaults stand where nothing was chosen.
            if (filters) {
                var active = filters.changed();
                Object.keys(active).forEach(function (key) {
                    params.set(key, active[key]);
                });
            }

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

        filters = Shunno.filterBar({
            root: document.getElementById('payments-filter'),
            onApply: function () {
                loadList({ page: null });
            }
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

        /* ----- Take payment at the counter -----

           The register lists payment REQUESTS, so a booking fee that settled
           leaves a visit with a balance and no open request to record it
           against — which is why staff had no button for money handed over on
           the day. This starts from the RESERVATION instead.

           A remote Select2 rather than a rendered list: the answer is one
           reservation out of however many are open, and whoever is at the till
           knows the name or the reference. The server decides which
           reservations qualify and formats every figure on the summary card;
           nothing here works out what is owed.
        */

        var collectModalEl = document.getElementById('payment-collect-modal');
        var collectForm = document.getElementById('payment-collect-form');

        function collectPart(name) {
            return collectModalEl
                ? collectModalEl.querySelector('[data-collect="' + name + '"]')
                : null;
        }

        function openCollect() {
            if (!collectForm || !config.collectableUrl) return;

            Shunno.clearErrors(collectForm);
            collectForm.reset();

            // Back to the loading state every time. Reopening the modal after a
            // payment must not show the previous visitor's summary card while
            // the picker reloads.
            collectPart('loading').hidden = false;
            collectPart('notice').hidden = true;
            collectPart('body').hidden = true;
            collectPart('save').hidden = true;
            collectPart('summary').hidden = true;
            collectPart('summary').innerHTML = '';

            Shunno.modal(collectModalEl).show();

            /*
             | One plain fetch before Select2 is wired up, purely to answer "is
             | there anything to collect at all". Select2's own empty state is a
             | line of grey text inside a dropdown nobody has opened yet, which
             | is not where somebody looks to find out that every visit is paid
             | up.
             */
            fetch(config.collectableUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                credentials: 'same-origin'
            })
                .then(function (response) { return response.json(); })
                .then(function (payload) {
                    collectPart('loading').hidden = true;

                    if (payload.notice) {
                        collectPart('notice-text').textContent = payload.notice;
                        collectPart('notice').hidden = false;
                        return;
                    }

                    collectPart('body').hidden = false;
                    collectPart('save').hidden = false;
                    initCollectPicker();
                })
                .catch(function () {
                    collectPart('loading').hidden = true;
                    collectPart('notice-text').textContent =
                        'Could not load reservations just now. Please try again.';
                    collectPart('notice').hidden = false;
                });
        }

        var collectPickerReady = false;

        function initCollectPicker() {
            var field = document.getElementById('payment-collect-reservation');

            if (!field || !window.jQuery || !window.jQuery.fn.select2) return;

            if (collectPickerReady) {
                // Already built. Clear the previous choice rather than
                // rebuilding, which would leave two instances stacked up.
                window.jQuery(field).val(null).trigger('change');
                return;
            }

            window.jQuery(field).select2({
                placeholder: field.dataset.placeholder,
                allowClear: true,

                // The modal, not the body: a dropdown appended to <body> renders
                // behind the backdrop and cannot be clicked.
                dropdownParent: window.jQuery(collectModalEl),

                minimumInputLength: 0,
                ajax: {
                    url: config.collectableUrl,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { q: params.term || '' };
                    },
                    processResults: function (payload) {
                        return { results: payload.results || [] };
                    }
                },
                language: {
                    noResults: function () {
                        return 'No reservation with a balance matches that.';
                    }
                }
            });

            /*
             | jQuery's own event, because that is what Select2 fires and this
             | needs the chosen row's DATA rather than just its value — the
             | summary card and the prefilled amount both come off it.
             */
            window.jQuery(field).on('select2:select', function (event) {
                var chosen = event.params.data;
                var summary = collectPart('summary');
                var amount = document.getElementById('payment-collect-amount');

                summary.innerHTML = chosen.card || '';
                summary.hidden = !chosen.card;

                // Assigned, not calculated. The server sent this string.
                if (amount) {
                    amount.value = chosen.outstanding_input || '';
                    amount.max = chosen.outstanding_input || '';
                }
            });

            window.jQuery(field).on('select2:clear', function () {
                collectPart('summary').innerHTML = '';
                collectPart('summary').hidden = true;
            });

            collectPickerReady = true;
        }

        document.querySelectorAll('[data-collect="open"]').forEach(function (button) {
            button.addEventListener('click', openCollect);
        });

        if (collectForm) {
            collectForm.addEventListener('submit', function (event) {
                event.preventDefault();

                var save = collectPart('save');

                Shunno.clearErrors(collectForm);
                Shunno.busy(save, true);

                Shunno.request(collectForm.action, {
                    method: 'POST',
                    body: new FormData(collectForm)
                })
                    .then(function (payload) {
                        Shunno.modal(collectModalEl).hide();
                        Shunno.toast('success', payload.message);
                        loadList();
                    })
                    .catch(function (error) {
                        if (error.handled) return;

                        if (error.status === 422 && error.errors) {
                            Shunno.showErrors(collectForm, error.errors);
                        }

                        Shunno.toast('error', error.message);

                        /*
                         | 409 means the state moved under us — somebody else
                         | settled it, or the reservation was cancelled while
                         | this modal sat open. The list is refreshed so the
                         | next attempt starts from the truth.
                         */
                        if (error.status === 409) {
                            Shunno.modal(collectModalEl).hide();
                            loadList();
                        }
                    })
                    .finally(function () {
                        Shunno.busy(save, false);
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
