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
|   writing        create, edit and delete gift vouchers
|
| PHASE 25 changed two things worth knowing before reading further. Filters now
| live in a Metronic menu and are read on Apply rather than on change, and the
| create form is also the edit form.
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

    // Kind, status, issue date and page size live in the shared filter menu now
    // — see js/admin/filters.js. It is created further down, once loadList()
    // exists for it to call.
    var filters = null;

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

        // Rebuilt rather than patched, so clearing the search box or resetting
        // a filter actually drops the parameter instead of leaving a stale one.
        ['q', 'type', 'status', 'issued_from', 'issued_to', 'per_page', 'page'].forEach(function (key) {
            params.delete(key);
        });

        if (search && search.value.trim()) params.set('q', search.value.trim());

        // changed() returns only the filters that are NOT at their default, so
        // the address bar stays readable and a shared link carries exactly what
        // somebody chose.
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

    /* ---------------------------------------------------------------------
       The filter menu

       Roughly sixty lines used to live here: the Select2 wiring, the reset, the
       badge count, the defaults table. Phase 29 moved all of it into
       Shunno.filterBar(), which does the same job for every register in the
       panel — including the two things this file got right and the others did
       not, the Apply button that avoids three page loads for three choices and
       the badge that tells a filtered view from a full one.

       Two things worth keeping from the old comment, because they are the
       reasons the shared version is built the way it is. Nothing listens for
       change, so Select2 is safe here; and Reset dispatches a NATIVE change
       event rather than jQuery's .trigger(), because a native one reaches both
       Select2 and any plain listener while .trigger() reaches only the first.
       --------------------------------------------------------------------- */

    filters = Shunno.filterBar({
        root: document.getElementById('vouchers-filter'),
        onApply: function () {
            loadList({ page: null });
        }
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
       Creating and editing

       One form for both. The fields are the same and the rules are the same
       bar a uniqueness ignore, so two forms would mean every future field had
       to be added twice — and the missing second copy would only be noticed by
       somebody who edited a voucher and lost what they had typed.
       ===================================================================== */

    var formModalEl = document.getElementById('voucher-form-modal');
    var form = document.getElementById('voucher-form');
    var formSave = document.getElementById('voucher-form-save');
    var formSaveLabel = document.getElementById('voucher-form-save-label');
    var formTitle = document.getElementById('voucher-form-title');
    var sentWarning = document.getElementById('voucher-form-sent-warning');
    var createButton = document.getElementById('voucher-create');

    var codeField = form ? form.querySelector('[name="code"]') : null;
    var codeFeedback = document.getElementById('voucher-code-feedback');
    var codeSuggest = document.getElementById('voucher-code-suggest');

    // The code the form opened on, so the availability check does not report a
    // voucher's own code as taken by itself. Empty while creating.
    var editingCode = '';

    // What the last check said. Read on submit so an already-known clash is
    // shown at once instead of after a round trip.
    var lastCodeVerdict = null;

    /* ---------------------------------------------------------------------
       Code availability

       Advisory, and deliberately so. The unique index on the column is what
       actually prevents two vouchers sharing a code, and VoucherService turns
       the collision that lands between this answer and the insert into a
       readable message. This exists only so nobody fills in a whole form and
       then discovers the clash.
       --------------------------------------------------------------------- */

    var codeTyping = null;

    function paintCode(state, message) {
        if (!codeFeedback) return;

        codeFeedback.className = 'form-text' +
            (state === 'ok' ? ' text-success' : state === 'bad' ? ' text-danger' : '');
        codeFeedback.textContent = message;
    }

    function checkCode() {
        if (!codeField) return;

        var code = (codeField.value || '').trim();
        lastCodeVerdict = null;

        if (code === '') {
            paintCode('', 'Letters, numbers and hyphens. 4–24 characters. Checked as you type.');
            return;
        }

        paintCode('', 'Checking…');

        var url = config.checkCodeUrl +
            '?code=' + encodeURIComponent(code) +
            '&ignore=' + encodeURIComponent(editingCode);

        Shunno.request(url)
            .then(function (payload) {
                // A slower reply for an older keystroke must not overwrite a
                // newer one. Compared against what is in the box right now.
                if ((codeField.value || '').trim().toUpperCase() !== code.toUpperCase()) return;

                lastCodeVerdict = payload.data;
                paintCode(payload.data.available ? 'ok' : 'bad', payload.data.message);
            })
            .catch(function (error) {
                if (error.handled) return;

                // A failed check must not block the form. The server decides at
                // save time either way.
                paintCode('', 'Could not check that code just now. It will be checked when you save.');
            });
    }

    if (codeField) {
        codeField.addEventListener('input', function () {
            var start = this.selectionStart;
            this.value = this.value.toUpperCase();
            this.setSelectionRange(start, start);

            window.clearTimeout(codeTyping);
            codeTyping = window.setTimeout(checkCode, 400);
        });
    }

    if (codeSuggest) {
        codeSuggest.addEventListener('click', function () {
            Shunno.busy(codeSuggest, true);

            // An empty code asks the server for one. Kept server-side so the
            // generated alphabet stays in one place rather than being
            // reimplemented here and drifting.
            Shunno.request(config.checkCodeUrl + '?code=')
                .then(function (payload) {
                    codeField.value = payload.data.suggestion;
                    checkCode();
                })
                .catch(function (error) {
                    if (error.handled) return;
                    Shunno.toast('error', error.message);
                })
                .finally(function () {
                    Shunno.busy(codeSuggest, false);
                });
        });
    }

    /* ---------------------------------------------------------------------
       Opening the form
       --------------------------------------------------------------------- */

    /**
     * Flatpickr hides the real input and shows its own beside it, so setting
     * .value on the field updates nothing a person can see. The instance has to
     * be told.
     */
    function setDateField(name, value) {
        var field = form.querySelector('[name="' + name + '"]');
        if (!field) return;

        if (field._flatpickr) {
            value ? field._flatpickr.setDate(value, false) : field._flatpickr.clear();
        } else {
            field.value = value || '';
        }
    }

    function openForm(mode, data) {
        Shunno.clearErrors(form);
        form.reset();

        editingCode = mode === 'edit' ? data.code : '';
        lastCodeVerdict = null;

        if (mode === 'edit') {
            form.action = data.update_url;
            formTitle.textContent = 'Edit ' + data.code;
            formSaveLabel.textContent = 'Save changes';

            Shunno.fill(form, {
                code: data.code,
                value: data.value,
                workshop_id: data.workshop_id || '',
                issued_to_name: data.issued_to_name || '',
                issued_to_email: data.issued_to_email || '',
                note: data.note || ''
            });

            setDateField('expires_at', data.expires_at);
            sentWarning.classList.toggle('d-none', !data.was_emailed);
        } else {
            form.action = config.storeUrl;
            formTitle.textContent = 'New gift voucher';
            formSaveLabel.textContent = 'Create it';

            setDateField('expires_at', null);
            sentWarning.classList.add('d-none');

            /*
             | The server-rendered suggestion is wiped by reset(), so it is put
             | back here — and cleared after each successful create, because
             | offering the same generated code twice would open the form on one
             | that is now taken.
             */
            if (codeField) {
                codeField.value = codeField.defaultValue;
                if (!codeField.value && codeSuggest) codeSuggest.click();
            }

            // Select2 keeps its own state and a native reset() does not reach
            // it, so the previous choice would survive into the next voucher.
            Shunno.syncSelects(form);
        }

        checkCode();
        Shunno.modal(formModalEl).show();
    }

    if (createButton && form) {
        createButton.addEventListener('click', function () {
            openForm('create');
        });
    }

    // Delegated: the trigger sits in the table, which is replaced wholesale,
    // and in the drawer, which is rendered fresh on every open.
    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-action="edit-voucher"]');
        if (!trigger || !form) return;

        event.preventDefault();

        Shunno.request(trigger.dataset.url)
            .then(function (payload) {
                openForm('edit', payload.data);
            })
            .catch(function (error) {
                if (error.handled) return;
                Shunno.toast('error', error.message);
            });
    });

    if (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            // A clash the browser already knows about is shown without asking
            // the server again. Anything else — including a check that never
            // came back — goes through and is decided server-side.
            if (lastCodeVerdict && lastCodeVerdict.available === false) {
                Shunno.showErrors(form, { code: [lastCodeVerdict.message] });
                return;
            }

            Shunno.clearErrors(form);
            Shunno.busy(formSave, true);

            Shunno.request(form.action, { method: 'POST', body: new FormData(form) })
                .then(function (payload) {
                    Shunno.modal(formModalEl).hide();
                    Shunno.toast('success', payload.message);

                    listEl.innerHTML = payload.data.list.html;

                    // That suggestion has now been spent. See openForm().
                    if (codeField && !editingCode) codeField.defaultValue = '';

                    // An edit returns the redrawn panel; a create does not,
                    // because there is no open drawer to redraw.
                    if (payload.data.html && drawerBody) {
                        drawerBody.innerHTML = payload.data.html;
                        drawerTitle.textContent = payload.data.code;
                    }
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
       Deleting

       A confirmation rather than a modal with a reason box, which is the
       difference between this and cancelling. Cancelling records WHY, because
       the row survives and somebody turned away at the counter is owed the
       explanation. Deleting leaves nothing for a reason to be written on.
       ===================================================================== */

    document.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-action="delete-voucher"]');
        if (!trigger) return;

        event.preventDefault();

        var url = trigger.dataset.url;
        var code = trigger.dataset.code;

        Shunno.confirm({
            title: 'Delete ' + code + '?',
            text: 'This removes the voucher completely and leaves no record of it. ' +
                'If somebody may be holding it, cancel it instead — that keeps the row and the reason.',
            confirmText: 'Yes, delete it'
        }).then(function (confirmed) {
            if (!confirmed) return;

            return Shunno.request(url, { method: 'DELETE' })
                .then(function (payload) {
                    Shunno.toast('success', payload.message);
                    listEl.innerHTML = payload.data.list.html;

                    // The drawer, if open, is showing a voucher that no longer
                    // exists.
                    if (drawerEl) Shunno.modal(drawerEl).hide();
                })
                .catch(function (error) {
                    if (error.handled) return;
                    Shunno.toast('error', error.message);
                });
        });
    });

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
