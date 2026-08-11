"use strict";

// Class Definition
var KTAuthResetPassword = function () {
      // Elements
      var form;
      var submitButton;
      var validator;

      var handleForm = function (e) {
            // Init form validation rules. For more info check the FormValidation plugin's official documentation:https://formvalidation.io/
            validator = FormValidation.formValidation(
                  form,
                  {
                        fields: {
                              'email': {
                                    validators: {
                                          regexp: {
                                                regexp: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
                                                message: 'The value is not a valid email address',
                                          },
                                          notEmpty: {
                                                message: 'Email address is required'
                                          }
                                    }
                              }
                        },
                        plugins: {
                              trigger: new FormValidation.plugins.Trigger(),
                              bootstrap: new FormValidation.plugins.Bootstrap5({
                                    rowSelector: '.fv-row',
                                    eleInvalidClass: '',  // comment to enable invalid state icons
                                    eleValidClass: '' // comment to enable valid state icons
                              })
                        }
                  }
            );

      }

      var handleSubmitAjax = function () {
            form.addEventListener('submit', function (e) {
                  e.preventDefault(); // prevent default form submission (page reload)

                  validator.validate().then(function (status) {
                        if (status === 'Valid') {
                              submitButton.setAttribute('data-kt-indicator', 'on');
                              submitButton.disabled = true;

                              axios.post(form.getAttribute('action'), new FormData(form))
                                    .then(function (response) {
                                          form.reset();

                                          Swal.fire({
                                                text: "We have sent a password reset link to your email.",
                                                icon: "success",
                                                buttonsStyling: false,
                                                confirmButtonText: "Ok, got it!",
                                                customClass: {
                                                      confirmButton: "btn btn-primary"
                                                }
                                          }).then(function (result) {
                                                if (result.isConfirmed) {
                                                      var redirectUrl = form.getAttribute('data-kt-redirect-url');
                                                      if (redirectUrl) {
                                                            location.href = redirectUrl;
                                                      }
                                                }
                                          });
                                    })
                                    .catch(function (error) {
                                          let message = "Sorry, looks like there are some errors detected, please try again.";

                                          if (error.response && error.response.data && error.response.data.message) {
                                                message = error.response.data.message;
                                          }

                                          Swal.fire({
                                                text: message,
                                                icon: "error",
                                                buttonsStyling: false,
                                                confirmButtonText: "Ok, got it!",
                                                customClass: {
                                                      confirmButton: "btn btn-primary"
                                                }
                                          });
                                    })

                                    .then(function () {
                                          submitButton.removeAttribute('data-kt-indicator');
                                          submitButton.disabled = false;
                                    });
                        } else {
                              Swal.fire({
                                    text: "Sorry, looks like there are some errors detected, please try again.",
                                    icon: "error",
                                    buttonsStyling: false,
                                    confirmButtonText: "Ok, got it!",
                                    customClass: {
                                          confirmButton: "btn btn-primary"
                                    }
                              });
                        }
                  });
            });
      };


      var isValidUrl = function (url) {
            try {
                  new URL(url);
                  return true;
            } catch (e) {
                  return false;
            }
      }

      // Public Functions
      return {
            // public functions
            init: function () {
                  form = document.querySelector('#kt_password_reset_form');
                  submitButton = document.querySelector('#kt_password_reset_submit');

                  handleForm();

                  if (isValidUrl(form.getAttribute('action'))) {
                        handleSubmitAjax(); // use for ajax submit
                  } else {
                        // handleSubmitDemo(); // used for demo purposes only
                  }
            }
      };
}();

// On document ready
KTUtil.onDOMContentLoaded(function () {
      KTAuthResetPassword.init();
});
