/**
 * Six-digit code entry: one box per digit, with paste support and a resend
 * countdown driven by the server's remaining seconds.
 */
(function () {
    'use strict';

    const form = document.getElementById('kt_otp_form');
    if (!form) return;

    const inputs = Array.from(form.querySelectorAll('[data-otp-digit]'));
    const hidden = form.querySelector('[name="code"]');
    const submit = document.getElementById('kt_otp_submit');

    function sync() {
        hidden.value = inputs.map(function (i) { return i.value; }).join('');
    }

    inputs.forEach(function (input, index) {
        input.addEventListener('input', function () {
            input.value = input.value.replace(/\D/g, '').slice(-1);
            if (input.value && index < inputs.length - 1) inputs[index + 1].focus();
            sync();
        });

        input.addEventListener('keydown', function (event) {
            if (event.key === 'Backspace' && !input.value && index > 0) {
                inputs[index - 1].focus();
            }
        });

        input.addEventListener('paste', function (event) {
            event.preventDefault();
            const digits = (event.clipboardData.getData('text') || '').replace(/\D/g, '').split('');
            inputs.forEach(function (box, i) { box.value = digits[i] || ''; });
            sync();
            (inputs[digits.length] || inputs[inputs.length - 1]).focus();
        });
    });

    form.addEventListener('submit', function (event) {
        sync();
        if (hidden.value.length !== inputs.length) {
            event.preventDefault();
            inputs[0].focus();
            return;
        }
        submit.setAttribute('data-kt-indicator', 'on');
        submit.disabled = true;
    });

    // Resend countdown
    const resendButton = document.getElementById('kt_otp_resend');
    const resendTimer = document.getElementById('kt_otp_timer');

    if (resendButton && resendTimer) {
        let remaining = parseInt(resendButton.dataset.wait || '0', 10);

        function tick() {
            if (remaining <= 0) {
                resendButton.disabled = false;
                resendTimer.textContent = '';
                return;
            }
            resendButton.disabled = true;
            resendTimer.textContent = 'You can ask for another in ' + remaining + 's';
            remaining -= 1;
            window.setTimeout(tick, 1000);
        }

        tick();
    }

    if (inputs.length) inputs[0].focus();
})();
