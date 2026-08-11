/**
 * Sign-in form. The auth JS referenced by the BIDA Blade files was never
 * supplied, so this is a fresh, dependency-free replacement: no FormValidation,
 * no jQuery. The server validates everything regardless; this only spares a
 * round trip on obvious mistakes.
 */
(function () {
    'use strict';

    const form = document.getElementById('kt_sign_in_form');
    if (!form) return;

    const submit = document.getElementById('kt_sign_in_submit');

    form.addEventListener('submit', function (event) {
        const email = form.querySelector('[name="email"]');
        const password = form.querySelector('[name="password"]');
        let valid = true;

        [email, password].forEach(function (field) {
            field.classList.remove('is-invalid');
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                valid = false;
            }
        });

        if (!valid) {
            event.preventDefault();
            return;
        }

        submit.setAttribute('data-kt-indicator', 'on');
        submit.disabled = true;
    });

    const toggle = document.querySelector('[data-kt-password-toggle]');
    if (toggle) {
        toggle.addEventListener('click', function () {
            const field = form.querySelector('[name="password"]');
            const shown = field.type === 'text';
            field.type = shown ? 'password' : 'text';
            toggle.querySelector('i').className = shown
                ? 'ki-outline ki-eye fs-2'
                : 'ki-outline ki-eye-slash fs-2';
        });
    }
})();
