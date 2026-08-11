import Modal from 'bootstrap/js/dist/modal';

/**
 * Reservation request popup.
 *
 * The server is the authority on everything here — the slot rules, the pricing
 * and the validation all exist again in PHP. What this does is make the form
 * feel immediate: the right times for the chosen session, a running total, and
 * errors next to the field that caused them.
 */

const MODAL_ID = 'sh-reserve';

const money = (amount) =>
    `${new Intl.NumberFormat('en-US').format(Math.round(amount))} BDT`;

function readConfig() {
    const node = document.getElementById('sh-reserve-config');
    if (!node) return null;
    try {
        return JSON.parse(node.textContent);
    } catch {
        return null;
    }
}

export default function initReservation() {
    const root = document.getElementById(MODAL_ID);
    const form = document.getElementById('sh-reserve-form');
    const config = readConfig();

    if (!root || !form || !config) return;

    const modal = Modal.getOrCreateInstance(root);

    const el = {
        date: document.getElementById('sh-date'),
        time: document.getElementById('sh-time'),
        people: document.getElementById('sh-participants'),
        submit: document.getElementById('sh-submit'),
        spinner: document.querySelector('#sh-submit .sh-spinner'),
        label: document.querySelector('#sh-submit .sh-btn__label'),
        alert: document.getElementById('sh-form-alert'),
        foot: document.getElementById('sh-reserve-foot'),
        done: document.getElementById('sh-reserve-done'),
        doneRef: document.getElementById('sh-done-ref'),
        doneSummary: document.getElementById('sh-done-summary'),
        subtotal: document.getElementById('sh-sum-subtotal'),
        discount: document.getElementById('sh-sum-discount'),
        discountRow: document.getElementById('sh-sum-discount-row'),
        total: document.getElementById('sh-sum-total'),
    };

    const chosenExperience = () => form.querySelector('input[name="experience"]:checked');

    // ---- time slots -------------------------------------------------------

    function refreshSlots() {
        const experience = chosenExperience();
        if (!experience) return;

        const slots = config.slots[experience.value] || {};
        const previous = el.time.value;

        el.time.innerHTML = '';
        Object.entries(slots).forEach(([value, label]) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            el.time.append(option);
        });

        // Keep the visitor's choice if the longer session still allows it.
        if (previous && slots[previous]) el.time.value = previous;
    }

    // ---- running total ----------------------------------------------------

    function refreshTotal() {
        const experience = chosenExperience();
        const people = Number.parseInt(el.people.value, 10);

        if (!experience || !Number.isFinite(people) || people < 1) {
            el.subtotal.textContent = el.total.textContent = '—';
            el.discountRow.hidden = true;
            return;
        }

        const subtotal = Number(experience.dataset.price) * people;
        const qualifies = people >= config.discount.min;
        const discount = qualifies ? Math.round((subtotal * config.discount.percent) / 100) : 0;

        el.subtotal.textContent = money(subtotal);
        el.discountRow.hidden = !qualifies;
        el.discount.textContent = `−${money(discount)}`;
        el.total.textContent = money(subtotal - discount);
    }

    // ---- errors -----------------------------------------------------------

    function clearErrors() {
        el.alert.hidden = true;
        el.alert.textContent = '';
        form.querySelectorAll('.is-invalid').forEach((node) => node.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach((node) => {
            node.textContent = '';
            if (node.id === 'sh-experience-error') node.hidden = true;
        });
    }

    function showErrors(errors) {
        let first = null;

        Object.entries(errors).forEach(([field, messages]) => {
            const key = field.replace(/\.\d+$/, '').replace(/\[\]$/, '');
            const input = form.querySelector(`[name="${key}"], [name="${key}[]"]`);
            const feedback = document.getElementById(`sh-${key}-error`);

            if (input) {
                input.classList.add('is-invalid');
                first = first || input;
            }
            if (feedback) {
                feedback.textContent = Array.isArray(messages) ? messages[0] : messages;
                feedback.hidden = false;
            }
        });

        if (first) {
            first.focus({ preventScroll: false });
        } else {
            showAlert('Please check the form and try again.');
        }
    }

    function showAlert(message) {
        el.alert.textContent = message;
        el.alert.hidden = false;
        el.alert.scrollIntoView({ block: 'nearest' });
    }

    function setBusy(busy) {
        el.submit.disabled = busy;
        el.spinner.hidden = !busy;
        el.label.textContent = busy ? 'Sending…' : 'Send request';
    }

    // ---- submit -----------------------------------------------------------

    async function submit(event) {
        event.preventDefault();
        clearErrors();
        setBusy(true);

        try {
            const response = await fetch(config.endpoint, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: new FormData(form),
            });

            const payload = await response.json().catch(() => null);

            if (response.status === 422 && payload?.errors) {
                showErrors(payload.errors);
                return;
            }

            if (response.status === 429) {
                showAlert('That is a lot of requests in a short time. Please wait a moment and try again.');
                return;
            }

            if (!response.ok || !payload?.success) {
                showAlert(payload?.message ?? 'Something went wrong sending your request. Please try again, or message us on WhatsApp.');
                return;
            }

            succeed(payload.data);
        } catch {
            showAlert('We could not reach the server. Check your connection and try again.');
        } finally {
            setBusy(false);
        }
    }

    function succeed(data) {
        form.hidden = true;
        el.foot.hidden = true;
        document.querySelector('.sh-modal__intro').hidden = true;
        el.done.hidden = false;

        el.doneRef.textContent = data.reference;
        el.doneSummary.textContent =
            `${data.experience} on ${data.date} at ${data.time}, estimated ${money(data.pricing.total)}.`;

        el.done.focus();
    }

    // ---- wiring -----------------------------------------------------------

    form.addEventListener('submit', submit);
    form.addEventListener('change', (event) => {
        if (event.target.name === 'experience') {
            refreshSlots();
            refreshTotal();
        }
        if (event.target === el.people) refreshTotal();
    });
    el.people.addEventListener('input', refreshTotal);

    // Sunday is closed. Say so as soon as the date is picked rather than after
    // the round trip — the server checks this again regardless.
    el.date.addEventListener('change', () => {
        const feedback = document.getElementById('sh-date-error');
        if (!el.date.value) return;

        const day = new Date(`${el.date.value}T00:00:00`).getDay();
        const closed = config.closedDays.includes(day);

        el.date.classList.toggle('is-invalid', closed);
        feedback.textContent = closed ? "We're closed on Sundays. Please choose another day." : '';
    });

    root.addEventListener('shown.bs.modal', () => {
        refreshSlots();
        refreshTotal();
    });

    // Deep link for the printed QR code and email links: /?reserve=1
    const params = new URLSearchParams(window.location.search);
    if (params.has('reserve')) {
        modal.show();
        params.delete('reserve');
        const query = params.toString();
        window.history.replaceState(
            null,
            '',
            window.location.pathname + (query ? `?${query}` : '') + window.location.hash
        );
    }

    refreshSlots();
    refreshTotal();
}
