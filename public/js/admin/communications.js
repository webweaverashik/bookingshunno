/*
|------------------------------------------------------------------------------
| Communications — message history, resend, and copying a payment link
|------------------------------------------------------------------------------
| Depends on js/admin/shunno.js. Loaded on the reservations and payments pages.
|
| Everything here is delegated from the document, because every element it
| touches lives inside a drawer that reservations.js and payments.js replace
| wholesale on each action. A listener bound to a button would survive exactly
| one render.
*/
(function () {
    'use strict';

    /* ---------------------------------------------------------------
       Message history — loaded on demand
       --------------------------------------------------------------- */

    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('[data-action="load-messages"]');
        if (!toggle) return;

        event.preventDefault();

        var target = document.querySelector(toggle.dataset.target);
        if (!target) return;

        // Second click collapses. Cheaper than a reload and matches what the
        // chevron implies.
        if (target.dataset.loaded === '1') {
            target.hidden = !target.hidden;
            return;
        }

        target.hidden = false;
        target.innerHTML = '<div class="text-muted fs-8">Loading…</div>';

        Shunno.request(toggle.dataset.url)
            .then(function (payload) {
                target.innerHTML = payload.data.html;
                target.dataset.loaded = '1';
            })
            .catch(function (error) {
                if (error.handled) return;
                target.innerHTML = '<div class="text-danger fs-8">Could not load the message history.</div>';
                Shunno.toast('error', error.message);
            });
    });

    /* ---------------------------------------------------------------
       Resend
       --------------------------------------------------------------- */

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-action="resend-message"]');
        if (!button) return;

        event.preventDefault();

        // The list this button sits in, so the refreshed HTML goes back where
        // it came from without the button needing to know which drawer it is in.
        var list = button.closest('[data-messages-list]');

        Shunno.busy(button, true);

        Shunno.request(button.dataset.url, { method: 'POST' })
            .then(function (payload) {
                Shunno.toast('success', payload.message);
                if (list && payload.data && payload.data.html) {
                    list.innerHTML = payload.data.html;
                }
            })
            .catch(function (error) {
                if (error.handled) return;

                // 409 is the throttle, or a payment request that has since been
                // withdrawn. The message explains which; nothing else to do.
                Shunno.toast('error', error.message);
            })
            .finally(function () {
                Shunno.busy(button, false);
            });
    });

    /* ---------------------------------------------------------------
       Copy the payment link
       --------------------------------------------------------------- */

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-action="copy-payment-link"]');
        if (!button) return;

        event.preventDefault();
        copy(button.dataset.link, button);
    });

    /**
     * Clipboard with a fallback.
     *
     * navigator.clipboard is unavailable on plain http, which is exactly the
     * situation on a local install, and silently rejecting there would look
     * like a broken button to whoever is testing.
     */
    function copy(text, button) {
        var done = function () {
            var label = button.innerHTML;
            button.innerHTML = '<i class="ki-outline ki-check fs-5"></i> Copied';
            window.setTimeout(function () { button.innerHTML = label; }, 1800);
        };

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done).catch(function () {
                legacy(text, done);
            });
            return;
        }

        legacy(text, done);
    }

    function legacy(text, done) {
        var field = document.createElement('textarea');

        field.value = text;
        field.setAttribute('readonly', '');
        field.style.position = 'fixed';
        field.style.opacity = '0';

        document.body.appendChild(field);
        field.select();

        try {
            document.execCommand('copy');
            done();
        } catch (e) {
            Shunno.toast('error', 'Could not copy. Select the link and copy it manually.');
        }

        document.body.removeChild(field);
    }
})();
