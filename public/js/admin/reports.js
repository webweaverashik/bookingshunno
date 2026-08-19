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
    var windowEl = document.querySelector('[data-report-window]');

    // The dates, the period, the status and the page size all live in the
    // shared filter menu now — see js/admin/filters.js. Created further down,
    // once load() exists for it to call.
    var filters = null;

    // Guards against a slow response landing after a faster later one and
    // overwriting it — the same counter pattern the other registers use.
    var listRequest = 0;

    /* =====================================================================
       Reading the filters
       ===================================================================== */

    /*
     | values(), not changed().
     |
     | On a register, sending only what differs from the default keeps the URL
     | short and lets the server's defaults stand. Here the dates ARE the
     | report: leaving them out because they match what the page opened with
     | would hand the server no window at all and get back its default month.
     | Every filter goes on every request.
     */
    function params(extra) {
        var query = new URLSearchParams();
        var current = filters ? filters.values() : {};

        extra = extra || {};

        Object.keys(current).forEach(function (key) {
            /*
             | 'range' is the menu's own control, not a filter the server
             | keeps — a named period is resolved once into dates and the
             | dates are what travel from then on. It is passed explicitly
             | through `extra` when it is actually being requested.
             */
            if (key === 'range') return;

            if (current[key] !== '') query.set(key, current[key]);
        });

        /*
         | When a named period is being requested, the from/to fields still hold
         | the PREVIOUS window — they are only updated once the response comes
         | back. Sending them alongside would be two answers to the same
         | question, so they are dropped and the server's resolution wins.
         */
        if (extra.range) {
            query.delete('from');
            query.delete('to');
        }

        Object.keys(extra).forEach(function (key) {
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
                // The toolbar follows the server, never the other way round.
                applyRange(payload.data.range);

                // Keep the address bar honest so the view can be bookmarked or
                // sent to somebody. replaceState, not pushState: a filter change
                // is not a new page and should not fill the back button.
                var url = new URLSearchParams(query);
                url.delete('range');

                if (payload.data.range) {
                    url.set('from', payload.data.range.from);
                    url.set('to', payload.data.range.to);
                }

                // replaceState, not pushState: a filter change is not a new page
                // and should not fill the back button. The resolved dates go in
                // rather than the range key, so a copied URL keeps meaning the
                // same window next month.
                window.history.replaceState({}, '', window.location.pathname + '?' + url.toString());
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
       The filter menu
       =====================================================================
       Apply reads the whole set. A named period — "last month" — is sent as a
       KEY and the server decides what it means, then sends the resolved dates
       back for applyRange() to display.

       No date arithmetic in the browser, deliberately. An earlier version built
       both dates here, which made the meaning of a range depend on the clock
       and timezone of whichever machine had the page open, and left the picker
       and the table able to disagree about which month was on screen.
    */

    filters = Shunno.filterBar({
        root: document.getElementById('reports-filter'),

        onApply: function (changed, all) {
            if (all.range && all.range !== 'custom') {
                load({ page: 1, range: all.range });
                return;
            }

            load({ page: 1 });
        }
    });

    /**
     * Write the range the SERVER resolved back into the menu and the heading.
     *
     * setDate's second argument is false so this does NOT fire change — load()
     * has already run, and letting the write trigger another would loop.
     *
     * The DEFAULTS move with the values, and that is the point of doing it here
     * rather than leaving the fields alone. A report always has a range, so
     * counting it as an active filter would leave the badge permanently reading
     * two; keeping the default in step means the badge counts what somebody has
     * narrowed BEYOND the window on screen, which is the useful reading.
     */
    function applyRange(range) {
        if (!range) return;

        ['from', 'to'].forEach(function (key) {
            var field = document.querySelector('[data-filter-field="' + key + '"]');
            if (!field) return;

            field.dataset.filterDefault = range[key];

            if (field._flatpickr) {
                field._flatpickr.setDate(range[key], false);
            } else {
                field.value = range[key];
            }
        });

        // from_label / to_label are formatted by PHP — "14 Aug 2026" — so the
        // heading never contains a date this file has formatted itself.
        if (windowEl && range.from_label) {
            windowEl.textContent = range.from_label + ' \u2013 ' + range.to_label;
        }

        if (filters) filters.refresh();
    }

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

    /* =====================================================================
       Export
       =====================================================================
       AJAX rather than a link, so a refusal arrives as a message instead of a
       downloaded file containing an error page.

       PHASE 29 moved the download itself into Shunno.download(): the same
       routine now serves the index registers, and two copies of it would drift
       the first time one of them learned something the other did not. This is
       left with the one thing that is specific to this page — which filters the
       export should carry.
    */

    document.querySelectorAll('[data-row-export]').forEach(function (item) {
        item.addEventListener('click', function (event) {
            event.preventDefault();

            var format = this.getAttribute('data-row-export');
            var query = params({ format: format });

            Shunno.download(
                config.exportUrl + '?' + query.toString(),
                format,
                document.getElementById('report-export')
            );
        });
    });

    /* =====================================================================
       Clearing a log
       ===================================================================== */

    document.querySelectorAll('[data-log-clear]').forEach(function (item) {
        item.addEventListener('click', function (event) {
            event.preventDefault();

            var days = this.getAttribute('data-log-clear');
            var everything = days === '0';

            Shunno.confirm({
                title: everything ? 'Clear the entire log?' : 'Clear entries older than ' + days + ' days?',
                text: everything
                    ? 'Every entry in this log will be deleted. Successful payment transactions are never removed — only failed attempts.'
                    : 'Entries older than that will be deleted. This cannot be undone.',
                confirmText: 'Yes, clear',
                danger: true
            }).then(function (ok) {
                if (!ok) return;

                var body = new FormData();
                body.append('older_than_days', days);

                Shunno.request(config.clearUrl, { method: 'POST', body: body })
                    .then(function (payload) {
                        Shunno.toast('success', payload.message);
                        load({ page: 1 });
                    })
                    .catch(function (error) {
                        if (!error.handled) Shunno.toast('error', error.message);
                    });
            });
        });
    });
})();
