/**
 * Reservation request dialog.
 *
 * Plain script, no modules, no bundler. Bootstrap's JS is not loaded on the
 * public site, so the dialog is driven by ShunnoModal below — about sixty lines
 * covering the parts that actually matter for accessibility: focus trap,
 * Escape, backdrop click, scroll lock, and returning focus to whatever opened
 * it.
 *
 * The server remains the authority on the slot rules, the pricing and the
 * validation. All of this only makes the form feel immediate.
 *
 * PHASE 7C changes:
 *   - the native date input is replaced by ShunnoDatePicker, so closed days and
 *     blocked dates are greyed rather than accepted and then refused;
 *   - the participants field is bounded by the chosen session's own maximum,
 *     not a hard-coded 30;
 *   - availability lookups are debounced and sent with no-store, so a fiddling
 *     visitor no longer fires a request per keystroke and never reads a stale
 *     answer from cache.
 */
(function () {
    'use strict';

    // -----------------------------------------------------------------------
    // Minimal dialog controller
    // -----------------------------------------------------------------------
    function ShunnoModal(root) {
        var opener = null;
        var focusables = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

        function trap(event) {
            if (event.key !== 'Tab') return;

            var items = root.querySelectorAll(focusables);
            if (!items.length) return;

            var first = items[0];
            var last = items[items.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }

        function onKeydown(event) {
            if (event.key === 'Escape') hide();
            else trap(event);
        }

        function show(trigger) {
            opener = trigger || document.activeElement;

            root.classList.add('is-open');
            root.removeAttribute('aria-hidden');
            document.body.classList.add('sh-modal-open');

            // Next frame, so the opacity transition has a starting value.
            window.requestAnimationFrame(function () {
                root.classList.add('is-visible');
            });

            document.addEventListener('keydown', onKeydown);

            var target = root.querySelector('[autofocus]') || root.querySelector(focusables);
            if (target) target.focus({ preventScroll: true });

            root.dispatchEvent(new CustomEvent('shunno:shown'));
        }

        function hide() {
            root.classList.remove('is-visible');
            document.removeEventListener('keydown', onKeydown);

            window.setTimeout(function () {
                root.classList.remove('is-open');
                root.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('sh-modal-open');
                if (opener) opener.focus({ preventScroll: true });
            }, 250);
        }

        // Backdrop click, but not a click that merely ended on the backdrop
        // after starting inside the dialog (a text selection drag).
        root.addEventListener('mousedown', function (event) {
            root._fromBackdrop = event.target === root;
        });
        root.addEventListener('click', function (event) {
            if (event.target === root && root._fromBackdrop) hide();
        });

        root.querySelectorAll('[data-modal-dismiss]').forEach(function (button) {
            button.addEventListener('click', hide);
        });

        return { show: show, hide: hide };
    }

    // -----------------------------------------------------------------------
    // Reservation form
    // -----------------------------------------------------------------------
    var root = document.getElementById('sh-reserve');
    var form = document.getElementById('sh-reserve-form');
    var configNode = document.getElementById('sh-reserve-config');

    if (!root || !form || !configNode) return;

    var config;
    try {
        config = JSON.parse(configNode.textContent);
    } catch (e) {
        return;
    }

    var modal = ShunnoModal(root);

    var el = {
        date: document.getElementById('sh-date'),                 // hidden, carries Y-m-d
        dateTrigger: document.getElementById('sh-date-trigger'),
        dateLabel: document.getElementById('sh-date-label'),
        datePanel: document.getElementById('sh-date-cal'),
        dateError: document.getElementById('sh-date-error'),
        time: document.getElementById('sh-time'),
        people: document.getElementById('sh-participants'),
        peopleHelp: document.getElementById('sh-participants-help'),
        peopleError: document.getElementById('sh-participants-error'),
        submit: document.getElementById('sh-submit'),
        spinner: document.querySelector('#sh-submit .sh-spinner'),
        label: document.querySelector('#sh-submit .sh-btn__label'),
        alert: document.getElementById('sh-form-alert'),
        foot: document.getElementById('sh-reserve-foot'),
        intro: document.querySelector('.sh-modal__intro'),
        done: document.getElementById('sh-reserve-done'),
        doneRef: document.getElementById('sh-done-ref'),
        doneSummary: document.getElementById('sh-done-summary'),
        subtotal: document.getElementById('sh-sum-subtotal'),
        discount: document.getElementById('sh-sum-discount'),
        discountRow: document.getElementById('sh-sum-discount-row'),
        total: document.getElementById('sh-sum-total')
    };

    function money(amount) {
        return new Intl.NumberFormat('en-US').format(Math.round(amount)) + ' BDT';
    }

    function chosenExperience() {
        return form.querySelector('input[name="experience"]:checked');
    }

    function fetchJson(url) {
        return fetch(url, {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin',
            // Availability is live. Without this a browser may reuse an earlier
            // answer, which looks exactly like an admin's change to the opening
            // hours having had no effect.
            cache: 'no-store'
        }).then(function (response) {
            return response.json();
        });
    }

    // -----------------------------------------------------------------------
    // Date picker
    // -----------------------------------------------------------------------
    var picker = window.ShunnoDatePicker({
        trigger: el.dateTrigger,
        label: el.dateLabel,
        input: el.date,
        panel: el.datePanel,
        placeholder: 'Choose a date',
        fetchMonth: function (month) {
            var experience = chosenExperience();
            if (!experience) return Promise.reject(new Error('no session'));

            return fetchJson(config.calendar +
                '?experience=' + encodeURIComponent(experience.value) +
                '&month=' + encodeURIComponent(month)
            ).then(function (payload) {
                if (!payload || !payload.success) throw new Error('unavailable');
                return payload.data;
            });
        },
        onSelect: function () {
            el.dateTrigger.classList.remove('is-invalid');
            if (el.dateError) el.dateError.textContent = '';
            refreshSlots();
        }
    });

    // -----------------------------------------------------------------------
    // Group size
    // -----------------------------------------------------------------------
    // The ceiling is the session's own max_participants, capped by the
    // site-wide limit. Enforced again by AvailabilityService::check() on
    // submit — this only saves the visitor a round trip.
    function limitsFor(experience) {
        var max = parseInt(experience && experience.dataset.max, 10);
        var min = parseInt(experience && experience.dataset.min, 10);

        return {
            min: isFinite(min) && min > 0 ? min : 1,
            max: isFinite(max) && max > 0 ? Math.min(max, config.ceiling) : config.ceiling
        };
    }

    function applyPeopleLimits(clamp) {
        var experience = chosenExperience();
        if (!experience) return;

        var limits = limitsFor(experience);
        var value = parseInt(el.people.value, 10);

        el.people.min = limits.min;
        el.people.max = limits.max;

        if (el.peopleHelp) {
            el.peopleHelp.textContent = 'Up to ' + limits.max + ' for this session. ' +
                config.discount.min + ' or more gets ' + config.discount.percent + '% off.';
        }

        // Clamped on a session change, where the number is no longer the
        // visitor's fault; flagged rather than rewritten while they are typing.
        if (clamp && isFinite(value) && value > limits.max) {
            el.people.value = limits.max;
            value = limits.max;
        }

        var over = isFinite(value) && value > limits.max;
        var under = isFinite(value) && value < limits.min;

        el.people.classList.toggle('is-invalid', over || under);

        if (el.peopleError) {
            el.peopleError.textContent = over
                ? 'This session takes up to ' + limits.max + ' people. Message us for a larger group.'
                : (under ? 'This session runs for ' + limits.min + ' people or more.' : '');
        }

        return !(over || under);
    }

    // -----------------------------------------------------------------------
    // Slots
    // -----------------------------------------------------------------------
    var slotRequest = 0;
    var slotTimer = null;

    function setTimeOptions(slots, placeholder) {
        el.time.innerHTML = '';

        if (placeholder) {
            var hint = document.createElement('option');
            hint.value = '';
            hint.textContent = placeholder;
            el.time.appendChild(hint);
            el.time.disabled = true;
            return;
        }

        el.time.disabled = false;

        slots.forEach(function (slot) {
            var option = document.createElement('option');
            option.value = slot.value;
            // An unavailable slot is greyed rather than removed: a visitor who
            // sees "6:00 PM - fully booked" understands the studio is busy,
            // where a silently shortened list just looks broken.
            option.textContent = slot.available
                ? slot.label + (slot.seats_left !== null ? ' \u00b7 ' + slot.seats_left + ' left' : '')
                : slot.label + ' \u2014 ' + slot.reason;
            option.disabled = !slot.available;
            el.time.appendChild(option);
        });
    }

    function firstSelectable() {
        for (var i = 0; i < el.time.options.length; i++) {
            if (!el.time.options[i].disabled) return el.time.options[i].value;
        }
        return '';
    }

    // Debounced: changing the session reloads the calendar and the slots at
    // once, and a visitor stepping the participant count fires several changes
    // in a second. One request per settled state is plenty.
    function refreshSlots() {
        window.clearTimeout(slotTimer);
        slotTimer = window.setTimeout(loadSlots, 120);
    }

    function loadSlots() {
        var experience = chosenExperience();

        if (!experience) return;

        if (!el.date.value) {
            setTimeOptions([], 'Choose a date first');
            return;
        }

        var ticket = ++slotRequest;
        setTimeOptions([], 'Checking availability\u2026');

        var url = config.availability
            + '?experience=' + encodeURIComponent(experience.value)
            + '&date=' + encodeURIComponent(el.date.value);

        fetchJson(url)
            .then(function (payload) {
                // A slow earlier request must never overwrite a newer answer.
                if (ticket !== slotRequest) return;

                if (!payload || !payload.success) {
                    setTimeOptions([], 'Could not load times');
                    return;
                }

                var data = payload.data;

                el.dateTrigger.classList.toggle('is-invalid', !data.open);
                if (el.dateError) el.dateError.textContent = data.open ? '' : (data.message || '');

                if (!data.open) {
                    setTimeOptions([], 'Not available that day');
                    return;
                }

                if (!data.slots.length) {
                    setTimeOptions([], 'No start time fits this session');
                    return;
                }

                var previous = el.time.value;
                setTimeOptions(data.slots, null);

                var kept = false;
                for (var i = 0; i < el.time.options.length; i++) {
                    if (el.time.options[i].value === previous && !el.time.options[i].disabled) {
                        kept = true;
                        break;
                    }
                }

                el.time.value = kept ? previous : firstSelectable();
            })
            .catch(function () {
                if (ticket !== slotRequest) return;
                setTimeOptions([], 'Could not load times');
            });
    }

    function refreshTotal() {
        var experience = chosenExperience();
        var people = parseInt(el.people.value, 10);

        if (!experience || !isFinite(people) || people < 1) {
            el.subtotal.textContent = el.total.textContent = '—';
            el.discountRow.hidden = true;
            return;
        }

        var subtotal = Number(experience.dataset.price) * people;
        var qualifies = people >= config.discount.min;
        var discount = qualifies ? Math.round((subtotal * config.discount.percent) / 100) : 0;

        el.subtotal.textContent = money(subtotal);
        el.discountRow.hidden = !qualifies;
        el.discount.textContent = '\u2212' + money(discount);
        el.total.textContent = money(subtotal - discount);
    }

    function clearErrors() {
        el.alert.hidden = true;
        el.alert.textContent = '';
        form.querySelectorAll('.is-invalid').forEach(function (node) {
            node.classList.remove('is-invalid');
        });
        el.dateTrigger.classList.remove('is-invalid');
        form.querySelectorAll('.invalid-feedback').forEach(function (node) {
            node.textContent = '';
            if (node.id === 'sh-experience-error') node.hidden = true;
        });
    }

    function showAlert(message) {
        el.alert.textContent = message;
        el.alert.hidden = false;
        el.alert.scrollIntoView({ block: 'nearest' });
    }

    function showErrors(errors) {
        var first = null;

        Object.keys(errors).forEach(function (field) {
            var key = field.replace(/\.\d+$/, '').replace(/\[\]$/, '');

            // The date input is hidden behind the picker; marking and focusing
            // it would put the error somewhere nobody can see.
            var input = key === 'date'
                ? el.dateTrigger
                : form.querySelector('[name="' + key + '"], [name="' + key + '[]"]');

            var feedback = document.getElementById('sh-' + key + '-error');
            var messages = errors[field];

            if (input) {
                input.classList.add('is-invalid');
                first = first || input;
            }
            if (feedback) {
                feedback.textContent = Array.isArray(messages) ? messages[0] : messages;
                feedback.hidden = false;
            }
        });

        if (first) first.focus();
        else showAlert('Please check the form and try again.');
    }

    // Set once the request has been accepted. The dialog stays on screen
    // afterwards so the visitor can read their reference, which means the
    // submit button is still in the DOM and still bound to the form by its
    // form= attribute — a second Enter press would post the whole thing again
    // and create a duplicate reservation.
    var completed = false;

    function setBusy(busy) {
        // `|| completed` matters: setBusy(false) runs in the settle handler
        // AFTER succeed(), so without it the button was re-enabled a moment
        // after being retired.
        el.submit.disabled = busy || completed;
        el.spinner.hidden = !busy;
        el.label.textContent = busy ? 'Sending…' : 'Send request';
    }

    function succeed(data) {
        completed = true;

        form.hidden = true;
        el.foot.hidden = true;
        if (el.intro) el.intro.hidden = true;
        el.done.hidden = false;

        // Belt and braces alongside the hidden footer: neither the pointer nor
        // the keyboard can reach a disabled button.
        el.submit.disabled = true;

        el.doneRef.textContent = data.reference;
        el.doneSummary.textContent = data.experience + ' on ' + data.date + ' at ' + data.time +
            ', estimated ' + money(data.pricing.total) + '.';

        el.done.focus();
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        // The request already went through. Nothing on this form should be
        // able to send a second one.
        if (completed) return;

        clearErrors();

        // Cheap local checks first. Everything here is re-run on the server;
        // this only avoids spending a submission on an answer we already know.
        if (!el.date.value) {
            el.dateTrigger.classList.add('is-invalid');
            if (el.dateError) el.dateError.textContent = 'Please choose a date.';
            el.dateTrigger.focus();
            return;
        }

        if (applyPeopleLimits(false) === false) {
            el.people.focus();
            return;
        }

        setBusy(true);

        var token = document.querySelector('meta[name="csrf-token"]');

        fetch(config.endpoint, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token ? token.content : ''
            },
            credentials: 'same-origin',
            body: new FormData(form)
        })
            .then(function (response) {
                return response.json().catch(function () { return null; })
                    .then(function (payload) { return { response: response, payload: payload }; });
            })
            .then(function (result) {
                var response = result.response;
                var payload = result.payload;

                if (response.status === 422 && payload && payload.errors) {
                    showErrors(payload.errors);
                    return;
                }
                if (response.status === 419) {
                    showAlert('This page has been open a while and the security token expired. ' +
                        'Please reload the page and send the request again.');
                    return;
                }
                if (response.status === 429) {
                    var wait = parseInt(response.headers.get('Retry-After'), 10);
                    showAlert('Too many requests from this connection. Please wait ' +
                        (isFinite(wait) && wait > 0 ? wait + ' seconds' : 'a minute') +
                        ' and try again.');
                    return;
                }
                if (!response.ok || !payload || !payload.success) {
                    showAlert((payload && payload.message) ||
                        'Something went wrong sending your request. Please try again, or message us on WhatsApp.');
                    return;
                }
                succeed(payload.data);
            })
            .catch(function () {
                showAlert('We could not reach the server. Check your connection and try again.');
            })
            .then(function () {
                setBusy(false);
            });
    });

    form.addEventListener('change', function (event) {
        if (event.target.name === 'experience') {
            // Which days are bookable depends on how long the session runs, so
            // the cached months are no longer answers to the right question.
            picker.reload();
            applyPeopleLimits(true);
            refreshSlots();
            refreshTotal();
        }
        if (event.target === el.people) {
            applyPeopleLimits(false);
            refreshTotal();
            // Only meaningful once capacity enforcement is switched on, but
            // then a larger group can turn an available slot unavailable.
            refreshSlots();
        }
    });

    el.people.addEventListener('input', function () {
        applyPeopleLimits(false);
        refreshTotal();
    });

    root.addEventListener('shunno:shown', function () {
        applyPeopleLimits(true);
        refreshSlots();
        refreshTotal();
    });

    // Any element that opens the dialog.
    document.querySelectorAll('[data-modal-open="sh-reserve"]').forEach(function (trigger) {
        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            modal.show(trigger);
        });
    });

    // Deep link for the printed QR code and email links: /?reserve=1
    var params = new URLSearchParams(window.location.search);
    if (params.has('reserve')) {
        modal.show();
        params.delete('reserve');
        var query = params.toString();
        window.history.replaceState(null, '',
            window.location.pathname + (query ? '?' + query : '') + window.location.hash);
    }

    applyPeopleLimits(true);
    refreshSlots();
    refreshTotal();
})();