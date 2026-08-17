/*
|------------------------------------------------------------------------------
| Settings
|------------------------------------------------------------------------------
| Depends on js/admin/shunno.js.
|
| Every pane on the settings screen is an ordinary <form> carrying
| data-settings-form and an action. One delegated handler serves all of them,
| rather than four near-identical blocks — the panes differ only in where they
| post and which fields they carry, and both of those are already in the markup.
|
| Nothing here knows what a setting means. No value is computed, defaulted or
| validated in the browser: the form posts what was typed, the Form Request
| decides whether it is acceptable, and the response says what happened. A
| booking-fee percentage checked in JavaScript would be a second opinion about
| money, and the server's is the only one that counts.
*/
(function () {
    'use strict';

    var config = window.SettingsConfig || {};

    /* =====================================================================
       Saving a pane
       ===================================================================== */

    document.querySelectorAll('[data-settings-form]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            /*
             | Switching the gateway to live gets a confirmation.
             |
             | The server refuses live mode without live credentials, so this is
             | not the check — it is the pause. Sandbox and live differ by one
             | dropdown now that credentials live in the database, and the
             | failure of getting it wrong is invisible: in sandbox, payments
             | look successful to everyone and never settle, and nobody finds out
             | until a bank statement at month end.
             */
            var mode = form.querySelector('#gateway-mode');

            if (form.hasAttribute('data-confirm-live') && mode && mode.value === 'live') {
                Shunno.confirm({
                    title: 'Switch the gateway to live?',
                    text: 'Payments made from now on will move real money.',
                    confirmText: 'Yes, go live'
                }).then(function (ok) {
                    if (ok) save(form);
                });

                return;
            }

            save(form);
        });
    });

    function save(form) {
        var button = form.querySelector('[data-submit]');

        Shunno.clearErrors(form);
        Shunno.busy(button, true);

        // FormData reads the fields as they stand, including Select2 values —
        // which is exactly why Select2 fields are read at submit rather than
        // listened to. An unchecked switch is simply absent, and the Form
        // Requests turn that into false in prepareForValidation().
        Shunno.request(form.getAttribute('action'), {
            method: 'POST',
            body: new FormData(form)
        })
            .then(function (payload) {
                Shunno.toast('success', payload.message);
            })
            .catch(function (error) {
                if (error.handled) return;

                if (error.errors) {
                    Shunno.showErrors(form, error.errors);
                    return;
                }

                Shunno.toast('error', error.message);
            })
            .then(function () {
                Shunno.busy(button, false);
            });
    }

    /* =====================================================================
       Test email
       ===================================================================== */

    var testButton = document.getElementById('settings-test-mail');

    var testEmail = document.getElementById('settings-test-email');

    if (testButton) {
        testButton.addEventListener('click', function () {
            var recipient = testEmail ? testEmail.value.trim() : '';

            if (!recipient) {
                Shunno.toast('error', 'Enter an address to send the test to.');
                if (testEmail) testEmail.focus();
                return;
            }

            Shunno.busy(testButton, true);

            // FormData rather than a query string: the address is user input
            // going into a POST, and the server validates it as an email before
            // anything is sent.
            var body = new FormData();
            body.append('email', recipient);

            Shunno.request(config.testMailUrl, { method: 'POST', body: body })
                .then(function (payload) {
                    Shunno.toast('success', payload.message);
                })
                .catch(function (error) {
                    if (error.handled) return;

                    /*
                     | The transport's own message is shown rather than a
                     | friendly summary. "Could not send" tells whoever has to
                     | fix this nothing, and the reason is almost always the
                     | field they got wrong two rows up — a refused password, a
                     | wrong port, an unknown host. This reaches signed-in
                     | Admins only.
                     */
                    Shunno.toast('error', error.message);
                })
                .then(function () {
                    Shunno.busy(testButton, false);
                });
        });
    }

    /* =====================================================================
       Copy buttons on the gateway tab
       ===================================================================== */

    // Delegated, so the four URL rows need no per-element wiring.
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-copy]');
        if (!button) return;

        var text = button.getAttribute('data-copy');

        // navigator.clipboard needs a secure context. On plain http — which a
        // staging box often is — it is simply undefined, so the old selection
        // trick is kept as the fallback rather than letting the button die.
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(confirmCopy, fallbackCopy.bind(null, text));
        } else {
            fallbackCopy(text);
        }

        function confirmCopy() {
            Shunno.toast('success', 'Copied.');
        }

        function fallbackCopy(value) {
            var field = document.createElement('textarea');
            field.value = value;
            field.setAttribute('readonly', '');
            field.style.position = 'fixed';
            field.style.opacity = '0';
            document.body.appendChild(field);
            field.select();

            try {
                document.execCommand('copy');
                confirmCopy();
            } catch (e) {
                Shunno.toast('error', 'Could not copy — select the URL and copy it by hand.');
            }

            document.body.removeChild(field);
        }
    });
})();
