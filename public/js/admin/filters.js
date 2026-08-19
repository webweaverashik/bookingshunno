/*
|------------------------------------------------------------------------------
| Filter menus
|------------------------------------------------------------------------------
| Depends on js/admin/shunno.js. Drives the markup in
| resources/views/admin/partials/filter-bar.blade.php.
|
| One factory rather than a copy per register. Each page says which container
| holds its fields and what to do when Apply is pressed; everything else — the
| Select2 wiring, the date pickers, the reset, the badge, the dependent fields,
| the export — is the same everywhere and lives here.
|
| Nothing in this file knows what any filter MEANS. It reads values off the DOM
| and hands them back; the server decides what they do.
*/
(function () {
    'use strict';

    function fieldsIn(root) {
        return Array.prototype.slice.call(root.querySelectorAll('[data-filter-field]'));
    }

    /*
     | A cleared field falls back to its default rather than to an empty string.
     |
     | Select2's allow-clear puts the field back to '' — but '' is not what
     | "every status" means to the server, 'all' is. Resolving it here means no
     | page has to know that a cleared field and a defaulted one are the same
     | request.
     */
    function valueOf(field) {
        var value = (field.value || '').trim();

        return value === '' ? (field.dataset.filterDefault || '') : value;
    }

    function isDefault(field) {
        return valueOf(field) === (field.dataset.filterDefault || '');
    }

    function filterBar(options) {
        var root = options.root;

        if (!root) return null;

        var menu = root.closest('[data-kt-menu]');
        var toolbar = menu ? menu.parentNode : null;
        var badge = toolbar ? toolbar.querySelector('[data-filter="count"]') : null;

        /* =====================================================================
           Controls
           ===================================================================== */

        // Select2 where the markup asks for it. Metronic does not auto-init
        // data-kt-select2 — that is the point of using it rather than
        // data-control="select2", which Metronic claims on page load and would
        // leave these fields initialised twice.
        if (window.jQuery && window.jQuery.fn.select2) {
            window.jQuery(root).find('[data-kt-select2]').each(function () {
                var data = this.dataset;

                window.jQuery(this).select2({
                    placeholder: data.placeholder || '',
                    allowClear: data.allowClear === 'true',
                    // -1 hides the search box entirely, which is right for a
                    // three-option list and wrong for a list of workshops.
                    minimumResultsForSearch: data.hideSearch === 'true' ? -1 : 0,
                    // Inside the menu, never on <body>. KTMenu closes on any
                    // click outside its own DOM, so a dropdown appended to the
                    // body shuts the menu the moment an option is clicked.
                    dropdownParent: window.jQuery(data.dropdownParent ? data.dropdownParent : menu || root)
                });
            });
        }

        // static: true keeps the calendar inside the menu. Rendered on <body>,
        // as Flatpickr does by default, the first click on a date registers as
        // a click outside the KTMenu and closes it mid-selection.
        Shunno.datepickers(root, { selector: '.shunno-filter-date', static: true });

        /* =====================================================================
           State
           ===================================================================== */

        function values() {
            var out = {};

            fieldsIn(root).forEach(function (field) {
                out[field.dataset.filterField] = valueOf(field);
            });

            return out;
        }

        // Only what actually filters something. Keeps the address bar readable
        // and lets the server's own defaults stand where nothing was chosen.
        function changed() {
            var out = {};

            fieldsIn(root).forEach(function (field) {
                if (!isDefault(field)) out[field.dataset.filterField] = valueOf(field);
            });

            return out;
        }

        function refreshBadge() {
            if (!badge) return;

            var count = fieldsIn(root).filter(function (field) {
                return !isDefault(field);
            }).length;

            badge.textContent = count;
            badge.hidden = count === 0;
        }

        // A field that only applies when another holds a particular value — the
        // custom date range under a "Custom range" choice, today.
        function syncVisibility() {
            var current = values();

            Array.prototype.slice.call(root.querySelectorAll('[data-filter-when]')).forEach(function (wrap) {
                var parts = wrap.dataset.filterWhen.split(':');

                wrap.hidden = current[parts[0]] !== parts[1];
            });
        }

        // Through Shunno.onChange, which binds via jQuery on a Select2 and
        // natively otherwise: a Select2 announces itself with jQuery's
        // .trigger('change'), which never reaches addEventListener.
        fieldsIn(root).forEach(function (field) {
            Shunno.onChange(field, function () {
                syncVisibility();
                refreshBadge();
            });
        });

        /* =====================================================================
           Actions
           ===================================================================== */

        function apply() {
            refreshBadge();
            if (options.onApply) options.onApply(changed(), values());
        }

        function reset() {
            fieldsIn(root).forEach(function (field) {
                var fallback = field.dataset.filterDefault || '';

                if (field._flatpickr) {
                    field._flatpickr.clear();
                    return;
                }

                field.value = fallback;

                /*
                 | A NATIVE dispatch, deliberately, not jQuery's .trigger().
                 |
                 | Setting .value on a native select does not redraw Select2's
                 | own markup, so the visible control would keep showing the old
                 | choice. Select2 listens through jQuery .on(), which is a real
                 | addEventListener underneath — so a native event reaches it
                 | AND reaches any plain listener. jQuery .trigger() only
                 | reaches the first.
                 */
                field.dispatchEvent(new Event('change', { bubbles: true }));
            });

            syncVisibility();
            apply();
        }

        root.addEventListener('click', function (event) {
            var action = event.target.closest('[data-filter]');

            if (!action) return;

            if (action.dataset.filter === 'apply') {
                event.preventDefault();
                apply();
            }

            if (action.dataset.filter === 'reset') {
                event.preventDefault();
                reset();
            }
        });

        /* =====================================================================
           Export
           ===================================================================== */

        var exportBox = toolbar ? toolbar.querySelector('[data-filter="export"]') : null;

        if (exportBox) {
            exportBox.addEventListener('click', function (event) {
                var item = event.target.closest('[data-row-export]');

                if (!item) return;

                event.preventDefault();

                var format = item.getAttribute('data-row-export');

                // The same filters that produced what is on screen. An export
                // that ignores them is a file nobody asked for.
                var params = new URLSearchParams(changed());
                params.set('format', format);

                Shunno.download(exportBox.dataset.url + '?' + params.toString(), format, item);
            });
        }

        syncVisibility();
        refreshBadge();

        /*
         | Re-read the fields without asking for a reload.
         |
         | For a page that writes values back INTO the menu after a response —
         | the reports screen resolves "last month" on the server and sends the
         | dates back — the badge and the dependent fields would otherwise still
         | describe what was on screen before the answer arrived.
         */
        function refresh() {
            syncVisibility();
            refreshBadge();
        }

        return { values: values, changed: changed, apply: apply, reset: reset, refresh: refresh };
    }

    window.Shunno = window.Shunno || {};
    window.Shunno.filterBar = filterBar;
})();
