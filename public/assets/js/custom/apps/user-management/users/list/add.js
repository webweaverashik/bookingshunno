"use strict";

// Class definition
var KTUsersAddUser = function () {
    // Shared variables
    const element = document.getElementById('kt_modal_add_user');
    const form = element.querySelector('#kt_modal_add_user_form');
    const modal = new bootstrap.Modal(element);

    // Init add schedule modal
    var initAddUser = () => {

        // Cancel button handler
        const cancelButton = element.querySelector('[data-kt-users-modal-action="cancel"]');
        cancelButton.addEventListener('click', e => {
            e.preventDefault();

            form.reset(); // Reset form			
            modal.hide();
        });

        // Close button handler
        const closeButton = element.querySelector('[data-kt-users-modal-action="close"]');
        closeButton.addEventListener('click', e => {
            e.preventDefault();

            form.reset(); // Reset form			
            modal.hide();
        });
    }

    return {
        // Public functions
        init: function () {
            initAddUser();
        }
    };
}();

var KTUsersEditUser = function () {
    // Shared variables
    const element = document.getElementById('kt_modal_edit_user');
    const form = element.querySelector('#kt_modal_edit_user_form');
    const modal = new bootstrap.Modal(element);

    // Init add schedule modal
    var initEditUser = () => {

        // Cancel button handler
        const cancelButton = element.querySelector('[data-kt-users-modal-action="cancel"]');
        cancelButton.addEventListener('click', e => {
            e.preventDefault();

            form.reset(); // Reset form			
            modal.hide();
        });

        // Close button handler
        const closeButton = element.querySelector('[data-kt-users-modal-action="close"]');
        closeButton.addEventListener('click', e => {
            e.preventDefault();

            form.reset(); // Reset form			
            modal.hide();
        });
    }

    return {
        // Public functions
        init: function () {
            initEditUser();
        }
    };
}();

// On document ready
KTUtil.onDOMContentLoaded(function () {
    KTUsersAddUser.init();
    KTUsersEditUser.init();
});