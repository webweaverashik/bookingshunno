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
        date: document.getElementById('sh-date'),
        time: document.getElementById('sh-time'),
        people: document.getElementById('sh-participants'),
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

    function refreshSlots() {
        var experience = chosenExperience();
        if (!experience) return;

        var slots = config.slots[experience.value] || {};
        var previous = el.time.value;

        el.time.innerHTML = '';
        Object.keys(slots).forEach(function (value) {
            var option = document.createElement('option');
            option.value = value;
            option.textContent = slots[value];
            el.time.appendChild(option);
        });

        // Keep the visitor's choice if the longer session still allows it.
        if (previous && slots[previous]) el.time.value = previous;
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
            var input = form.querySelector('[name="' + key + '"], [name="' + key + '[]"]');
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

    function setBusy(busy) {
        el.submit.disabled = busy;
        el.spinner.hidden = !busy;
        el.label.textContent = busy ? 'Sending…' : 'Send request';
    }

    function succeed(data) {
        form.hidden = true;
        el.foot.hidden = true;
        if (el.intro) el.intro.hidden = true;
        el.done.hidden = false;

        el.doneRef.textContent = data.reference;
        el.doneSummary.textContent = data.experience + ' on ' + data.date + ' at ' + data.time +
            ', estimated ' + money(data.pricing.total) + '.';

        el.done.focus();
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        clearErrors();
        setBusy(true);

        var token = document.querySelector('meta[name="csrf-token"]');

        fetch(config.endpoint, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token ? token.content : ''
            },
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
                if (response.status === 429) {
                    showAlert('That is a lot of requests in a short time. Please wait a moment and try again.');
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
            refreshSlots();
            refreshTotal();
        }
        if (event.target === el.people) refreshTotal();
    });
    el.people.addEventListener('input', refreshTotal);

    // Sunday is closed. Say so on selection rather than after a round trip —
    // the server checks this again regardless.
    el.date.addEventListener('change', function () {
        var feedback = document.getElementById('sh-date-error');
        if (!el.date.value) return;

        var day = new Date(el.date.value + 'T00:00:00').getDay();
        var closed = config.closedDays.indexOf(day) !== -1;

        el.date.classList.toggle('is-invalid', closed);
        feedback.textContent = closed ? "We're closed on Sundays. Please choose another day." : '';
    });

    root.addEventListener('shunno:shown', function () {
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

    refreshSlots();
    refreshTotal();
})();
