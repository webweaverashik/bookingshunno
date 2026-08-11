(function () {
    'use strict';

    const form = document.getElementById('kt_password_reset_form');
    if (!form) return;

    form.addEventListener('submit', function (event) {
        const email = form.querySelector('[name="email"]');
        email.classList.remove('is-invalid');

        if (!email.value.trim()) {
            email.classList.add('is-invalid');
            event.preventDefault();
            return;
        }

        const submit = document.getElementById('kt_password_reset_submit');
        submit.setAttribute('data-kt-indicator', 'on');
        submit.disabled = true;
    });
})();
