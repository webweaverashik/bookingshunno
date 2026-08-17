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

        if (typeof window.flatpickr !== 'function') {
            // Bundle not loaded on this page. The fields stay plain text inputs
            // and the server still validates them, so this degrades rather than
            // breaking.
            return [];
        }

        var fields = Array.prototype.slice.call(scope.querySelectorAll('.shunno-datepicker'));

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
                altInputClass: field.className.replace(/\bshunno-datepicker\b/g, '').trim(),

                // Reads the field's own attributes, so a min or max date is set
                // in Blade beside the input rather than in a JS lookup table
                // that has to be kept in step with it.
                minDate: field.dataset.minDate || null,
                maxDate: field.dataset.maxDate || null,
                defaultDate: field.value || null,
            }, options || {}));
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
            }
        });

        syncSelects(form);
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
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { datepickers(); });
    } else {
        datepickers();
    }

    initPasswordToggles();

    return {
        request: request,
        showErrors: showErrors,
        clearErrors: clearErrors,
        syncSelects: syncSelects,
        datepickers: datepickers,
        onChange: onChange,
        toast: toast,
        busy: busy,
        confirm: confirm,
        modal: modal,
        fill: fill,
        csrf: csrf
    };
})();
