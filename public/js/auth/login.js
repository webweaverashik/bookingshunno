"use strict";

var KTLoginSetup = function () {

      let form;
      let submitButton;
      let validator;

      // -------------------------------
      // Init validation
      // -------------------------------
      const initValidation = () => {
            validator = FormValidation.formValidation(form, {
                  fields: {
                        fields: {
                              'email': {
                                    validators: {
                                          notEmpty: {
                                                message: 'Please, provide an email'
                                          },
                                          regexp: {
                                                regexp: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
                                                message: 'It\'s not a valid email address',
                                          },
                                    }
                              },
                              'password': {
                                    validators: {
                                          notEmpty: {
                                                message: 'Please, provide the password'
                                          }
                                    }
                              }
                        }

                  },
                  plugins: {
                        trigger: new FormValidation.plugins.Trigger(),
                        bootstrap: new FormValidation.plugins.Bootstrap5({
                              rowSelector: '.fv-row',
                              eleInvalidClass: '',
                              eleValidClass: ''
                        })
                  }
            });
      };

      // -------------------------------
      // Handle submit
      // -------------------------------
      const handleSubmit = () => {
            form.addEventListener('submit', function (e) {
                  e.preventDefault();

                  toastr.options = {
                        "closeButton": false,
                        "debug": false,
                        "newestOnTop": true,
                        "progressBar": true,
                        "positionClass": "toastr-top-right",
                        "preventDuplicates": false,
                        "onclick": null,
                        "showDuration": "300",
                        "hideDuration": "1000",
                        "timeOut": "2000",
                        "extendedTimeOut": "1000",
                        "showEasing": "swing",
                        "hideEasing": "linear",
                        "showMethod": "fadeIn",
                        "hideMethod": "fadeOut",
                  };

                  validator.validate().then(function (status) {

                        if (status !== 'Valid') {
                              toastr.warning(
                                    'Please, provide email and password.'
                              );
                              return;
                        }

                        submitButton.setAttribute('data-kt-indicator', 'on');
                        submitButton.disabled = true;

                        axios.post(form.action, new FormData(form))
                              .then(response => {

                                    toastr.success(
                                          response.data.message || 'Successfully logged in.'
                                    );

                                    setTimeout(() => {
                                          window.location.href = response.data.redirect;
                                    }, 1000);

                              })
                              .catch(error => {

                                    let message = 'Login failed';

                                    if (error.response?.data?.message) {
                                          message = error.response.data.message;
                                    }

                                    toastr.error(message);
                              })
                              .finally(() => {
                                    submitButton.removeAttribute('data-kt-indicator');
                                    submitButton.disabled = false;
                              });
                  });
            });
      };

      // -------------------------------
      // Init
      // -------------------------------
      return {
            init: function () {
                  form = document.querySelector('#kt_sign_in_form');
                  submitButton = document.querySelector('#kt_sign_in_submit');

                  if (!form || !submitButton) {
                        console.error('Login form or submit button not found');
                        return;
                  }

                  initValidation();
                  handleSubmit();
            }
      };
}();

// Run on DOM ready
KTUtil.onDOMContentLoaded(function () {
      KTLoginSetup.init();
});
