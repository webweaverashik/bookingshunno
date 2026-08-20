/*
|------------------------------------------------------------------------------
| Maintenance
|------------------------------------------------------------------------------
| Depends on js/admin/shunno.js.
|
| Two paths, decided by the button rather than by this file: a read-only task
| runs on click, and anything that writes goes through the password dialog
| first. The distinction is declared in App\Enums\System\MaintenanceTask and
| travels here on a data attribute — the server re-checks it, so an edited
| attribute buys nothing.
|
| Output is rendered with textContent, never innerHTML. Artisan output is not
| user input in any ordinary sense, but it can contain a filename or an
| exception message with angle brackets in it, and a maintenance page that
| executes its own output is not a page anyone should build.
*/
(function () {
    'use strict';

    var outputCard = document.getElementById('maintenance-output-card');
    var outputBox = document.getElementById('maintenance-output');
    var outputTitle = document.getElementById('maintenance-output-title');
    var confirmModalEl = document.getElementById('maintenance-confirm-modal');
    var confirmForm = document.getElementById('maintenance-confirm-form');

    if (!outputBox || !confirmForm) return;

    var runUrl = confirmForm.getAttribute('action');
    var pending = null;

    function show(label, text, ok) {
        outputTitle.textContent = label;
        outputBox.textContent = text;

        // Colour the frame, not the text: a migration's own output is what
        // should be read, and tinting every line of it green helps nobody.
        outputBox.classList.toggle('text-danger', !ok);

        outputCard.hidden = false;
        outputCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function send(task, password, trigger, label) {
        var body = new FormData();

        body.append('task', task);
        if (password) body.append('password', password);

        Shunno.busy(trigger, true);

        return Shunno.request(runUrl, { method: 'POST', body: body })
            .then(function (payload) {
                Shunno.toast('success', payload.message);
                show(label, payload.data.output, true);
                return payload;
            })
            .catch(function (error) {
                if (error.handled) throw error;

                Shunno.toast('error', error.message);

                // A failure that came back with output — a migration that hit a
                // duplicate column, say — is the case where the detail matters
                // most, so it is shown rather than swallowed with the toast.
                if (error.data && error.data.output) {
                    show(label + ' — failed', error.data.output, false);
                }

                throw error;
            })
            .finally(function () {
                Shunno.busy(trigger, false);
            });
    }

    document.querySelectorAll('[data-maintenance-run]').forEach(function (button) {
        button.addEventListener('click', function () {
            var task = this.getAttribute('data-maintenance-run');
            var label = this.getAttribute('data-label');
            var readOnly = this.getAttribute('data-readonly') === '1';
            var destructive = this.getAttribute('data-destructive') === '1';
            var trigger = this;

            if (readOnly) {
                send(task, null, trigger, label).catch(function () {});
                return;
            }

            /*
             | Two prompts for a writing task, and they are asking different
             | things. This one asks "did you mean to?", which is answered by
             | reading the name of the command; the dialog behind it asks "are
             | you who you say you are?", which a stolen session cannot answer.
             */
            /*
             | The destructive task gets its own wording, and it names the
             | consequence rather than the action. "Run this?" is a question
             | somebody answers yes to on autopilot; "every reservation will be
             | destroyed" is one they read.
             |
             | Its own environment fence is server-side — the button only exists
             | on a development machine at all. This is about the wrong click on
             | the right machine.
             */
            Shunno.confirm(destructive ? {
                title: 'Destroy this database?',
                text: 'Every reservation, payment, voucher and staff account will be dropped and '
                    + 'rebuilt from the seeders. This cannot be undone.',
                confirmText: 'Yes, wipe it',
                danger: true
            } : {
                title: 'Run this on the live server?',
                text: label + ' will run against the live database.',
                confirmText: 'Continue',
                danger: true
            }).then(function (confirmed) {
                if (!confirmed) return;

                pending = { task: task, label: label, trigger: trigger };

                Shunno.clearErrors(confirmForm);
                confirmForm.reset();
                confirmForm.querySelector('[name="task"]').value = task;
                document.getElementById('maintenance-confirm-label').textContent = label;

                Shunno.modal(confirmModalEl).show();

                // Focus after the modal has finished animating, or Bootstrap
                // takes it back on the way in.
                confirmModalEl.addEventListener('shown.bs.modal', function once() {
                    confirmModalEl.removeEventListener('shown.bs.modal', once);
                    var field = confirmForm.querySelector('[name="password"]');
                    if (field) field.focus();
                });
            });
        });
    });

    confirmForm.addEventListener('submit', function (event) {
        event.preventDefault();

        if (!pending) return;

        var save = document.getElementById('maintenance-confirm-save');
        var password = confirmForm.querySelector('[name="password"]').value;

        Shunno.clearErrors(confirmForm);
        Shunno.busy(save, true);

        send(pending.task, password, pending.trigger, pending.label)
            .then(function () {
                Shunno.modal(confirmModalEl).hide();
                pending = null;
            })
            .catch(function (error) {
                // A wrong password keeps the dialog open with the field
                // flagged. Anything else has already been reported by send(),
                // and the dialog closes so the output is readable.
                if (error.status === 422 && error.errors) {
                    Shunno.showErrors(confirmForm, error.errors);
                    return;
                }

                Shunno.modal(confirmModalEl).hide();
                pending = null;
            })
            .finally(function () {
                Shunno.busy(save, false);
            });
    });

    document.getElementById('maintenance-output-clear').addEventListener('click', function () {
        outputBox.textContent = '';
        outputCard.hidden = true;
    });
})();
