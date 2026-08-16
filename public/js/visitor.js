(function () {
    'use strict';

    /**
     * Shunno Art Cafe — visitor area (Phase 15).
     *
     * Two small enhancements on the code screen and nothing else. The page works
     * without this file: the boxes are ordinary inputs, the hidden field is what
     * submits, and the <noscript> block supplies a plain text field if the
     * script never runs at all. That matters more here than anywhere else in the
     * app — somebody who cannot get past this screen cannot use the area.
     *
     * Deliberately NOT the admin otp.js. That one is Metronic: axios, toastr,
     * KTUtil, a fetch per verification. The public side has none of those loaded
     * and is not going to gain them for one form.
     */

    function initCodeBoxes() {
        var form = document.getElementById('sh-otp-form');
        if (!form) return;

        var hidden = document.getElementById('sh-otp-value');
        var boxes = Array.prototype.slice.call(form.querySelectorAll('[data-otp-box]'));
        if (!hidden || boxes.length === 0) return;

        var length = parseInt(form.getAttribute('data-length'), 10) || boxes.length;

        function sync() {
            var value = '';
            boxes.forEach(function (box) {
                value += (box.value || '').replace(/\D/g, '');
            });
            hidden.value = value;
            return value;
        }

        boxes.forEach(function (box, index) {
            box.addEventListener('input', function () {
                // A phone keyboard can deliver the whole code into one box when
                // the OS autofills a one-time code, so spread it rather than
                // truncating to a single digit and losing the rest.
                var digits = this.value.replace(/\D/g, '');

                if (digits.length > 1) {
                    spread(digits, index);
                    return;
                }

                this.value = digits.slice(0, 1);
                if (this.value && index < boxes.length - 1) boxes[index + 1].focus();
                sync();
            });

            box.addEventListener('keydown', function (event) {
                if (event.key === 'Backspace' && !this.value && index > 0) {
                    boxes[index - 1].focus();
                }
                if (event.key === 'ArrowLeft' && index > 0) boxes[index - 1].focus();
                if (event.key === 'ArrowRight' && index < boxes.length - 1) boxes[index + 1].focus();
            });

            box.addEventListener('paste', function (event) {
                event.preventDefault();
                var pasted = (event.clipboardData || window.clipboardData).getData('text');
                spread(pasted.replace(/\D/g, ''), index);
            });

            box.addEventListener('focus', function () {
                this.select();
            });
        });

        function spread(digits, from) {
            for (var i = 0; i < boxes.length - from; i++) {
                boxes[from + i].value = digits[i] || '';
            }
            var landing = Math.min(from + digits.length, boxes.length - 1);
            boxes[landing].focus();
            sync();
        }

        // Submitting an incomplete code would spend one of the five attempts
        // OtpService allows, so it is caught here before it costs anything.
        form.addEventListener('submit', function (event) {
            if (sync().length !== length) {
                event.preventDefault();
                var first = boxes.find(function (box) { return !box.value; }) || boxes[0];
                first.focus();
            }
        });
    }

    /**
     * The resend cooldown.
     *
     * Cosmetic only. OtpService::secondsUntilResend() is what actually refuses
     * an early resend, and the rate limiter sits behind that — this just stops
     * somebody clicking a button that was always going to say no.
     */
    function initResendTimer() {
        var button = document.getElementById('sh-otp-resend');
        var label = document.getElementById('sh-otp-timer');
        if (!button || !label) return;

        var remaining = parseInt(button.getAttribute('data-wait'), 10) || 60;

        button.disabled = true;
        label.hidden = false;

        var tick = setInterval(function () {
            remaining -= 1;

            if (remaining <= 0) {
                clearInterval(tick);
                button.disabled = false;
                label.hidden = true;
                return;
            }

            label.textContent = 'You can ask for another in ' + remaining + 's';
        }, 1000);

        label.textContent = 'You can ask for another in ' + remaining + 's';
    }

    initCodeBoxes();
    initResendTimer();
})();
