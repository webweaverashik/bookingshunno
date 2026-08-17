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

    // Guards against a slow response landing after a faster later one and
    // overwriting it — the same counter pattern the other registers use.
    var listRequest = 0;

    /* =====================================================================
       Reading the filters
       ===================================================================== */

    function params(extra) {
        var query = new URLSearchParams();
        extra = extra || {};

        /*
         | When a named range is being requested, the from/to inputs still hold
         | the PREVIOUS range — they are only updated once the response comes
         | back. Sending them alongside would be sending two answers to the same
         | question, so they are left out and the server's own resolution wins.
         */
        if (!extra.range) {
            if (fromEl && fromEl.value) query.set('from', fromEl.value);
            if (toEl && toEl.value) query.set('to', toEl.value);
        }
        if (statusEl) query.set('status', statusEl.value);
        if (perPageEl) query.set('per_page', perPageEl.value);

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

    // Attached before anything reads the fields. Shunno.datepickers() guards
    // against double-initialising, which is what previously left these showing
    // a date the table was not filtered by — see the note in shunno.js.
    var pickers = Shunno.datepickers(document);

    function fromPicker() { return pickers[0] || null; }
    function toPicker() { return pickers[1] || null; }

    /**
     * Write the range the SERVER resolved back into the pickers.
     *
     * setDate's second argument is false so this does NOT fire change — load()
     * has already run, and letting the write trigger another would loop.
     */
    function applyRange(range) {
        if (!range) return;

        if (fromPicker()) {
            fromPicker().setDate(range.from, false);
        } else if (fromEl) {
            fromEl.value = range.from;
        }

        if (toPicker()) {
            toPicker().setDate(range.to, false);
        } else if (toEl) {
            toEl.value = range.to;
        }
    }

    /* =====================================================================
       Quick ranges
       =====================================================================
       No date arithmetic here any more. The button posts a KEY and the server
       decides what "last month" means, then sends the resolved dates back for
       applyRange() to display.

       The old version built two dates in the browser, which made the meaning of
       a range depend on the clock and timezone of whichever machine had the
       page open, and left the picker and the table able to disagree.
    */

    document.querySelectorAll('[data-report-range]').forEach(function (button) {
        button.addEventListener('click', function () {
            document.querySelectorAll('[data-report-range]').forEach(function (other) {
                other.classList.toggle('active', other === button);
            });

            load({ page: 1, range: this.getAttribute('data-report-range') });
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

    /* =====================================================================
       Export
       =====================================================================
       AJAX rather than a link, so a refusal arrives as a message instead of a
       downloaded file containing an error page.

       Shunno.request is not used here — it parses JSON, and two of these three
       responses are binary. Raw fetch, then branch on the content type: JSON
       means the server refused (too many rows for PDF, say) and the message is
       worth reading; anything else is the file.
    */

    var FORMAT_LABELS = {
        csv: 'CSV',
        xlsx: 'Excel workbook',
        pdf: 'PDF'
    };

    function downloadExport(format) {
        var query = params({ format: format });
        var trigger = document.getElementById('report-export');

        Shunno.busy(trigger, true);

        /*
         | A blocking dialog, not just a spinner on the button.
         |
         | A PDF of a year of reservations takes seconds — long enough that
         | somebody wonders whether the click registered and clicks again, and
         | the second click builds the entire file a second time. The dialog
         | makes the wait visible and takes the button out of reach while it
         | lasts.
         |
         | The message names the format, because "Preparing your PDF" is the
         | difference between waiting and wondering whether the wrong menu item
         | was hit.
         */
        Shunno.progress(
            'Preparing your ' + (FORMAT_LABELS[format] || 'file'),
            format === 'pdf'
                ? 'Laying out the pages. Larger ranges take a moment.'
                : 'Gathering the rows.'
        );

        fetch(config.exportUrl + '?' + query.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (response) {
                var type = response.headers.get('content-type') || '';

                if (type.indexOf('application/json') !== -1) {
                    return response.json().then(function (payload) {
                        throw { message: payload.message || 'Export failed.' };
                    });
                }

                if (!response.ok) {
                    throw { message: 'Export failed. Please try again.' };
                }

                // The server already worked out the filename, range included.
                // Parsing it back off the header beats rebuilding it here and
                // getting a different answer.
                var disposition = response.headers.get('content-disposition') || '';
                var match = disposition.match(/filename="?([^"]+)"?/);

                return response.blob().then(function (blob) {
                    return { blob: blob, name: match ? match[1] : 'report.' + format };
                });
            })
            .then(function (file) {
                var url = URL.createObjectURL(file.blob);
                var link = document.createElement('a');

                link.href = url;
                link.download = file.name;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                // Released, or the whole file stays in memory until the tab is
                // closed — which for someone exporting a year of reservations
                // several times over is worth caring about.
                setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
            })
            .catch(function (error) {
                Shunno.toast('error', error.message || 'Export failed.');
            })
            .then(function () {
                // In the final then, not in each branch: this runs whether the
                // export succeeded, was refused for being too large, or the
                // connection dropped. A dialog left open after a failure is
                // worse than never showing one, because it says the work is
                // still going.
                Shunno.progressDone();
                Shunno.busy(trigger, false);
            });
    }

    document.querySelectorAll('[data-row-export]').forEach(function (item) {
        item.addEventListener('click', function (event) {
            event.preventDefault();
            downloadExport(this.getAttribute('data-row-export'));
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
