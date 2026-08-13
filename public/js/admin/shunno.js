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
     * Select2 renders its own markup and only listens for jQuery events, so
     * setting .value on the native select silently leaves the visible control
     * showing the previous choice. Metronic bundles jQuery; if a field was
     * never initialised this is a no-op.
     */
    function syncSelects(form) {
        if (!window.jQuery) return;
        form.querySelectorAll('select[data-control="select2"]').forEach(function (select) {
            if (jQuery(select).data('select2')) {
                jQuery(select).trigger('change.select2');
            }
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

    return {
        request: request,
        showErrors: showErrors,
        clearErrors: clearErrors,
        syncSelects: syncSelects,
        toast: toast,
        busy: busy,
        confirm: confirm,
        modal: modal,
        fill: fill,
        csrf: csrf
    };
})();
