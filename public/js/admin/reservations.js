/*
|------------------------------------------------------------------------------
| Reservations
|------------------------------------------------------------------------------
| Depends on js/admin/shunno.js.
|
| Same shape as visitors.js: filters travel as query parameters, the server
| returns rendered HTML, and one container is swapped. Nothing here builds
| markup — including the time options, which come back from the server whenever
| the date changes, because which times exist is an availability decision.
*/
(function () {
    'use strict';

    var config = window.ReservationsConfig || {};

    var listEl = document.getElementById('reservations-list');
    if (!listEl) return;

    var search = document.getElementById('reservations-search');
    var status = document.getElementById('reservations-status');
    var range = document.getElementById('reservations-range');
    var workshop = document.getElementById('reservations-workshop');

    var detailModalEl = document.getElementById('reservation-modal');
    var detailBody = document.getElementById('reservation-modal-body');
    var detailTitle = document.getElementById('reservation-modal-title');

    var editForm = document.getElementById('reservation-form');
    var editModalEl = document.getElementById('reservation-edit-modal');
    var editBody = document.getElementById('reservation-form-body');
    var editSave = document.getElementById('reservation-save');
    var editTitle = document.getElementById('reservation-edit-title');

    // Set when the edit form is opened; used to refresh the time options.
    var slotsUrl = null;

    /* =====================================================================
       Fetching the list
       ===================================================================== */

    var listRequest = 0;

    function currentQuery(extra) {
        var params = new URLSearchParams();

        if (search && search.value.trim()) params.set('q', search.value.trim());
        if (status) params.set('status', status.value);
        if (range) params.set('range', range.value);
        if (workshop && workshop.value !== 'all') params.set('workshop', workshop.value);
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

    [status, range, workshop].forEach(function (select) {
        if (!select) return;
        select.addEventListener('change', function () {
            loadList();
        });
    });

    /* =====================================================================
       Pagination and row actions
       ===================================================================== */

    listEl.addEventListener('click', function (event) {
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

            // Filters travel with the update so the refreshed list comes back
            // on the same page and under the same filters the admin was using.
            var query = currentQuery();

            Shunno.request(editForm.action + (query ? '?' + query : ''), {
                method: 'POST',
                body: body
            })
                .then(function (payload) {
                    listEl.innerHTML = payload.data.html;
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
