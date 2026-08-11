"use strict";

// Class Definition
var KTAuthNewPassword = function () {
      // Elements
      var form;
      var submitButton;
      var validator;
      var passwordMeter;

      var handleForm = function () {
            // Init form validation rules using FormValidation plugin
            validator = FormValidation.formValidation(
                  form,
                  {
                        fields: {
                              'password': {
                                    validators: {
                                          notEmpty: {
                                                message: 'The password is required'
                                          },
                                          callback: {
                                                message: 'Please enter a stronger password',
                                                callback: function (input) {
                                                      if (input.value.length > 0) {
                                                            return validatePassword();
                                                      }
                                                }
                                          }
                                    }
                              },
                              'password_confirmation': {
                                    validators: {
                                          notEmpty: {
                                                message: 'The password confirmation is required'
                                          },
                                          identical: {
                                                compare: function () {
                                                      return form.querySelector('[name="password"]').value;
                                                },
                                                message: 'The password and its confirmation do not match'
                                          }
                                    }
                              },
                        },
                        plugins: {
                              trigger: new FormValidation.plugins.Trigger({
                                    event: {
                                          password: false
                                    }
                              }),
                              bootstrap: new FormValidation.plugins.Bootstrap5({
                                    rowSelector: '.fv-row',
                                    eleInvalidClass: '',
                                    eleValidClass: ''
                              })
                        }
                  }
            );

            form.querySelector('input[name="password"]').addEventListener('input', function () {
                  if (this.value.length > 0) {
                        validator.updateFieldStatus('password', 'NotValidated');
                  }
            });
      };

      var handleSubmitAjax = function () {
            submitButton.addEventListener('click', function (e) {
                  e.preventDefault();

                  validator.revalidateField('password');

                  validator.validate().then(function (status) {
                        if (status === 'Valid') {
                              submitButton.setAttribute('data-kt-indicator', 'on');
                              submitButton.disabled = true;

                              axios.post(form.action, new FormData(form))
                                    .then(function (response) {
                                          // Success popup
                                          Swal.fire({
                                                icon: 'success',
                                                title: 'Password Reset Successful',
                                                text: response.data.message || 'Your password has been updated.',
                                                buttonsStyling: false,
                                                confirmButtonText: 'Ok, got it!',
                                                customClass: {
                                                      confirmButton: 'btn btn-primary'
                                                }
                                          }).then(function (result) {
                                                if (result.isConfirmed) {
                                                      form.reset();
                                                      passwordMeter.reset();

                                                      var redirectUrl = form.getAttribute('data-kt-redirect-url');
                                                      if (redirectUrl) {
                                                            window.location.href = redirectUrl;
                                                      }
                                                }
                                          });
                                    })
                                    .catch(function (error) {
                                          let message = "Something went wrong, please try again.";

                                          if (error.response) {
                                                if (error.response.data && error.response.data.message) {
                                                      message = error.response.data.message;
                                                } else if (error.response.data && error.response.data.errors) {
                                                      // Laravel validation errors
                                                      const errors = Object.values(error.response.data.errors).flat();
                                                      message = errors.join('<br>');
                                                }
                                          }

                                          Swal.fire({
                                                icon: 'error',
                                                title: 'Error',
                                                html: message,
                                                buttonsStyling: false,
                                                confirmButtonText: 'Ok, got it!',
                                                customClass: {
                                                      confirmButton: 'btn btn-primary'
                                                }
                                          });
                                    })
                                    .then(function () {
                                          submitButton.removeAttribute('data-kt-indicator');
                                          submitButton.disabled = false;
                                    });
                        } else {
                              Swal.fire({
                                    icon: 'error',
                                    title: 'Validation Error',
                                    text: 'Please fix the errors in the form before submitting.',
                                    buttonsStyling: false,
                                    confirmButtonText: 'Ok, got it!',
                                    customClass: {
                                          confirmButton: 'btn btn-primary'
                                    }
                              });
                        }
                  });
            });
      };

      var validatePassword = function () {
            return passwordMeter.getScore() > 50;
      };

      return {
            // public functions
            init: function () {
                  form = document.querySelector('#kt_new_password_form');
                  submitButton = document.querySelector('#kt_new_password_submit');
                  passwordMeter = KTPasswordMeter.getInstance(form.querySelector('[data-kt-password-meter="true"]'));

                  handleForm();
                  handleSubmitAjax();
            }
      };
}();

// On document ready
KTUtil.onDOMContentLoaded(function () {
      KTAuthNewPassword.init();
});
