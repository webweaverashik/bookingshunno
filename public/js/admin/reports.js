/*
|------------------------------------------------------------------------------
| Reports
|------------------------------------------------------------------------------
| Depends on js/admin/shunno.js.
|
| One job: keep the table, the summary tiles and the download link in step with
| the filters, without a page reload. Blade renders, JS swaps — the server sends
| back rendered markup and this file puts it where it goes. Nothing here formats
| money, counts a row, or decides what a range means; all of that would be a
| second opinion about figures the server has already settled.
|
| The quick-range buttons are the one exception, and they only set two date
| inputs. The server still resolves, validates and clamps whatever they produce
| — see ReportController::filters() — so a tampered date reaches nothing.
*/
(function () {
    'use strict';

    var config = window.ReportsConfig || {};

    var listEl = document.getElementById('report-list');
    if (!listEl) return;

    var summaryEl = document.getElementById('report-summary');
    var fromEl = document.getElementById('report-from');
    var toEl = document.getElementById('report-to');
    var statusEl = document.getElementById('report-status');
    var perPageEl = document.getElementById('report-per-page');
    var exportEl = document.getElementById('report-export');

    // Guards against a slow response landing after a faster later one and
    // overwriting it — the same counter pattern the other registers use.
    var listRequest = 0;

    /* =====================================================================
       Reading the filters
       ===================================================================== */

    function params(extra) {
        var query = new URLSearchParams();

        if (fromEl && fromEl.value) query.set('from', fromEl.value);
        if (toEl && toEl.value) query.set('to', toEl.value);
        if (statusEl) query.set('status', statusEl.value);
        if (perPageEl) query.set('per_page', perPageEl.value);

        Object.keys(extra || {}).forEach(function (key) {
            query.set(key, extra[key]);
        });

        return query;
    }

    /* =====================================================================
       Loading
       ===================================================================== */

    function load(extra) {
        var token = ++listRequest;
        var query = params(extra);

        listEl.classList.add('opacity-50');

        Shunno.request(config.listUrl + '?' + query.toString())
            .then(function (payload) {
                if (token !== listRequest) return;

                listEl.innerHTML = payload.data.html;

                if (summaryEl && payload.data.summary) {
                    summaryEl.innerHTML = payload.data.summary;
                }

                // The download must carry what is on screen, not what was on
                // screen when the page loaded.
                if (exportEl && payload.data.export) {
                    exportEl.setAttribute('href', payload.data.export);
                }

                // Keep the address bar honest so the view can be bookmarked or
                // sent to somebody. replaceState, not pushState: a filter change
                // is not a new page and should not fill the back button.
                window.history.replaceState({}, '', window.location.pathname + '?' + query.toString());
            })
            .catch(function (error) {
                if (token !== listRequest || error.handled) return;
                Shunno.toast('error', error.message);
            })
            .then(function () {
                if (token === listRequest) listEl.classList.remove('opacity-50');
            });
    }

    /* =====================================================================
       Filter bindings
       ===================================================================== */

    // Shunno.onChange, not addEventListener. The status filter is a Select2 and
    // Select2 announces a selection through jQuery's .trigger('change'), which
    // a native listener never sees — the helper binds through jQuery for those
    // and natively for everything else.
    [fromEl, toEl, statusEl, perPageEl].forEach(function (el) {
        Shunno.onChange(el, function () {
            load({ page: 1 });
        });
    });

    /* =====================================================================
       Date pickers
       ===================================================================== */

    // Flatpickr replaces each field with a readable alt input and hides the
    // real one, so the values read in params() are still Y-m-d. Bound after the
    // listeners above because Flatpickr fires a native change on the original
    // input when a date is chosen, and that is what triggers the reload.
    var pickers = Shunno.datepickers(document, {
        // A range that runs backwards is swapped server-side rather than
        // refused, so nothing here needs to enforce an order. This only stops
        // the obvious mistake before it costs a round trip.
        maxDate: null,
    });

    // Quick-range buttons write to the fields directly, so the pickers have to
    // be told. setDate with the second argument false updates the display
    // WITHOUT firing change — load() is called explicitly straight after, and
    // letting both happen would fire two requests for one click.
    function setRange(fromValue, toValue) {
        if (pickers.length === 2) {
            pickers[0].setDate(fromValue, false);
            pickers[1].setDate(toValue, false);
        } else {
            fromEl.value = fromValue;
            toEl.value = toValue;
        }
    }

    /* =====================================================================
       Quick ranges
       ===================================================================== */

    function iso(date) {
        // Local date parts, not toISOString(): that converts to UTC first, and
        // Dhaka is UTC+6, so "today" becomes yesterday for six hours a day.
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return date.getFullYear() + '-' + month + '-' + day;
    }

    document.querySelectorAll('[data-report-range]').forEach(function (button) {
        button.addEventListener('click', function () {
            var now = new Date();
            var from;
            var to;

            switch (this.getAttribute('data-report-range')) {
                case 'last-month':
                    from = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                    to = new Date(now.getFullYear(), now.getMonth(), 0);
                    break;

                case 'quarter':
                    to = now;
                    from = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 89);
                    break;

                case 'year':
                    from = new Date(now.getFullYear(), 0, 1);
                    to = new Date(now.getFullYear(), 11, 31);
                    break;

                default: // this-month
                    from = new Date(now.getFullYear(), now.getMonth(), 1);
                    to = new Date(now.getFullYear(), now.getMonth() + 1, 0);
            }

            setRange(iso(from), iso(to));
            load({ page: 1 });
        });
    });

    /* =====================================================================
       Paging
       ===================================================================== */

    // Delegated from the container, because the pager markup is replaced on
    // every load and a listener bound to the links themselves would die with
    // the first swap.
    listEl.addEventListener('click', function (event) {
        var link = event.target.closest('[data-report-pager] a');
        if (!link) return;

        event.preventDefault();

        var page = new URL(link.href, window.location.origin).searchParams.get('page');
        if (page) load({ page: page });
    });
})();
