/*
|------------------------------------------------------------------------------
| Visitor payment portal — voucher redemption without a page reload
|------------------------------------------------------------------------------
| Progressive enhancement, and that word is doing real work here. The forms this
| script intercepts are ordinary forms posting to ordinary routes that still
| answer with redirects. If this file 404s, if the browser is old, if fetch
| throws — the checkout still completes. Nothing below is load-bearing.
|
| It computes nothing. Both endpoints answer with HTML that Blade rendered, and
| this only puts that HTML where the old HTML was. No taka figure is ever built
| in the browser.
|
| Deliberately NOT intercepted: the Pay online form. That one is supposed to
| leave the page.
|
| Vanilla, no framework, no build step, per the project rules.
*/

(function () {
      'use strict';

      var body = document.querySelector('[data-pay-body]');

      if (!body || !window.fetch) {
            return;
      }

      var tokenTag = document.querySelector('meta[name="csrf-token"]');
      var csrf = tokenTag ? tokenTag.getAttribute('content') : '';

      // The entry form as it was rendered, kept so "Not now" can restore it
      // without asking the server for markup it already sent us once.
      var entryMarkup = '';

      function panel() {
            return body.querySelector('[data-voucher-panel]');
      }

      /* Remember the entry form, and reveal the cancel button — it is hidden in
         the markup because without this script there is nothing for it to do. */
      function settle() {
            var box = panel();

            if (!box) {
                  return;
            }

            if (box.querySelector('[data-voucher-check]')) {
                  entryMarkup = box.innerHTML;
            }

            var cancel = box.querySelector('[data-voucher-cancel]');

            if (cancel) {
                  cancel.hidden = false;
            }
      }

      /* Messages come from the server as plain strings and are inserted as text,
         never as markup. */
      function flash(message, tone) {
            var slot = body.querySelector('[data-pay-flash]');

            if (!slot) {
                  return;
            }

            slot.textContent = '';

            if (!message) {
                  return;
            }

            var note = document.createElement('div');

            note.className = 'pay-flash pay-flash--' + tone;
            note.textContent = message;
            slot.appendChild(note);

            window.scrollTo({ top: 0, behavior: 'smooth' });
      }

      function busy(form, on) {
            var button = form.querySelector('button[type="submit"]');

            form.classList.toggle('is-busy', on);

            if (button) {
                  button.disabled = on;
            }
      }

      function send(form) {
            return fetch(form.getAttribute('action'), {
                  method: 'POST',
                  credentials: 'same-origin',
                  headers: {
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json'
                  },
                  body: new FormData(form)
            }).then(function (response) {
                  return response.json()
                        .catch(function () {
                              return {};
                        })
                        .then(function (payload) {
                              return { ok: response.ok, status: response.status, payload: payload };
                        });
            });
      }

      /* Anything unexpected — a 419 expired token, a 500, a proxy returning HTML —
         hands the submission back to the browser rather than leaving the visitor
         looking at a button that did nothing. */
      function giveUp(form) {
            form.removeEventListener('submit', onSubmit);
            HTMLFormElement.prototype.submit.call(form);
      }

      function onSubmit(event) {
            var form = event.target;

            if (!form.matches('[data-voucher-check], [data-voucher-apply]')) {
                  return;
            }

            event.preventDefault();

            var applying = form.matches('[data-voucher-apply]');

            busy(form, true);
            flash(null);

            send(form).then(function (result) {
                  // 429 from the voucher-attempt limiter arrives without our envelope.
                  if (result.status === 429) {
                        busy(form, false);
                        flash('Too many attempts. Please wait a minute and try again.', 'bad');
                        return;
                  }

                  if (result.status === 422 && result.payload && result.payload.message) {
                        busy(form, false);
                        flash(result.payload.message, 'bad');
                        return;
                  }

                  if (!result.ok || !result.payload || !result.payload.data) {
                        giveUp(form);
                        return;
                  }

                  var data = result.payload.data;

                  if (applying) {
                        // The redemption changed the money, the status and the receipts,
                        // so the server sent the whole body back rather than a patch.
                        body.innerHTML = data.html;
                        settle();
                        flash(result.payload.message, data.settled ? 'ok' : 'note');
                        return;
                  }

                  var box = panel();

                  if (box) {
                        box.innerHTML = data.html;
                        settle();
                  }
            }).catch(function () {
                  // Offline, or the request never completed. The plain form post is
                  // the honest fallback.
                  giveUp(form);
            });
      }

      body.addEventListener('submit', onSubmit);

      body.addEventListener('click', function (event) {
            var cancel = event.target.closest('[data-voucher-cancel]');

            if (!cancel) {
                  return;
            }

            event.preventDefault();

            var box = panel();

            if (box && entryMarkup) {
                  box.innerHTML = entryMarkup;
                  settle();
                  flash(null);
            }
      });

      settle();
})();