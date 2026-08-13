/*
|------------------------------------------------------------------------------
| Visitors
|------------------------------------------------------------------------------
| Depends on js/admin/shunno.js.
|
| First admin list with server-side search and paging. The container swap is the
| same idea as workshops — Blade owns the markup — but the filters travel as
| query parameters and the paginator links are intercepted rather than followed.
*/
(function () {
    'use strict';

    var config = window.VisitorsConfig || {};

    var listEl = document.getElementById('visitors-list');
    var search = document.getElementById('visitors-search');
    var status = document.getElementById('visitors-status');

    if (!listEl) return;

    var detailModalEl = document.getElementById('visitor-modal');
    var detailBody = document.getElementById('visitor-modal-body');
    var detailTitle = document.getElementById('visitor-modal-title');

    var editForm = document.getElementById('visitor-form');
    var editModalEl = document.getElementById('visitor-edit-modal');
    var editSave = document.getElementById('visitor-save');

    /* =====================================================================
       Fetching the list
       ===================================================================== */

    var listRequest = 0;

    function currentQuery(extra) {
        var params = new URLSearchParams();

        if (search && search.value.trim()) params.set('q', search.value.trim());
        if (status && status.value !== 'all') params.set('status', status.value);
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
            // Long enough that a normal typing rhythm produces one request,
            // short enough that the list does not feel stuck.
            typingTimer = window.setTimeout(function () {
                loadList();
            }, 350);
        });

        // Enter should not submit anything; there is no form to submit.
        search.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter') return;
            event.preventDefault();
            window.clearTimeout(typingTimer);
            loadList();
        });
    }

    if (status) {
        status.addEventListener('change', function () {
            loadList();
        });
    }

    /* =====================================================================
       Pagination and row actions
       ===================================================================== */

    listEl.addEventListener('click', function (event) {
        var link = event.target.closest('[data-visitors-pagination] a.page-link');

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

        if (trigger.dataset.action === 'view-visitor') {
            openDetail(trigger.dataset.url);
        } else if (trigger.dataset.action === 'edit-visitor') {
            openEdit(trigger.dataset.url);
        }
    });

    /* =====================================================================
       Detail drawer
       ===================================================================== */

    function openDetail(url) {
        if (!detailModalEl) return;

        detailBody.innerHTML = '<div class="text-center text-muted py-10">Loading…</div>';
        detailTitle.textContent = 'Visitor';
        Shunno.modal(detailModalEl).show();

        Shunno.request(url)
            .then(function (payload) {
                detailBody.innerHTML = payload.data.html;
                detailTitle.textContent = payload.data.name;
            })
            .catch(function (error) {
                if (error.handled) return;
                detailBody.innerHTML =
                    '<div class="text-center text-danger py-10">Could not load this visitor.</div>';
                Shunno.toast('error', error.message);
            });
    }

    /* =====================================================================
       Edit
       ===================================================================== */

    // Manager holds visitors.update, but a role without it never receives the
    // modal, so everything below is guarded.
    function openEdit(url) {
        if (!editForm || !editModalEl) return;

        Shunno.request(url)
            .then(function (payload) {
                var data = payload.data;

                editForm.reset();
                Shunno.clearErrors(editForm);
                editForm.action = data.update_url;
                Shunno.fill(editForm, data);

                Shunno.modal(editModalEl).show();
            })
            .catch(function (error) {
                if (!error.handled) Shunno.toast('error', error.message);
            });
    }

    if (editForm) {
        editForm.addEventListener('submit', function (event) {
            event.preventDefault();
            Shunno.clearErrors(editForm);
            Shunno.busy(editSave, true);

            var body = new FormData(editForm);
            // Unticked checkboxes never reach FormData.
            body.set('is_active', editForm.querySelector('[name="is_active"]').checked ? '1' : '0');

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
