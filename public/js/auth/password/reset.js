(function () {
    'use strict';

    const form = document.getElementById('kt_new_password_form');
    if (!form) return;

    form.addEventListener('submit', function (event) {
        const password = form.querySelector('[name="password"]');
        const confirm = form.querySelector('[name="password_confirmation"]');
        let valid = true;

        [password, confirm].forEach(function (f) { f.classList.remove('is-invalid'); });

        if (password.value.length < 8) {
            password.classList.add('is-invalid');
            valid = false;
        }

        if (password.value !== confirm.value) {
            confirm.classList.add('is-invalid');
            valid = false;
        }

        if (!valid) {
            event.preventDefault();
            return;
        }

        const submit = document.getElementById('kt_new_password_submit');
        submit.setAttribute('data-kt-indicator', 'on');
        submit.disabled = true;
    });
})();
