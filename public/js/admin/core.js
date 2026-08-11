/**
 * Admin shell behaviour.
 *
 * Two fixes against the BIDA template:
 *
 *  - Tooltips were bound to every [title] element on the page, and the call ran
 *    outside DOMContentLoaded. On an AJAX-refreshed table that double-binds and
 *    leaves orphaned tooltips floating over the page. They are now scoped to an
 *    explicit opt-in attribute and can be re-initialised after a render.
 *  - Flash messages were interpolated into an executable statement. They now
 *    arrive as JSON on window.ShunnoFlash.
 */
(function () {
    'use strict';

    function initTooltips(root) {
        const scope = root || document;

        if (typeof bootstrap === 'undefined') return;

        scope.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            const existing = bootstrap.Tooltip.getInstance(el);
            if (existing) existing.dispose();
            new bootstrap.Tooltip(el);
        });
    }

    function initFlash() {
        const flash = window.ShunnoFlash || {};

        if (typeof toastr === 'undefined') return;

        toastr.options = {
            closeButton: true,
            progressBar: true,
            positionClass: 'toast-top-right',
            timeOut: 5000,
        };

        if (flash.success) toastr.success(flash.success);
        if (flash.error) toastr.error(flash.error);
        if (flash.warning) toastr.warning(flash.warning);
    }

    document.addEventListener('DOMContentLoaded', function () {
        initTooltips();
        initFlash();
    });

    // Call after any AJAX render so new markup gets its tooltips.
    window.ShunnoAdmin = { initTooltips: initTooltips };
})();
