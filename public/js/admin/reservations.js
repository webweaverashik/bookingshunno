/*
|------------------------------------------------------------------------------
| Reservations
|------------------------------------------------------------------------------
| Depends on js/admin/shunno.js.
|
| Same shape as visitors.js: filters travel as query parameters, the server
| returns rendered HTML, and one container is swapped. Nothing here builds
| markup — including the time options and the decision buttons, both of which
| come back from the server, because which ones exist is a policy and
| availability decision rather than a display one.
*/
(function () {
    'use strict';

    var config = window.ReservationsConfig || {};

    var listEl = document.getElementById('reservations-list');
    if (!listEl) return;

    var search = document.getElementById('reservations-search');

    // Status, date range, session and page size all live in the shared filter
    // menu now — see js/admin/filters.js and the filter-bar partial. It is
    // created further down, after loadList() exists for it to call.
    var filters = null;
    var lastRange = null;

    // Sorting is server-side, so the current sort lives here rather than in the
    // table. Seeded from the URL so a shared link or a refresh reproduces it.
    var sortState = (function () {
        var params = new URLSearchParams(window.location.search);
        return { sort: params.get('sort') || '', dir: params.get('dir') || '' };
    })();

    var detailModalEl = document.getElementById('reservation-modal');
    var detailBody = document.getElementById('reservation-modal-body');
    var detailTitle = document.getElementById('reservation-modal-title');

    var editForm = document.getElementById('reservation-form');
    var editModalEl = document.getElementById('reservation-edit-modal');
    var editBody = document.getElementById('reservation-form-body');
    var editSave = document.getElementById('reservation-save');
    var editTitle = document.getElementById('reservation-edit-title');

    var decisionForm = document.getElementById('reservation-decision-form');
    var decisionModalEl = document.getElementById('reservation-decision-modal');
    var decisionTitle = document.getElementById('reservation-decision-title');
    var decisionPrompt = document.getElementById('reservation-decision-prompt');
    var decisionNote = document.getElementById('reservation-decision-note');
    var decisionSave = document.getElementById('reservation-decision-save');
    var decisionOverrideWrap = document.getElementById('reservation-decision-override-wrap');
    var decisionOverride = document.getElementById('reservation-decision-override');

    // Set when the edit form is opened; used to refresh the time options.
    var slotsUrl = null;

    /* =====================================================================
       Fetching the list
       ===================================================================== */

    var listRequest = 0;

    // changed() returns only the filters that are NOT at their default, so the
    // address bar stays readable and the server's own defaults stand where
    // nothing was chosen.
    function currentQuery(extra) {
        var params = new URLSearchParams(filters ? filters.changed() : {});

        if (search && search.value.trim()) params.set('q', search.value.trim());
        if (sortState.sort) params.set('sort', sortState.sort);
        if (sortState.dir) params.set('dir', sortState.dir);
        if (extra && extra.page) params.set('page', extra.page);

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
                window.history.replaceState(null, '', url);
            })
            .catch(function (error) {
                if (!error.handled) Shunno.toast('error', error.message);
            })
            .then(function () {
                if (ticket === listRequest) listEl.classList.remove('opacity-50');
            });
    }

    /* =====================================================================
       Filters
       ===================================================================== */

    if (search) {
        var typingTimer = null;

        search.addEventListener('input', function () {
            window.clearTimeout(typingTimer);
            typingTimer = window.setTimeout(function () {
                loadList();
            }, 350);
        });

        search.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            window.clearTimeout(typingTimer);
            loadList();
        });
    }

    filters = Shunno.filterBar({
        root: document.getElementById('reservations-filter'),

        onApply: function (changed, all) {
            /*
             | Changing the date range changes which direction is useful —
             | forward-looking ranges read earliest first, history reads latest
             | first — so an explicit sort DIRECTION is dropped and the server's
             | default for the new range applies. An explicit column choice is
             | kept: that is a preference about what to sort by rather than
             | about which end of the list to start from.
             */
            if (all.range !== lastRange) {
                lastRange = all.range;
                sortState.dir = '';
            }

            loadList();
        }
    });

    lastRange = filters ? filters.values().range : null;

    /* =====================================================================
       Pagination and row actions
       ===================================================================== */

    listEl.addEventListener('click', function (event) {
        // Sort headers. The server rendered them knowing the current sort, so
        // each one already carries the direction it should produce next.
        var sortHeader = event.target.closest('[data-sort]');

        if (sortHeader) {
            event.preventDefault();
            sortState.sort = sortHeader.dataset.sort;
            sortState.dir = sortHeader.dataset.dir;
            loadList();
            return;
        }

        var link = event.target.closest('[data-reservations-pagination] a.page-link');

        if (link) {
            event.preventDefault();

            var page = new URL(link.href, window.location.origin).searchParams.get('page');
            if (page) {
                loadList({ page: page }).then(function () {
                    listEl.scrollIntoView({ block: 'start', behavior: 'smooth' });
                });
            }
            return;
        }

        var trigger = event.target.closest('[data-action]');
        if (!trigger) return;

        event.preventDefault();

        if (trigger.dataset.action === 'view-reservation') {
            openDetail(trigger.dataset.url);
        } else if (trigger.dataset.action === 'edit-reservation') {
            openEdit(trigger.dataset.url);
        }
    });

    /* =====================================================================
       Detail drawer
       ===================================================================== */

    function openDetail(url) {
        if (!detailModalEl) return;

        detailBody.innerHTML = '<div class="text-center text-muted py-10">Loading…</div>';
        detailTitle.textContent = 'Reservation';
        Shunno.modal(detailModalEl).show();

        Shunno.request(url)
            .then(function (payload) {
                detailBody.innerHTML = payload.data.html;
                detailTitle.textContent = payload.data.reference;
            })
            .catch(function (error) {
                if (error.handled) return;
                detailBody.innerHTML =
                    '<div class="text-center text-danger py-10">Could not load this reservation.</div>';
                Shunno.toast('error', error.message);
            });
    }

    /* =====================================================================
       Decisions
       ===================================================================== */

    // The drawer's buttons carry their own wording and rules. Reading them here
    // rather than hard-coding five variants means the server decides both what
    // is offered and how it is described.
    if (detailBody) {
        detailBody.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-action="decide"]');
            if (!trigger || !decisionForm) return;

            event.preventDefault();
            openDecision(trigger.dataset);
        });
    }

    function openDecision(data) {
        Shunno.clearErrors(decisionForm);

        decisionForm.action = data.url;
        decisionForm.dataset.required = data.required;

        decisionTitle.textContent = data.title;
        decisionPrompt.textContent = data.prompt;
        decisionNote.value = '';
        decisionNote.placeholder = data.placeholder || '';

        decisionSave.querySelector('.indicator-label').textContent = data.confirm;

        // Only revealed for an action that says it applies — which today means
        // approving into a slot that is no longer free, for someone who holds
        // the ability.
        var wantsOverride = data.override === '1';
        decisionOverrideWrap.hidden = !wantsOverride;
        decisionOverride.checked = false;

        // The confirm button borrows the tone of the button that opened it, so
        // a destructive decision does not arrive looking routine.
        decisionSave.className = 'btn ' + (data.tone && data.tone.indexOf('danger') !== -1
            ? 'btn-danger'
            : 'btn-primary');

        Shunno.modal(decisionModalEl).show();
        window.setTimeout(function () { decisionNote.focus(); }, 250);
    }

    if (decisionForm) {
        decisionForm.addEventListener('submit', function (event) {
            event.preventDefault();

            // A local check only; the server requires it again and owns the
            // wording of the refusal.
            if (decisionForm.dataset.required === '1' && decisionNote.value.trim() === '') {
                Shunno.showErrors(decisionForm, { note: ['Please write something here first.'] });
                decisionNote.focus();
                return;
            }

            Shunno.clearErrors(decisionForm);
            Shunno.busy(decisionSave, true);

            var body = new FormData(decisionForm);
            body.set('override', decisionOverride.checked ? '1' : '0');

            // Filters travel with the decision so the refreshed list comes back
            // under the same filters the admin was using — and a request that
            // has just left the "still open" filter correctly disappears.
            var query = currentQuery();

            Shunno.request(decisionForm.action + (query ? '?' + query : ''), {
                method: 'POST',
                body: body
            })
                .then(function (payload) {
                    listEl.innerHTML = payload.data.list.html;
                    detailBody.innerHTML = payload.data.detail;

                    Shunno.modal(decisionModalEl).hide();
                    Shunno.toast('success', payload.message);
                })
                .catch(function (error) {
                    if (error.handled) return;

                    if (error.status === 422) {
                        Shunno.showErrors(decisionForm, error.errors);
                        return;
                    }

                    // 409: somebody else decided it first, or the lifecycle
                    // refuses the move. Worth a full stop rather than a toast.
                    if (error.status === 409) {
                        Shunno.modal(decisionModalEl).hide();
                        Shunno.toast('warning', error.message);
                        loadList();
                        return;
                    }

                    Shunno.toast('error', error.message);
                })
                .then(function () {
                    Shunno.busy(decisionSave, false);
                });
        });
    }

    /* =====================================================================
       Edit
       ===================================================================== */

    // A role without reservations.update never receives the modal, so
    // everything below is guarded.
    function openEdit(url) {
        if (!editForm || !editModalEl) return;

        Shunno.request(url)
            .then(function (payload) {
                var data = payload.data;

                Shunno.clearErrors(editForm);
                editForm.action = data.update_url;
                editBody.innerHTML = data.html;

                // Injected markup, so the DOM-ready scan in shunno.js never saw
                // it. Without this the date field is a plain text box.
                Shunno.datepickers(editBody);
                editTitle.textContent = data.editable
                    ? 'Edit ' + data.reference
                    : 'Notes for ' + data.reference;

                // Derived from the edit URL rather than sent separately: the
                // two always sit side by side and a mismatch would be silent.
                slotsUrl = url.replace(/\/edit$/, '/slots');

                Shunno.modal(editModalEl).show();
            })
            .catch(function (error) {
                if (!error.handled) Shunno.toast('error', error.message);
            });
    }

    /* Date change → re-render the time options on the server. */
    if (editBody) {
        editBody.addEventListener('change', function (event) {
            if (event.target.id !== 'reservation-date' || !slotsUrl) return;

            var select = document.getElementById('reservation-time');
            if (!select) return;

            var date = event.target.value;
            if (!date) return;

            select.disabled = true;

            Shunno.request(slotsUrl + '?date=' + encodeURIComponent(date))
                .then(function (payload) {
                    select.innerHTML = payload.data.html;
                })
                .catch(function (error) {
                    if (!error.handled) Shunno.toast('error', error.message);
                })
                .then(function () {
                    select.disabled = false;
                });
        });
    }

    if (editForm) {
        editForm.addEventListener('submit', function (event) {
            event.preventDefault();
            Shunno.clearErrors(editForm);
            Shunno.busy(editSave, true);

            var body = new FormData(editForm);

            // Unticked checkboxes never reach FormData, and the override switch
            // is absent entirely for anyone without the ability.
            var override = editForm.querySelector('[name="override"]');
            body.set('override', override && override.checked ? '1' : '0');

            var query = currentQuery();

            Shunno.request(editForm.action + (query ? '?' + query : ''), {
                method: 'POST',
                body: body
            })
                .then(function (payload) {
                    listEl.innerHTML = payload.data.list.html;

                    // The drawer may be open behind the edit modal; keeping it
                    // in step avoids it showing the pre-edit figures.
                    if (detailBody && detailBody.innerHTML.trim() !== '') {
                        detailBody.innerHTML = payload.data.detail;
                    }

                    Shunno.modal(editModalEl).hide();
                    Shunno.toast('success', payload.message);
                })
                .catch(function (error) {
                    if (error.handled) return;
                    if (error.status === 422) {
                        Shunno.showErrors(editForm, error.errors);
                        Shunno.toast('warning', 'Please correct the highlighted fields.');
                    } else {
                        Shunno.toast('error', error.message);
                    }
                })
                .then(function () {
                    Shunno.busy(editSave, false);
                });
        });
    }
})();
