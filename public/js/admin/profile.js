/*
|------------------------------------------------------------------------------
| My profile
|------------------------------------------------------------------------------
| Depends on js/admin/shunno.js.
|
| Three things: the details form, the password form, and paging the sign-in
| history.
*/
(function () {
    'use strict';

    var config = window.ProfileConfig || {};

    /* =====================================================================
       Details
       ===================================================================== */

    var detailsForm = document.getElementById('profile-form');
    var emailInput = document.getElementById('profile-email-input');
    var confirmBlock = document.getElementById('profile-confirm-block');

    /*
    | Reveal the current-password field only when the address has actually been
    | changed.
    |
    | THIS IS A CONVENIENCE, NOT THE CHECK. UpdateProfileRequest compares the
    | submitted address against the database and demands the password itself, so
    | deleting this block from the DOM buys an attacker nothing. It is here so
    | that somebody correcting a phone number is not asked for a password they
    | do not need to type.
    */
    if (emailInput && confirmBlock) {
        emailInput.addEventListener('input', function () {
            var changed = this.value.trim().toLowerCase() !== String(config.currentEmail || '').toLowerCase();
            confirmBlock.classList.toggle('d-none', !changed);
        });
    }

    if (detailsForm) {
        detailsForm.addEventListener('submit', function (event) {
            event.preventDefault();

            var button = detailsForm.querySelector('[data-submit]');

            Shunno.clearErrors(detailsForm);
            Shunno.busy(button, true);

            Shunno.request(config.updateUrl, {
                method: 'POST',
                body: new FormData(detailsForm)
            })
                .then(function (payload) {
                    Shunno.toast('success', payload.message);

                    // Keep the summary strip at the top of the page honest —
                    // it showed the old name until the next reload otherwise.
                    var nameEl = document.getElementById('profile-name');
                    var emailEl = document.getElementById('profile-email');
                    if (nameEl) nameEl.textContent = payload.data.name;
                    if (emailEl) emailEl.textContent = payload.data.email;

                    // The address is now the current one, so the confirmation
                    // block should fold away and stop asking.
                    config.currentEmail = payload.data.email;
                    if (confirmBlock) confirmBlock.classList.add('d-none');

                    var passwordField = detailsForm.querySelector('[name="current_password"]');
                    if (passwordField) passwordField.value = '';
                })
                .catch(function (error) {
                    if (error.handled) return;

                    if (error.errors) {
                        Shunno.showErrors(detailsForm, error.errors);

                        // An error on current_password can only mean the block
                        // is relevant, so make sure it is visible to be read.
                        if (error.errors.current_password && confirmBlock) {
                            confirmBlock.classList.remove('d-none');
                        }
                        return;
                    }

                    Shunno.toast('error', error.message);
                })
                .then(function () {
                    Shunno.busy(button, false);
                });
        });
    }

    /* =====================================================================
       Password
       ===================================================================== */

    var passwordForm = document.getElementById('password-form');

    if (passwordForm) {
        passwordForm.addEventListener('submit', function (event) {
            event.preventDefault();

            var button = passwordForm.querySelector('[data-submit]');

            Shunno.clearErrors(passwordForm);
            Shunno.busy(button, true);

            Shunno.request(config.passwordUrl, {
                method: 'POST',
                body: new FormData(passwordForm)
            })
                .then(function (payload) {
                    Shunno.toast('success', payload.message);

                    // Cleared on success, always. Password fields left populated
                    // on a screen somebody may walk away from is a small thing
                    // that costs nothing to avoid.
                    passwordForm.reset();

                    // reset() restores the VALUES but not the type — a field
                    // revealed by the eye toggle would stay legible, showing an
                    // empty box that turns visible again the moment anyone
                    // types. Put both back to masked.
                    passwordForm.querySelectorAll('input[type="text"]').forEach(function (field) {
                        if (!field.name.startsWith('password')) return;
                        field.type = 'password';
                    });

                    passwordForm.querySelectorAll('[data-password-toggle] i').forEach(function (icon) {
                        icon.classList.add('ki-eye');
                        icon.classList.remove('ki-eye-slash');
                    });
                })
                .catch(function (error) {
                    if (error.handled) return;

                    if (error.errors) {
                        Shunno.showErrors(passwordForm, error.errors);
                        return;
                    }

                    Shunno.toast('error', error.message);
                })
                .then(function () {
                    Shunno.busy(button, false);
                });
        });
    }

    /* =====================================================================
       Sign-in history
       =====================================================================
       A DataTable over rows that are already in the page. No AJAX endpoint —
       the list is capped at thirty in ProfileController, so paging it from the
       server would be a round trip for nothing.

       jQuery is used here and only here. DataTables is a jQuery plugin with no
       vanilla API, and jQuery is already in Metronic's bundle; the project's
       rule is against a jQuery application ARCHITECTURE, not against
       initialising a bundled plugin the framework ships with.
    */

    var activityTable = document.getElementById('activity-table');

    if (activityTable && window.jQuery && jQuery.fn.DataTable) {
        jQuery(activityTable).DataTable({
            // Newest first. Column 0 sorts on the data-order timestamp in the
            // markup, not on the rendered date string — without that, "1 Aug"
            // sorts alphabetically and April lands before January.
            order: [[0, 'desc']],

            pageLength: 10,
            lengthChange: false,

            // Off: the whole list is thirty rows and already sorted the way
            // anyone wants it. Info and paging stay, because knowing which ten
            // of thirty you are looking at is worth the line.
            searching: true,

            language: {
                search: '',
                searchPlaceholder: 'Search history',
                info: 'Showing _START_ to _END_ of _TOTAL_ sign-ins',
                infoEmpty: 'No sign-ins recorded yet',
                infoFiltered: '(filtered from _MAX_)',
                zeroRecords: 'Nothing matches that',
                paginate: { previous: '‹', next: '›' }
            },

            // The browser column holds a long user-agent string; letting
            // DataTables size it fights the CSS truncation already on the cell.
            autoWidth: false,

            columnDefs: [
                { targets: 3, orderable: false }
            ]
        });
    }
})();
