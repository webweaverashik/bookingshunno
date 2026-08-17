/*
|------------------------------------------------------------------------------
| Staff management
|------------------------------------------------------------------------------
| Depends on js/admin/shunno.js.
|
| One modal serving both create and edit — the fields are identical and only the
| endpoint, the title and whether the password is required differ.
|
| Nothing here decides who may do what. Every guard that matters (you cannot act
| on yourself, the last Admin is protected) is enforced in UserController and
| comes back as a 422 with a message. The buttons hidden in the Blade are a
| courtesy, not a control.
*/
(function () {
    'use strict';

    var config = window.UsersConfig || {};

    var listEl = document.getElementById('users-list');
    if (!listEl) return;

    var searchEl = document.getElementById('users-search');
    var roleEl = document.getElementById('users-role');
    var statusEl = document.getElementById('users-status');

    var form = document.getElementById('user-form');
    var modalEl = document.getElementById('user-modal');
    var modal = modalEl ? Shunno.modal(modalEl) : null;

    var editingId = null;
    var listRequest = 0;
    var searchTimer = null;

    /* =====================================================================
       The table
       ===================================================================== */

    function load(extra) {
        var token = ++listRequest;
        var query = new URLSearchParams(extra || {});

        if (searchEl && searchEl.value.trim()) query.set('q', searchEl.value.trim());
        if (roleEl && roleEl.value) query.set('role', roleEl.value);
        if (statusEl && statusEl.value) query.set('status', statusEl.value);

        listEl.classList.add('opacity-50');

        Shunno.request(config.listUrl + '?' + query.toString())
            .then(function (payload) {
                if (token !== listRequest) return;
                listEl.innerHTML = payload.data.html;
            })
            .catch(function (error) {
                if (token !== listRequest || error.handled) return;
                Shunno.toast('error', error.message);
            })
            .then(function () {
                if (token === listRequest) listEl.classList.remove('opacity-50');
            });
    }

    // Debounced: a search box that fires per keystroke sends eight requests for
    // "Rahman" and races them against each other.
    if (searchEl) {
        searchEl.addEventListener('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { load({ page: 1 }); }, 350);
        });
    }

    [roleEl, statusEl].forEach(function (el) {
        Shunno.onChange(el, function () { load({ page: 1 }); });
    });

    /* =====================================================================
       Create and edit
       ===================================================================== */

    function openModal(mode, data) {
        if (!modal) return;

        editingId = mode === 'edit' ? data.id : null;

        Shunno.clearErrors(form);
        form.reset();

        document.getElementById('user-modal-title').textContent =
            mode === 'edit' ? 'Edit staff member' : 'Add staff';

        document.getElementById('user-submit-label').textContent =
            mode === 'edit' ? 'Save changes' : 'Create account';

        var passwordLabel = document.getElementById('user-password-label');
        var passwordHint = document.getElementById('user-password-hint');

        if (mode === 'edit') {
            // Never populated — the hash is not readable and would not be sent
            // here if it were. Blank means unchanged, which the hint has to say
            // or somebody will assume the field being empty wiped it.
            passwordLabel.classList.remove('required');
            passwordHint.textContent = 'Leave blank to keep their current password.';

            Shunno.fill(form, data);
        } else {
            passwordLabel.classList.add('required');
            passwordHint.textContent = 'At least 8 characters, checked against known breached passwords.';
            form.querySelector('[name="is_active"]').checked = true;
        }

        modal.show();
    }

    var createButton = document.getElementById('user-create');

    if (createButton) {
        createButton.addEventListener('click', function () { openModal('create'); });
    }

    // Delegated: the table is replaced on every filter change, so listeners
    // bound to the buttons themselves would die with the first swap.
    listEl.addEventListener('click', function (event) {
        var edit = event.target.closest('[data-user-edit]');
        var toggle = event.target.closest('[data-user-toggle]');
        var remove = event.target.closest('[data-user-delete]');
        var page = event.target.closest('[data-users-pager] a');

        if (page) {
            event.preventDefault();
            var pageNo = new URL(page.href, window.location.origin).searchParams.get('page');
            if (pageNo) load({ page: pageNo });
            return;
        }

        if (edit) {
            var id = edit.getAttribute('data-user-edit');

            Shunno.request(config.baseUrl + '/' + id + '/edit')
                .then(function (payload) { openModal('edit', payload.data); })
                .catch(function (error) {
                    if (!error.handled) Shunno.toast('error', error.message);
                });

            return;
        }

        if (toggle) {
            var active = toggle.getAttribute('data-user-active') === '1';
            var name = toggle.getAttribute('data-user-name');

            Shunno.confirm({
                title: active ? 'Deactivate ' + name + '?' : 'Let ' + name + ' sign in again?',
                text: active
                    ? 'They will be signed out everywhere immediately and will not be able to sign back in.'
                    : 'They will be able to sign in with their existing password.',
                confirmText: active ? 'Yes, deactivate' : 'Yes, activate',
                danger: active
            }).then(function (ok) {
                if (!ok) return;

                Shunno.request(config.baseUrl + '/' + toggle.getAttribute('data-user-toggle') + '/toggle', {
                    method: 'POST'
                })
                    .then(function (payload) {
                        Shunno.toast('success', payload.message);
                        load();
                    })
                    .catch(function (error) {
                        if (!error.handled) Shunno.toast('error', error.message);
                    });
            });

            return;
        }

        if (remove) {
            var who = remove.getAttribute('data-user-name');

            Shunno.confirm({
                title: 'Remove ' + who + '?',
                text: 'Their name stays on every reservation they decided and every payment they recorded — that is deliberate. They will not be able to sign in.',
                confirmText: 'Yes, remove',
                danger: true
            }).then(function (ok) {
                if (!ok) return;

                Shunno.request(config.baseUrl + '/' + remove.getAttribute('data-user-delete'), {
                    method: 'DELETE'
                })
                    .then(function (payload) {
                        Shunno.toast('success', payload.message);
                        load();
                    })
                    .catch(function (error) {
                        if (!error.handled) Shunno.toast('error', error.message);
                    });
            });
        }
    });

    /* =====================================================================
       Saving
       ===================================================================== */

    if (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var button = form.querySelector('[data-submit]');
            var url = editingId ? config.baseUrl + '/' + editingId : config.storeUrl;

            Shunno.clearErrors(form);
            Shunno.busy(button, true);

            Shunno.request(url, { method: 'POST', body: new FormData(form) })
                .then(function (payload) {
                    Shunno.toast('success', payload.message);
                    if (modal) modal.hide();
                    load();
                })
                .catch(function (error) {
                    if (error.handled) return;

                    if (error.errors) {
                        Shunno.showErrors(form, error.errors);
                        return;
                    }

                    // The refusals from UserController — last Admin, acting on
                    // yourself — arrive here rather than as field errors,
                    // because they are about the situation, not about a field.
                    Shunno.toast('error', error.message);
                })
                .then(function () {
                    Shunno.busy(button, false);
                });
        });
    }
})();
