/*
|------------------------------------------------------------------------------
| Workshops module
|------------------------------------------------------------------------------
| Depends on js/admin/shunno.js. The table body is rendered by Blade and swapped
| wholesale after every mutation, so nothing here builds markup.
*/
(function () {
    'use strict';

    var config = window.WorkshopsConfig || {};

    var tbody = document.getElementById('workshops-rows');
    var form = document.getElementById('workshop-form');
    var modalEl = document.getElementById('workshop-modal');
    var search = document.getElementById('workshops-search');
    var countEl = document.getElementById('workshops-count');

    if (!tbody) return;

    /*
    |--------------------------------------------------------------------------
    | Search — available to every role, including read-only Manager
    |--------------------------------------------------------------------------
    */

    function filterRows(term) {
        term = (term || '').toLowerCase().trim();
        var empty = document.getElementById('workshops-no-match');
        var shown = 0;

        tbody.querySelectorAll('tr[data-workshop-row]').forEach(function (row) {
            var match = !term || row.textContent.toLowerCase().indexOf(term) !== -1;
            row.hidden = !match;
            if (match) shown++;
        });

        if (empty) empty.hidden = shown !== 0;
    }

    if (search) {
        search.addEventListener('input', function () {
            filterRows(search.value);
        });
    }

    // Manager holds workshops.view only, so the modal is never rendered for
    // them. Everything below this point is write-side.
    if (!form || !modalEl) return;

    var modal = Shunno.modal(modalEl);
    var titleEl = document.getElementById('workshop-modal-title');
    var submitBtn = document.getElementById('workshop-save');
    var preview = document.getElementById('workshop-image-preview');
    var removeWrap = document.getElementById('workshop-image-remove-wrap');

    /*
    |--------------------------------------------------------------------------
    | Café credit — shown for "Other purposes" only
    |--------------------------------------------------------------------------
    | The client's rule is that only non-session bookings earn a coupon. The
    | categories that qualify are rendered into a data attribute by Blade rather
    | than listed here, so WorkshopCategory stays the single source of the rule
    | and adding a second credit-earning category never means editing this file.
    |
    | Bound through Shunno.onChange rather than addEventListener. The category
    | field is a Select2, and Select2 announces a selection with jQuery's
    | .trigger('change'), which never reaches a native listener — a plain
    | addEventListener here would be silently dead and the field would never
    | appear. onChange picks the right binding for the field it is given.
    |
    | Clearing the input on hide is not cosmetic: it stops a figure typed under
    | one category riding along in FormData after the admin switches to another.
    | The server zeroes it in that case anyway, so this exists so the number the
    | admin last saw is the number that gets saved.
    */

    var creditWrap = document.getElementById('workshop-cafe-credit');
    var creditInput = creditWrap ? creditWrap.querySelector('[name="cafe_credit_per_person"]') : null;
    var categorySelect = form.querySelector('[name="category"]');

    function syncCafeCredit() {
        if (!creditWrap || !categorySelect) return;

        var allowed = (creditWrap.getAttribute('data-credit-categories') || '').split(',');
        var show = allowed.indexOf(categorySelect.value) !== -1;

        creditWrap.hidden = !show;

        if (!show && creditInput) creditInput.value = '';
    }

    Shunno.onChange(categorySelect, syncCafeCredit);

    /*
    |--------------------------------------------------------------------------
    | Modal open / reset
    |--------------------------------------------------------------------------
    */

    function resetForm() {
        form.reset();
        Shunno.syncSelects(form);   // reset() clears the native select; Select2 needs telling
        Shunno.clearErrors(form);
        form.action = config.storeUrl;
        form.querySelector('[name="remove_image"]').checked = false;
        preview.hidden = true;
        preview.querySelector('img').src = '';
        removeWrap.hidden = true;

        /*
         | syncSelects() above already dispatches a native change that reaches
         | the jQuery binding, so this is belt and braces. It is here because
         | that chain is three indirections long and one day somebody will
         | change one of them; an explicit call costs nothing and the failure it
         | prevents — a credit field left open on a clay session — is not
         | obvious from looking at either file.
         */
        syncCafeCredit();
    }

    function openCreate() {
        resetForm();
        titleEl.textContent = 'New workshop';
        // Sensible starting point rather than an empty form.
        Shunno.fill(form, {
            price_basis: 'per_person',
            duration_minutes: 120,
            min_participants: 1,
            max_participants: 12,
            materials_included: true,
            is_active: true,
            sort_order: 0
        });
        modal.show();
    }

    function openEdit(id, url) {
        resetForm();
        titleEl.textContent = 'Edit workshop';

        Shunno.request(url).then(function (payload) {
            var data = payload.data;
            form.action = data.update_url;
            Shunno.fill(form, data);

            // fill() sets the category and then syncs the Select2s, which
            // reaches the binding above. Called again for the same reason as in
            // resetForm(): without it, an "Other purposes" workshop would open
            // with its figure stored and the field hidden.
            syncCafeCredit();

            if (data.image_url) {
                preview.querySelector('img').src = data.image_url;
                preview.hidden = false;
                removeWrap.hidden = false;
            }

            modal.show();
        }).catch(function (error) {
            if (!error.handled) Shunno.toast('error', error.message);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Table refresh
    |--------------------------------------------------------------------------
    */

    function applyRows(data) {
        tbody.innerHTML = data.html;
        if (countEl) {
            countEl.textContent = data.active + ' of ' + data.count + ' visible on the website';
        }
        if (search) search.value = '';
        filterRows('');
    }

    /*
    |--------------------------------------------------------------------------
    | Save
    |--------------------------------------------------------------------------
    */

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        Shunno.clearErrors(form);
        Shunno.busy(submitBtn, true);

        Shunno.request(form.action, {
            method: 'POST',
            body: new FormData(form)
        }).then(function (payload) {
            applyRows(payload.data);
            modal.hide();
            Shunno.toast('success', payload.message);
        }).catch(function (error) {
            if (error.handled) return;

            if (error.status === 422) {
                Shunno.showErrors(form, error.errors);
                Shunno.toast('warning', error.message || 'Please correct the highlighted fields.');
            } else {
                Shunno.toast('error', error.message);
            }
        }).then(function () {
            Shunno.busy(submitBtn, false);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Row actions (delegated — rows are replaced wholesale)
    |--------------------------------------------------------------------------
    */

    tbody.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-action]');
        if (!trigger) return;

        event.preventDefault();

        var action = trigger.dataset.action;
        var url = trigger.dataset.url;

        if (action === 'edit') {
            openEdit(trigger.dataset.id, url);
            return;
        }

        if (action === 'toggle') {
            var goingLive = trigger.dataset.active === '0';

            Shunno.confirm({
                title: goingLive ? 'Show on the website?' : 'Hide from the website?',
                text: goingLive
                    ? 'Visitors will be able to choose this session when requesting a visit.'
                    : 'Existing reservations are unaffected. Visitors will no longer be offered this session.',
                icon: 'question',
                danger: false,
                confirmText: goingLive ? 'Yes, show it' : 'Yes, hide it'
            }).then(function (ok) {
                if (!ok) return;

                Shunno.request(url, { method: 'POST' })
                    .then(function (payload) {
                        applyRows(payload.data);
                        Shunno.toast('success', payload.message);
                    })
                    .catch(function (error) {
                        if (!error.handled) Shunno.toast('error', error.message);
                    });
            });
            return;
        }

        /*
         | No 'delete' branch. Workshops are deactivated, never removed — the
         | Live/Hidden toggle above is the whole story. See
         | WorkshopPolicy::delete() for why; the route and controller method are
         | still there and now answer 403, so nothing here needs to guard it.
         */
    });

    /*
    |--------------------------------------------------------------------------
    | Wiring
    |--------------------------------------------------------------------------
    */

    document.querySelectorAll('[data-workshop-create]').forEach(function (button) {
        button.addEventListener('click', openCreate);
    });

    // Live preview of a newly chosen file, so the admin sees what they picked.
    var fileInput = form.querySelector('[name="image"]');
    fileInput.addEventListener('change', function () {
        if (!fileInput.files || !fileInput.files[0]) return;
        preview.querySelector('img').src = URL.createObjectURL(fileInput.files[0]);
        preview.hidden = false;
        form.querySelector('[name="remove_image"]').checked = false;
    });

    // Duration is entered in minutes; show the human reading beside it.
    var duration = form.querySelector('[name="duration_minutes"]');
    var durationHint = document.getElementById('workshop-duration-hint');
    function updateDurationHint() {
        var minutes = parseInt(duration.value, 10);
        if (!minutes || minutes < 0) { durationHint.textContent = ''; return; }
        var h = Math.floor(minutes / 60), m = minutes % 60;
        durationHint.textContent = (h ? h + (h === 1 ? ' hour' : ' hours') : '') + (m ? (h ? ' ' : '') + m + ' min' : '');
    }
    duration.addEventListener('input', updateDurationHint);
    modalEl.addEventListener('shown.bs.modal', updateDurationHint);
})();
