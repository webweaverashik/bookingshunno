/*
|------------------------------------------------------------------------------
| Availability
|------------------------------------------------------------------------------
| Three independent forms on one page: opening hours, booking rules, blocked
| dates. Depends on js/admin/shunno.js. Blade renders every row; nothing here
| builds markup.
*/
(function () {
    'use strict';

    var config = window.AvailabilityConfig || {};

    /* =====================================================================
    Opening hours
    ===================================================================== */

    var hoursForm = document.getElementById('hours-form');
    var hoursRows = document.getElementById('hours-rows');
    var hoursSave = document.getElementById('hours-save');

    // Declared at this level, not inside the guards below: both the change
    // listener and the post-save re-render call it, and a function declared
    // inside an if block is not visible from a sibling one under strict mode.
    function applyClosedState(row) {
        if (!row) return;

        var toggle = row.querySelector('[data-closed-toggle]');
        if (!toggle) return;

        /*
         | A disabled input is not submitted, which is exactly what a closed day
         | needs — but the state has to follow the checkbox immediately, or the
         | admin sees editable times on a day marked closed.
         |
         | Selected by data-hours-time rather than by input[type="time"]: these
         | are Flatpickr fields now, and a flatpickr field is a plain text input
         | with a SECOND visible input beside it. Both have to be disabled, and
         | clearing has to go through the instance, or the real field empties
         | while the visible one keeps showing a time.
         */
        row.querySelectorAll('[data-hours-time]').forEach(function (input) {
            input.disabled = toggle.checked;

            if (input._flatpickr) {
                input._flatpickr.altInput.disabled = toggle.checked;
                if (toggle.checked) input._flatpickr.clear();
            } else if (toggle.checked) {
                input.value = '';
            }
        });
    }

    function refreshHoursRows() {
        hoursRows.querySelectorAll('[data-hours-row]').forEach(function (row) {
            applyClosedState(row);
        });
    }

    if (hoursRows) {
        hoursRows.addEventListener('change', function (event) {
            if (!event.target.matches('[data-closed-toggle]')) return;
            applyClosedState(event.target.closest('tr'));
        });

        refreshHoursRows();
    }

    if (hoursForm) {
        hoursForm.addEventListener('submit', function (event) {
            event.preventDefault();
            Shunno.clearErrors(hoursForm);
            Shunno.busy(hoursSave, true);

            // Unticked checkboxes are absent from FormData, so is_closed has to
            // be sent explicitly for every day or the server sees six.
            var body = new FormData(hoursForm);

            hoursRows.querySelectorAll('[data-hours-row]').forEach(function (row, index) {
                var toggle = row.querySelector('[data-closed-toggle]');
                body.set('days[' + index + '][is_closed]', toggle && toggle.checked ? '1' : '0');
            });

            Shunno.request(hoursForm.action, { method: 'POST', body: body })
                .then(function (payload) {
                    hoursRows.innerHTML = payload.data.html;

                    // Rows re-rendered by the server carry no pickers: the
                    // DOM-ready scan ran long before this markup existed.
                    Shunno.timepickers(hoursRows);
                    refreshHoursRows();
                    retuneBlockPicker(payload.data.closed_weekdays);

                    // A workshop that no longer fits is a real operational
                    // problem, so it gets a dialog rather than a toast that
                    // vanishes in four seconds.
                    if (payload.data.warning && payload.data.warning.length) {
                        Shunno.confirm({
                            title: 'Hours saved, with a warning',
                            text: payload.message,
                            icon: 'warning',
                            danger: false,
                            confirmText: 'Understood'
                        });
                    } else {
                        Shunno.toast('success', payload.message);
                    }
                })
                .catch(function (error) {
                    if (error.handled) return;
                    if (error.status === 422) {
                        Shunno.showErrors(hoursForm, error.errors);
                        Shunno.toast('warning', 'Please correct the highlighted times.');
                    } else {
                        Shunno.toast('error', error.message);
                    }
                })
                .then(function () {
                    Shunno.busy(hoursSave, false);
                });
        });
    }

    /* =====================================================================
       Booking rules
       ===================================================================== */

    var rulesForm = document.getElementById('rules-form');

    if (rulesForm) {
        var rulesSave = document.getElementById('rules-save');
        var capacity = rulesForm.querySelector('[name="enforce_capacity"]');

        rulesForm.addEventListener('submit', function (event) {
            event.preventDefault();

            var body = new FormData(rulesForm);
            body.set('enforce_capacity', capacity && capacity.checked ? '1' : '0');

            function send() {
                Shunno.clearErrors(rulesForm);
                Shunno.busy(rulesSave, true);

                Shunno.request(rulesForm.action, { method: 'POST', body: body })
                    .then(function (payload) {
                        Shunno.toast('success', payload.message);
                    })
                    .catch(function (error) {
                        if (error.handled) return;
                        if (error.status === 422) {
                            Shunno.showErrors(rulesForm, error.errors);
                        } else {
                            Shunno.toast('error', error.message);
                        }
                    })
                    .then(function () {
                        Shunno.busy(rulesSave, false);
                    });
            }

            // Switching capacity on starts refusing bookings. Worth one
            // deliberate confirmation.
            if (capacity && capacity.checked) {
                Shunno.confirm({
                    title: 'Enforce capacity?',
                    text: 'Visitors will be turned away once a session reaches its maximum participants. Make sure every workshop has a real figure before switching this on.',
                    icon: 'warning',
                    danger: false,
                    confirmText: 'Yes, enforce it'
                }).then(function (ok) {
                    if (ok) send();
                });
            } else {
                send();
            }
        });
    }

    /**
     * Keep the Block a date calendar in step with the weekly pattern.
     *
     * Both are on this screen, so closing Mondays and then blocking a date
     * happens in one sitting — and without this the calendar would still offer
     * a Monday, having been built from the hours as they were when the page
     * loaded.
     *
     * The date field's data attribute is updated as well as the live instance:
     * the modal's picker is rebuilt from the field if it is ever
     * re-initialised, and leaving the attribute stale would undo this quietly.
     */
    function retuneBlockPicker(closedWeekdays) {
        if (!Array.isArray(closedWeekdays)) return;

        var field = document.querySelector('#block-form [name="date"]');

        if (!field) return;

        field.dataset.disableWeekdays = closedWeekdays.join(',');

        if (!field._flatpickr) return;

        field._flatpickr.set('disable', [
            function (date) {
                return closedWeekdays.indexOf(date.getDay()) !== -1;
            },
        ]);
    }

    /* =====================================================================
       Blocked dates
       ===================================================================== */

    var blockForm = document.getElementById('block-form');
    var blockRows = document.getElementById('blocks-rows');
    var blockModalEl = document.getElementById('block-modal');

    if (!blockRows) return;

    function applyBlocks(data) {
        blockRows.innerHTML = data.html;
        var count = document.getElementById('blocks-count');
        if (count) {
            count.textContent = data.count + ' upcoming ' + (data.count === 1 ? 'closure' : 'closures');
        }
    }

    // Manager gets a read-only page and never receives the modal.
    if (blockForm && blockModalEl) {
        var blockModal = Shunno.modal(blockModalEl);
        var blockSave = document.getElementById('block-save');
        var blockTitle = document.getElementById('block-modal-title');
        var fullDay = document.getElementById('block-full-day');
        var times = document.getElementById('block-times');

        function applyFullDayState() {
            times.hidden = fullDay.checked;
        }

        fullDay.addEventListener('change', applyFullDayState);

        function resetBlockForm() {
            blockForm.reset();
            Shunno.clearErrors(blockForm);
            blockForm.action = config.blockStoreUrl;
            blockForm.querySelector('[name="acknowledge"]').value = '0';
            fullDay.checked = true;
            applyFullDayState();
        }

        document.querySelectorAll('[data-block-create]').forEach(function (button) {
            button.addEventListener('click', function () {
                resetBlockForm();
                blockTitle.textContent = 'Block a date';
                blockModal.show();
            });
        });

        blockForm.addEventListener('submit', function (event) {
            event.preventDefault();
            Shunno.clearErrors(blockForm);
            Shunno.busy(blockSave, true);

            Shunno.request(blockForm.action, { method: 'POST', body: new FormData(blockForm) })
                .then(function (payload) {
                    applyBlocks(payload.data);
                    blockModal.hide();
                    Shunno.toast('success', payload.message);
                })
                .catch(function (error) {
                    if (error.handled) return;

                    if (error.status === 422) {
                        Shunno.showErrors(blockForm, error.errors);
                        return;
                    }

                    // 409 with requires_acknowledgement means the period already
                    // has reservations. Confirm once, then resubmit with the
                    // flag set — the server still decides.
                    if (error.status === 409 && error.data && error.data.requires_acknowledgement) {
                        Shunno.confirm({
                            title: 'Reservations already booked',
                            text: error.message,
                            icon: 'warning',
                            confirmText: 'Block it anyway'
                        }).then(function (ok) {
                            if (!ok) return;
                            blockForm.querySelector('[name="acknowledge"]').value = '1';
                            blockForm.requestSubmit();
                        });
                        return;
                    }

                    Shunno.toast('error', error.message);
                })
                .then(function () {
                    Shunno.busy(blockSave, false);
                });
        });
    }

    blockRows.addEventListener('click', function (event) {
        var trigger = event.target.closest('[data-action]');
        if (!trigger) return;

        event.preventDefault();

        if (trigger.dataset.action === 'edit-block' && blockForm) {
            Shunno.request(trigger.dataset.url)
                .then(function (payload) {
                    var data = payload.data;
                    blockForm.reset();
                    Shunno.clearErrors(blockForm);
                    blockForm.action = data.update_url;
                    blockForm.querySelector('[name="acknowledge"]').value = '0';
                    Shunno.fill(blockForm, data);
                    document.getElementById('block-full-day').checked = data.is_full_day;
                    document.getElementById('block-times').hidden = data.is_full_day;
                    document.getElementById('block-modal-title').textContent = 'Edit closure';
                    Shunno.modal(document.getElementById('block-modal')).show();
                })
                .catch(function (error) {
                    if (!error.handled) Shunno.toast('error', error.message);
                });
            return;
        }

        if (trigger.dataset.action === 'delete-block') {
            Shunno.confirm({
                title: 'Reopen this date?',
                text: trigger.dataset.label + ' will accept reservation requests again.',
                icon: 'question',
                danger: false,
                confirmText: 'Yes, reopen it'
            }).then(function (ok) {
                if (!ok) return;

                Shunno.request(trigger.dataset.url, { method: 'DELETE' })
                    .then(function (payload) {
                        applyBlocks(payload.data);
                        Shunno.toast('success', payload.message);
                    })
                    .catch(function (error) {
                        if (!error.handled) Shunno.toast('error', error.message);
                    });
            });
        }
    });
})();
