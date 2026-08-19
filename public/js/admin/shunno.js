/*
|------------------------------------------------------------------------------
| Shunno admin — shared AJAX utilities
|------------------------------------------------------------------------------
| Small, deliberately unambitious helpers shared by every admin module. Not a
| framework: no state, no components, no router. Everything speaks the JSON
| envelope fixed in §16 of the brief:
|
|   { success: true,  message, data }
|   { success: false, message, errors }
|
| Loaded once, before any module script.
*/
window.Shunno = (function () {
    'use strict';

    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    /**
     * A session that expired mid-request currently answers admin AJAX with a
     * 302 to the login page rather than a 401, so the redirect is detected by
     * content type as well as by status. Remove the HTML check once IsLoggedIn
     * returns JSON for expectsJson() requests.
     */
    function isSessionLoss(response, contentType) {
        return response.status === 401
            || (response.redirected && contentType.indexOf('text/html') !== -1)
            || (response.status === 200 && contentType.indexOf('text/html') !== -1);
    }

    /**
     * Select2 renders its own markup and only redraws when the underlying
     * <select> emits change. A native dispatch reaches it: Select2 binds with
     * jQuery .on(), which is a real addEventListener underneath.
     */
    function syncSelects(form) {
        form.querySelectorAll('select[data-control="select2"]').forEach(function (select) {
            select.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    /**
     * @returns {Promise<Object>} the parsed envelope
     * @throws  {Object} envelope with .status for non-2xx responses
     */
    function request(url, options) {
        options = options || {};

        var headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrf()
        };

        // FormData sets its own multipart boundary — never set Content-Type.
        if (options.json) {
            headers['Content-Type'] = 'application/json';
        }

        return fetch(url, {
            method: options.method || 'GET',
            headers: headers,
            body: options.body || null,
            credentials: 'same-origin'
        }).then(function (response) {
            var contentType = response.headers.get('content-type') || '';

            if (isSessionLoss(response, contentType)) {
                toast('error', 'Your session has expired. Reloading the sign-in page…');
                setTimeout(function () { window.location.reload(); }, 1200);
                return Promise.reject({ handled: true });
            }

            return response.json()
                .catch(function () { return null; })
                .then(function (payload) {
                    if (!response.ok || !payload || payload.success === false) {
                        return Promise.reject(
                            Object.assign(
                                { message: 'Something went wrong. Please try again.' },
                                payload || {},
                                { status: response.status }
                            )
                        );
                    }
                    return payload;
                });
        }).catch(function (error) {
            if (error && (error.handled || error.status)) {
                return Promise.reject(error);
            }
            return Promise.reject({
                status: 0,
                message: 'Could not reach the server. Check your connection and try again.'
            });
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Validation errors
    |--------------------------------------------------------------------------
    */

    function clearErrors(form) {
        form.querySelectorAll('.is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
        });
        form.querySelectorAll('[data-error-for]').forEach(function (el) {
            el.textContent = '';
        });
    }

    /**
     * Laravel keys errors by field name, including dotted paths. The matching
     * message element is <div data-error-for="field"></div>.
     */
    function showErrors(form, errors) {
        clearErrors(form);
        if (!errors) return;

        var first = null;

        Object.keys(errors).forEach(function (field) {
            var input = form.querySelector('[name="' + field + '"]')
                || form.querySelector('[name="' + field + '[]"]');
            var slot = form.querySelector('[data-error-for="' + field + '"]');

            if (input) {
                input.classList.add('is-invalid');
                if (!first) first = input;
            }
            if (slot) {
                slot.textContent = Array.isArray(errors[field]) ? errors[field][0] : errors[field];
            }
        });

        if (first) {
            first.focus();
            first.scrollIntoView({ block: 'center', behavior: 'smooth' });
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Feedback
    |--------------------------------------------------------------------------
    */

    function toast(type, message) {
        if (!message) return;
        if (window.toastr && typeof toastr[type] === 'function') {
            toastr[type](message);
        } else {
            window.alert(message);
        }
    }

    /** Metronic's indicator states: adds/removes the busy attribute. */
    function busy(button, on) {
        if (!button) return;
        button.disabled = !!on;
        button.setAttribute('data-kt-indicator', on ? 'on' : 'off');
    }

    /** @returns {Promise<boolean>} */
    function confirm(options) {
        options = options || {};

        if (!window.Swal) {
            return Promise.resolve(window.confirm(options.text || 'Are you sure?'));
        }

        return Swal.fire({
            title: options.title || 'Are you sure?',
            text: options.text || '',
            icon: options.icon || 'warning',
            showCancelButton: true,
            confirmButtonText: options.confirmText || 'Yes, continue',
            cancelButtonText: 'Cancel',
            confirmButtonColor: options.danger === false ? '#3085d6' : '#d33',
            cancelButtonColor: '#7e8299',
            reverseButtons: true
        }).then(function (result) {
            return !!result.isConfirmed;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Date fields
    |--------------------------------------------------------------------------
    | Flatpickr comes in plugins.bundle.js with the rest of Metronic. Nothing is
    | loaded here — this only ATTACHES it to the fields that ask for it, which
    | Metronic does not do on its own (it auto-initialises Select2, tooltips and
    | popovers, but not date pickers).
    |
    | THIS ALSO FIXES A LIVE BUG. Phase 14B gave the gift-voucher expiry field
    | the class `shunno-datepicker` and a comment saying Flatpickr, per the
    | project convention — and nothing anywhere ever called it. That field has
    | been a bare text input since it shipped, accepting whatever anyone typed
    | and relying on the server to reject it. Scanning on DOM ready from here
    | catches it and every field added after it.
    |
    | One house configuration, in one place:
    |
    |   the input SUBMITS Y-m-d          what every date column and Form Request
    |                                    in the application already expects
    |   the input SHOWS  j M Y           what a person reads without decoding
    |
    | altInput is what makes those two different things. Flatpickr hides the
    | real field and puts a readable one beside it, so nothing downstream has to
    | parse "14 Aug 2026".
    */
    function datepickers(root, options) {
        var scope = root || document;
        var settings = Object.assign({}, options || {});

        /*
         | `selector` is ours, not Flatpickr's, and is removed before the config
         | is handed over or Flatpickr logs it as an unknown option on every
         | field.
         |
         | It exists so a caller can claim a SUBSET of the date fields and give
         | them a different configuration. The filter menus need it: their
         | pickers must render inside the menu (static: true) or the first click
         | on a date counts as a click outside the KTMenu and closes it. They
         | carry `shunno-filter-date` so the house scan below leaves them alone.
         */
        var selector = settings.selector || '.shunno-datepicker';
        delete settings.selector;

        if (typeof window.flatpickr !== 'function') {
            // Bundle not loaded on this page. The fields stay plain text inputs
            // and the server still validates them, so this degrades rather than
            // breaking.
            return [];
        }

        var fields = Array.prototype.slice.call(scope.querySelectorAll(selector));

        return fields.map(function (field) {
            // Re-initialising a field would stack two calendars on it. Flatpickr
            // records its instance on the element, so this is the reliable check.
            if (field._flatpickr) {
                return field._flatpickr;
            }

            /*
             | THE BUG THIS GUARD EXISTS FOR — worth reading before touching it.
             |
             | With altInput on, flatpickr builds a SECOND visible input and, by
             | default, copies the original's className onto it. That means the
             | new input inherits `shunno-datepicker` too. The next scan over the
             | document therefore finds it, sees no _flatpickr on it, and
             | initialises a picker on the alt input — whose value is the DISPLAY
             | string ("1 Aug 2026"), not Y-m-d.
             |
             | Flatpickr cannot parse that with dateFormat 'Y-m-d', falls back to
             | a default, and the field ends up showing 1 Jan of the parsed year
             | while the real hidden input keeps a value nothing updates. The
             | symptom is a date picker that looks alive, reads wrong, and makes
             | every filter behave as though no range were set at all.
             |
             | Two defences, because either alone is fragile:
             |
             |   altInputClass below stops the class being copied in the first
             |   place, so a second scan never sees the alt input.
             |
             |   This check catches an alt input built before that was fixed, or
             |   by any other code that makes one.
             */
            if (field.previousElementSibling && field.previousElementSibling._flatpickr) {
                return field.previousElementSibling._flatpickr;
            }

            return window.flatpickr(field, Object.assign({
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'j M Y',
                allowInput: false,

                // Explicit, and NOT carrying `shunno-datepicker` — see above.
                // Metronic's own field classes are named so the alt input still
                // looks like every other control on the page.
                altInputClass: field.className
                    .replace(/\bshunno-datepicker\b/g, '')
                    .replace(/\bshunno-filter-date\b/g, '')
                    .trim(),

                // Reads the field's own attributes, so a min or max date is set
                // in Blade beside the input rather than in a JS lookup table
                // that has to be kept in step with it.
                minDate: field.dataset.minDate || null,
                maxDate: field.dataset.maxDate || null,
                defaultDate: field.value || null,
            }, settings));
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Time fields
    |--------------------------------------------------------------------------
    | The same argument as the date fields above, for the same reason.
    |
    | <input type="time"> renders as a different control in every browser, is
    | unusable on some Android keyboards, and looks nothing like the rest of a
    | Metronic form. Worse for this project specifically: the studio's operating
    | hours and its partial closures are entered in half-hour steps, and a
    | native control that accepts 18:07 hands the server a value it has to
    | reject after the fact.
    |
    | One house configuration:
    |
    |   the input SUBMITS H:i          what every time column and Form Request
    |                                  in the application already expects
    |   the input SHOWS  h:i K         6:00 PM, not 18:00
    |
    | minuteIncrement is 30 by default here, matching the step="1800" the native
    | inputs carried, and can be overridden per call.
    */
    function timepickers(root, options) {
        var scope = root || document;
        var settings = Object.assign({}, options || {});
        var selector = settings.selector || '.shunno-timepicker';
        delete settings.selector;

        if (typeof window.flatpickr !== 'function') {
            return [];
        }

        var fields = Array.prototype.slice.call(scope.querySelectorAll(selector));

        return fields.map(function (field) {
            if (field._flatpickr) {
                return field._flatpickr;
            }

            // Same alt-input trap as the date fields — see the long note above.
            if (field.previousElementSibling && field.previousElementSibling._flatpickr) {
                return field.previousElementSibling._flatpickr;
            }

            return window.flatpickr(field, Object.assign({
                noCalendar: true,
                enableTime: true,
                dateFormat: 'H:i',
                altInput: true,
                altFormat: 'h:i K',
                time_24hr: false,
                minuteIncrement: parseInt(field.dataset.minuteStep, 10) || 30,
                allowInput: false,

                altInputClass: field.className
                    .replace(/\bshunno-timepicker\b/g, '')
                    .trim(),

                defaultDate: field.value || null,
            }, settings));
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Change events, with or without Select2
    |--------------------------------------------------------------------------
    | The gap the Phase 6 rule works around, closed properly.
    |
    | Metronic auto-initialises anything carrying data-control="select2", and
    | Select2 announces a selection by calling jQuery's .trigger('change'). That
    | does NOT reach a listener added with addEventListener — jQuery only runs
    | its own handlers plus a native method of the same name, and elements have
    | no native .change(). So a Select2 filter bound the ordinary way is simply
    | dead, which is why every short filter dropdown in this panel is a plain
    | form-select.
    |
    | That rule is right for a three-option dropdown, which gains nothing from a
    | search box. It is wrong for a fourteen-option one. This binds through
    | jQuery when the field is a Select2 and natively otherwise, so the choice
    | between them can be made on whether a search box helps rather than on
    | which one the event happens to survive.
    */
    function onChange(el, handler) {
        if (!el) return;

        var isSelect2 = el.getAttribute('data-control') === 'select2';

        if (isSelect2 && window.jQuery) {
            window.jQuery(el).on('change', function () {
                handler.call(el);
            });
            return;
        }

        el.addEventListener('change', function () {
            handler.call(el);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Blocking progress dialog
    |--------------------------------------------------------------------------
    | For work the user must wait for and cannot usefully interrupt — building
    | a PDF of a year of reservations, assembling a spreadsheet.
    |
    | A modal rather than a spinner on the button, because those exports are the
    | one place in this panel where the wait is long enough that somebody starts
    | wondering whether the click registered, and clicks again. The second click
    | builds the whole file a second time.
    |
    | SweetAlert comes with Metronic's bundle; nothing is loaded here. When it is
    | somehow absent this degrades to doing nothing rather than throwing — a
    | missing progress dialog should not stop a download that would otherwise
    | have worked.
    */
    function progress(title, text) {
        if (!window.Swal) {
            return;
        }

        Swal.fire({
            title: title || 'Working…',
            text: text || '',

            // No outside click, no escape, no confirm button. There is nothing
            // to cancel — the request is already in flight — and a dialog that
            // closes while the work continues tells the user it stopped when it
            // has not.
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,

            didOpen: function () {
                Swal.showLoading();
            }
        });
    }

    /**
     * Close the progress dialog.
     *
     * Guarded on isVisible() so calling this when nothing is open cannot shut a
     * confirmation dialog that happens to be showing instead.
     */
    function progressDone() {
        if (window.Swal && Swal.isVisible()) {
            Swal.close();
        }
    }

    /** Bootstrap modal instance for an element or selector. */
    function modal(target) {
        var el = typeof target === 'string' ? document.querySelector(target) : target;
        return el ? bootstrap.Modal.getOrCreateInstance(el) : null;
    }

    /** Fills a form from a flat object; checkboxes read truthiness. */
    function fill(form, data) {
        Object.keys(data).forEach(function (key) {
            var field = form.querySelector('[name="' + key + '"]');
            if (!field) return;

            if (field.type === 'checkbox') {
                field.checked = !!data[key];
            } else if (field.type === 'radio') {
                var radio = form.querySelector('[name="' + key + '"][value="' + data[key] + '"]');
                if (radio) radio.checked = true;
            } else {
                field.value = data[key] === null || data[key] === undefined ? '' : data[key];

                /*
                 | A flatpickr field has TWO inputs: the real one, which this
                 | just set, and the visible alt input beside it. Setting .value
                 | updates the first and leaves the second showing whatever was
                 | there before — so an edit modal would submit the right date
                 | while displaying the previous one.
                 |
                 | false suppresses the change event: this is the form being
                 | populated, not somebody choosing a date, and firing change
                 | here would set off every listener watching the field.
                 */
                if (field._flatpickr) {
                    field._flatpickr.setDate(field.value || null, false);
                }
            }
        });

        syncSelects(form);
    }

    /*
    |--------------------------------------------------------------------------
    | File downloads
    |--------------------------------------------------------------------------
    | Every export in the panel. Lifted out of reports.js in Phase 29, unchanged
    | in behaviour: the awkward parts — a JSON refusal arriving from an endpoint
    | that otherwise returns a file, reading the filename back off the header,
    | revoking the object URL late enough — are the same everywhere and were
    | never specific to reports.
    |
    | A blob fetch rather than a plain link because xlsx and PDF cannot stream.
    | CSV can, but goes through the same path so all three behave alike.
    */
    var FORMAT_LABELS = { csv: 'CSV', xlsx: 'spreadsheet', pdf: 'PDF' };

    function download(url, format, trigger) {
        if (trigger) busy(trigger, true);

        /*
         | A blocking dialog, not just a spinner on the button. A PDF of a year
         | of reservations takes seconds — long enough that somebody wonders
         | whether the click registered and clicks again, and the second click
         | builds the entire file a second time.
         |
         | The message names the format, because "Preparing your PDF" is the
         | difference between waiting and wondering whether the wrong menu item
         | was hit.
         */
        progress(
            'Preparing your ' + (FORMAT_LABELS[format] || 'file'),
            format === 'pdf'
                ? 'Laying out the pages. Larger ranges take a moment.'
                : 'Gathering the rows.'
        );

        return fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        })
            .then(function (response) {
                var type = response.headers.get('content-type') || '';

                // A refusal — "too many rows", "nothing in that range" — comes
                // back as JSON from an endpoint that otherwise returns a file.
                if (type.indexOf('application/json') !== -1) {
                    return response.json().then(function (payload) {
                        throw { message: payload.message || 'Export failed.' };
                    });
                }

                if (!response.ok) {
                    throw { message: 'Export failed. Please try again.' };
                }

                // The server already worked out the filename, range included.
                // Reading it back beats rebuilding it here and disagreeing.
                var disposition = response.headers.get('content-disposition') || '';
                var match = disposition.match(/filename="?([^"]+)"?/);

                return response.blob().then(function (blob) {
                    return { blob: blob, name: match ? match[1] : 'export.' + format };
                });
            })
            .then(function (file) {
                var objectUrl = URL.createObjectURL(file.blob);
                var link = document.createElement('a');

                link.href = objectUrl;
                link.download = file.name;
                document.body.appendChild(link);
                link.click();
                link.remove();

                // Released on a timer rather than immediately: revoking before
                // the browser has finished handing the blob to its download
                // manager cancels the download.
                setTimeout(function () { URL.revokeObjectURL(objectUrl); }, 1000);
            })
            .catch(function (error) {
                toast('error', error.message || 'Export failed.');
            })
            .then(function () {
                // In the final then, not in each branch: this runs whether the
                // export succeeded, was refused, or the connection dropped. A
                // dialog left open after a failure says the work is still going.
                progressDone();
                if (trigger) busy(trigger, false);
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Password visibility
    |--------------------------------------------------------------------------
    | Any button carrying data-password-toggle="#selector" flips the field it
    | points at between password and text, and swaps its own icon.
    |
    | Delegated from the document rather than bound per button, so a field
    | inside a modal rendered later needs no wiring. Deliberately generic: the
    | settings screen has an SMTP password and the profile screen has three
    | more, and none of them should carry their own copy of six lines of DOM
    | fiddling.
    |
    | The toggle is set back to hidden on form submit by the pages that use it —
    | a password left legible on a screen somebody walks away from is the small
    | version of the problem the field exists to prevent.
    */
    function initPasswordToggles() {
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-password-toggle]');
            if (!button) return;

            event.preventDefault();

            var field = document.querySelector(button.getAttribute('data-password-toggle'));
            if (!field) return;

            var reveal = field.type === 'password';
            field.type = reveal ? 'text' : 'password';

            var icon = button.querySelector('i');
            if (icon) {
                icon.classList.toggle('ki-eye', !reveal);
                icon.classList.toggle('ki-eye-slash', reveal);
            }

            button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
            button.setAttribute('aria-pressed', reveal ? 'true' : 'false');
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Startup
    |--------------------------------------------------------------------------
    | One scan for date fields when the document is ready. Modal contents are
    | rendered by Blade into @stack('modals') at page load rather than injected
    | later, so this catches them too — a modal that is merely hidden is still
    | in the DOM. Anything that builds fields dynamically should call
    | Shunno.datepickers(container) itself.
    */
    /*
     | A native form.reset() restores the real input's value and knows nothing
     | about the visible alt input beside it, so a modal reused for "create"
     | after an "edit" would open showing the last record's date. Delegated from
     | the document so no form has to remember to do this.
     |
     | The timeout is not decoration: reset fires BEFORE the browser has put the
     | values back, so reading field.value in the handler itself would sync
     | flatpickr to the value being discarded.
     */
    document.addEventListener('reset', function (event) {
        var form = event.target;

        if (!(form instanceof HTMLFormElement)) return;

        window.setTimeout(function () {
            Array.prototype.slice.call(form.querySelectorAll('input')).forEach(function (field) {
                if (field._flatpickr) field._flatpickr.setDate(field.value || null, false);
            });
        }, 0);
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            datepickers();
            timepickers();
        });
    } else {
        datepickers();
        timepickers();
    }

    initPasswordToggles();

    return {
        request: request,
        showErrors: showErrors,
        clearErrors: clearErrors,
        syncSelects: syncSelects,
        datepickers: datepickers,
        timepickers: timepickers,
        onChange: onChange,
        toast: toast,
        busy: busy,
        confirm: confirm,
        progress: progress,
        progressDone: progressDone,
        download: download,
        modal: modal,
        fill: fill,
        csrf: csrf
    };
})();
