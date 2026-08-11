"use strict";

var KTLoginOtp = (function () {

      var form;
      var submitButton;
      var resendLink;
      var timerLabel;
      var hiddenCode;
      var inputs;
      var length;
      var resendAfter;
      var timerHandle = null;

      var toastrOptions = {
            "closeButton": true,
            "newestOnTop": true,
            "progressBar": true,
            "positionClass": "toastr-top-right",
            "preventDuplicates": false,
            "showDuration": "300",
            "hideDuration": "1000",
            "timeOut": "4000",
            "extendedTimeOut": "1000",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut"
      };

      // -------------------------------
      // Collect the 6 boxes into the hidden field
      // -------------------------------
      var syncCode = function () {
            var value = "";
            inputs.forEach(function (input) {
                  value += (input.value || "").replace(/\D/g, "");
            });
            hiddenCode.value = value;
            return value;
      };

      // -------------------------------
      // Box behaviour: digit-only, auto-advance, backspace, paste
      // -------------------------------
      var bindInputs = function () {
            inputs.forEach(function (input, index) {

                  input.addEventListener("input", function () {
                        this.value = this.value.replace(/\D/g, "").slice(0, 1);
                        if (this.value && index < inputs.length - 1) {
                              inputs[index + 1].focus();
                        }
                        syncCode();
                  });

                  input.addEventListener("keydown", function (e) {
                        if (e.key === "Backspace" && !this.value && index > 0) {
                              inputs[index - 1].focus();
                        }
                  });

                  input.addEventListener("paste", function (e) {
                        e.preventDefault();
                        var pasted = (e.clipboardData || window.clipboardData)
                              .getData("text").replace(/\D/g, "").slice(0, inputs.length);

                        for (var i = 0; i < inputs.length; i++) {
                              inputs[i].value = pasted[i] || "";
                        }
                        syncCode();
                        var next = Math.min(pasted.length, inputs.length - 1);
                        inputs[next].focus();
                  });
            });
      };

      // -------------------------------
      // Resend countdown
      // -------------------------------
      var startTimer = function (seconds) {
            var remaining = seconds;

            resendLink.classList.add("d-none");
            timerLabel.classList.remove("d-none");

            var tick = function () {
                  if (remaining <= 0) {
                        clearInterval(timerHandle);
                        timerHandle = null;
                        timerLabel.classList.add("d-none");
                        resendLink.classList.remove("d-none");
                        return;
                  }
                  timerLabel.textContent = "Resend available in " + remaining + "s";
                  remaining--;
            };

            tick();
            timerHandle = setInterval(tick, 1000);
      };

      // -------------------------------
      // Verify submit
      // -------------------------------
      var handleSubmit = function () {
            form.addEventListener("submit", function (e) {
                  e.preventDefault();
                  toastr.options = toastrOptions;

                  var code = syncCode();
                  if (code.length !== length) {
                        toastr.warning("Please enter the " + length + "-digit code.");
                        return;
                  }

                  submitButton.setAttribute("data-kt-indicator", "on");
                  submitButton.disabled = true;

                  axios.post(form.action, new FormData(form))
                        .then(function (response) {
                              toastr.success(response.data.message || "Login successful!");
                              setTimeout(function () {
                                    window.location.href = response.data.redirect;
                              }, 1000);
                        })
                        .catch(function (error) {
                              var data = error.response ? error.response.data : null;
                              toastr.error((data && data.message) || "Verification failed.");

                              // Pending login is dead — go back to sign in.
                              if (data && data.redirect) {
                                    setTimeout(function () {
                                          window.location.href = data.redirect;
                                    }, 1500);
                                    return;
                              }

                              // Clear boxes for another try.
                              inputs.forEach(function (i) { i.value = ""; });
                              syncCode();
                              inputs[0].focus();
                        })
                        .finally(function () {
                              submitButton.removeAttribute("data-kt-indicator");
                              submitButton.disabled = false;
                        });
            });
      };

      // -------------------------------
      // Resend
      // -------------------------------
      var handleResend = function () {
            resendLink.addEventListener("click", function () {
                  toastr.options = toastrOptions;

                  var data = new FormData();
                  var token = form.querySelector('[name="_token"]');
                  if (token) {
                        data.append("_token", token.value);
                  }

                  axios.post(form.getAttribute("data-kt-resend-url"), data)
                        .then(function (response) {
                              toastr.success(response.data.message || "A new code has been sent.");
                              startTimer(response.data.wait || resendAfter);
                        })
                        .catch(function (error) {
                              var d = error.response ? error.response.data : null;
                              toastr.error((d && d.message) || "Could not resend the code.");

                              if (d && d.redirect) {
                                    setTimeout(function () {
                                          window.location.href = d.redirect;
                                    }, 1500);
                                    return;
                              }

                              if (d && d.wait) {
                                    startTimer(d.wait);
                              }
                        });
            });
      };

      return {
            init: function () {
                  form         = document.querySelector("#kt_login_otp_form");
                  submitButton = document.querySelector("#kt_login_otp_submit");
                  resendLink   = document.querySelector("#kt_login_otp_resend");
                  timerLabel   = document.querySelector("#kt_login_otp_timer");
                  hiddenCode   = document.querySelector("#kt_login_otp_code");
                  inputs       = Array.prototype.slice.call(document.querySelectorAll("[data-otp-input]"));

                  if (!form || !submitButton || inputs.length === 0) {
                        console.error("OTP form elements not found");
                        return;
                  }

                  length      = (typeof BidaOtpConfig !== "undefined" && BidaOtpConfig.length) ? BidaOtpConfig.length : inputs.length;
                  resendAfter = (typeof BidaOtpConfig !== "undefined" && BidaOtpConfig.resendAfter) ? BidaOtpConfig.resendAfter : 60;

                  bindInputs();
                  handleSubmit();
                  handleResend();

                  // A code was just sent when this page loaded — start the cooldown.
                  startTimer(resendAfter);
            }
      };
})();

KTUtil.onDOMContentLoaded(function () {
      KTLoginOtp.init();
});
