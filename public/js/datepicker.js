/**
 * Shunno date picker.
 *
 * WHY THIS EXISTS: <input type="date"> cannot grey out a day. The browser's own
 * calendar will happily offer a Sunday, a public holiday the admin blocked, or
 * a day whose remaining window is too short for a four-hour session — and the
 * visitor only finds out after submitting. Every date shown here is one the
 * server has already said yes to, and the reasons for the rest are on the day
 * itself.
 *
 * The server remains the authority. StoreReservationRequest re-checks the date
 * on submit; this only stops the visitor wasting a submission.
 *
 * Plain script, no modules, no bundler — same rules as the rest of the public
 * site. Exposes one global.
 */
window.ShunnoDatePicker = (function () {
    'use strict';

    var WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'];

    function pretty(iso) {
        // Parsed by hand rather than through Date(): "2026-08-14" is treated as
        // UTC midnight by the constructor, which reads back as the 13th for
        // anyone west of Greenwich.
        var parts = iso.split('-');
        var date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));

        return WEEKDAYS[date.getDay()] + ', ' + date.getDate() + ' ' +
            MONTHS[date.getMonth()] + ' ' + date.getFullYear();
    }

    /**
     * options:
     *   trigger    button that opens the calendar and shows the chosen date
     *   label      element inside the trigger whose text is the chosen date
     *   input      hidden input carrying the Y-m-d value the form submits
     *   panel      empty element the calendar renders into
     *   placeholder text shown when nothing is chosen
     *   fetchMonth function(monthString) -> Promise of the server payload
     *   onSelect   function(isoDate) called after a date is chosen or cleared
     */
    return function create(options) {
        var months = {};          // payloads, keyed 'YYYY-MM'
        var current = null;       // month on screen
        var selected = '';
        var open = false;
        var generation = 0;       // invalidates in-flight fetches after a reload

        /* ---------------------------------------------------------------- */
        /* Rendering                                                        */
        /* ---------------------------------------------------------------- */

        function renderLoading(message) {
            options.panel.innerHTML =
                '<p class="sh-cal__state">' + (message || 'Loading\u2026') + '</p>';
        }

        function render(data) {
            var html = '';

            html += '<div class="sh-cal__head">';
            html += '<button type="button" class="sh-cal__nav" data-cal-prev' +
                (data.prev ? '' : ' disabled') + ' aria-label="Previous month">' +
                '<span aria-hidden="true">&#8592;</span></button>';
            html += '<span class="sh-cal__month" aria-live="polite">' + data.label + '</span>';
            html += '<button type="button" class="sh-cal__nav" data-cal-next' +
                (data.next ? '' : ' disabled') + ' aria-label="Next month">' +
                '<span aria-hidden="true">&#8594;</span></button>';
            html += '</div>';

            html += '<div class="sh-cal__weekdays" aria-hidden="true">';
            WEEKDAYS.forEach(function (name) {
                html += '<span>' + name.charAt(0) + '</span>';
            });
            html += '</div>';

            html += '<div class="sh-cal__grid" role="grid">';

            for (var blank = 0; blank < data.first_weekday; blank++) {
                html += '<span class="sh-cal__blank"></span>';
            }

            data.days.forEach(function (day) {
                var classes = 'sh-cal__day';
                if (day.is_today) classes += ' is-today';
                if (day.date === selected) classes += ' is-selected';
                if (!day.selectable) classes += ' is-blocked';

                html += '<button type="button" class="' + classes + '"' +
                    ' data-cal-day="' + day.date + '"' +
                    (day.selectable ? '' : ' disabled') +
                    ' aria-label="' + pretty(day.date) +
                    (day.selectable ? '' : ' \u2014 ' + escapeAttr(day.reason || 'unavailable')) + '"' +
                    (day.reason ? ' title="' + escapeAttr(day.reason) + '"' : '') +
                    (day.date === selected ? ' aria-current="date"' : '') +
                    '>' + day.day + '</button>';
            });

            html += '</div>';

            // Only worth saying once, under the grid, rather than on every
            // greyed day the visitor cannot click anyway.
            html += '<p class="sh-cal__note">Greyed dates are closed, fully committed, ' +
                'or too short for the session you picked.</p>';

            if (selected) {
                html += '<button type="button" class="sh-cal__clear" data-cal-clear>Clear date</button>';
            }

            options.panel.innerHTML = html;
        }

        function escapeAttr(value) {
            return String(value)
                .replace(/&/g, '&amp;').replace(/"/g, '&quot;')
                .replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        /* ---------------------------------------------------------------- */
        /* Data                                                             */
        /* ---------------------------------------------------------------- */

        function show(month) {
            var ticket = generation;

            if (months[month]) {
                current = month;
                render(months[month]);
                return Promise.resolve(months[month]);
            }

            renderLoading('Checking which dates are open\u2026');

            return options.fetchMonth(month)
                .then(function (data) {
                    // A month that arrived after the session changed describes
                    // a different question than the one now on screen.
                    if (ticket !== generation) return null;

                    months[data.month] = data;
                    current = data.month;
                    render(data);
                    return data;
                })
                .catch(function () {
                    if (ticket !== generation) return null;
                    renderLoading('Could not load the calendar. Please try again.');
                    return null;
                });
        }

        /* ---------------------------------------------------------------- */
        /* Selection                                                        */
        /* ---------------------------------------------------------------- */

        function setValue(iso) {
            selected = iso || '';
            options.input.value = selected;
            options.label.textContent = selected ? pretty(selected) : (options.placeholder || 'Choose a date');
            options.trigger.classList.toggle('is-empty', !selected);

            if (typeof options.onSelect === 'function') {
                options.onSelect(selected);
            }
        }

        function pick(iso) {
            setValue(iso);
            if (months[current]) render(months[current]);
            close();
            options.trigger.focus();
        }

        /* ---------------------------------------------------------------- */
        /* Open / close                                                     */
        /* ---------------------------------------------------------------- */

        function openPanel() {
            if (open) return;

            open = true;
            options.panel.hidden = false;
            options.trigger.setAttribute('aria-expanded', 'true');

            show(current || (selected ? selected.slice(0, 7) : monthOfToday())).then(function () {
                var target = options.panel.querySelector('.is-selected:not([disabled])') ||
                    options.panel.querySelector('.sh-cal__day:not([disabled])');
                if (target) target.focus();
            });

            document.addEventListener('mousedown', onOutside, true);
            document.addEventListener('keydown', onKey, true);
        }

        function close() {
            if (!open) return;

            open = false;
            options.panel.hidden = true;
            options.trigger.setAttribute('aria-expanded', 'false');

            document.removeEventListener('mousedown', onOutside, true);
            document.removeEventListener('keydown', onKey, true);
        }

        function monthOfToday() {
            var now = new Date();
            return now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
        }

        function onOutside(event) {
            if (options.panel.contains(event.target) || options.trigger.contains(event.target)) return;
            close();
        }

        function onKey(event) {
            if (event.key !== 'Escape') return;

            // The picker sits inside the reservation dialog, which also closes
            // on Escape. Stopping propagation means the first press closes the
            // calendar and the second closes the dialog.
            event.stopPropagation();
            close();
            options.trigger.focus();
        }

        /* ---------------------------------------------------------------- */
        /* Wiring                                                           */
        /* ---------------------------------------------------------------- */

        options.trigger.addEventListener('click', function () {
            open ? close() : openPanel();
        });

        options.panel.addEventListener('click', function (event) {
            var day = event.target.closest('[data-cal-day]');
            if (day && !day.disabled) {
                pick(day.getAttribute('data-cal-day'));
                return;
            }

            if (event.target.closest('[data-cal-clear]')) {
                setValue('');
                if (months[current]) render(months[current]);
                return;
            }

            var prev = event.target.closest('[data-cal-prev]');
            if (prev && !prev.disabled && months[current] && months[current].prev) {
                show(months[current].prev);
                return;
            }

            var next = event.target.closest('[data-cal-next]');
            if (next && !next.disabled && months[current] && months[current].next) {
                show(months[current].next);
            }
        });

        setValue(options.input.value || '');

        return {
            /**
             * Throw away every cached month and, if the calendar is open,
             * fetch the current one again. Called when the session changes:
             * which days are bookable depends on how long the session runs.
             */
            reload: function () {
                generation++;
                months = {};

                if (open) {
                    show(current || monthOfToday());
                }
            },
            value: function () { return selected; },
            clear: function () { setValue(''); },
            close: close,
            focus: function () { options.trigger.focus(); },
            element: options.trigger
        };
    };
})();
