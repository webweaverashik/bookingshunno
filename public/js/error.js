/*
|------------------------------------------------------------------------------
| Error pages
|------------------------------------------------------------------------------
| Two small things, neither of them load-bearing: a countdown that sends
| somebody home, and a Go back button.
|
| Depends on nothing. Not shunno.js, not jQuery, not Metronic. An error page is
| shown when something has already gone wrong, and a script that needs three
| other files to load first is a script that will not run on the day it matters.
|
| THE COUNTDOWN CAN ALWAYS BE STOPPED, and that is not a detail. An auto-redirect
| with no escape hatch takes the page away from somebody who is still reading the
| address that failed, or copying it into an email to the studio. It also stops
| itself the moment there is any sign of a person: a click, a key, a scroll.
*/
(function () {
    'use strict';

    var timerEl = document.querySelector('[data-error-timer]');
    var homeEl = document.querySelector('[data-error-home]');
    var backEl = document.querySelector('[data-error-back]');

    /* ---------- Go back ----------

       Revealed here rather than rendered visible, because without JavaScript it
       would be a button that does nothing. Hidden too when there is no history
       to go back to — a tab opened straight onto this URL has none, and the
       button would silently fail. */
    if (backEl && window.history.length > 1) {
        backEl.hidden = false;
        backEl.addEventListener('click', function () {
            window.history.back();
        });
    }

    if (!timerEl || !homeEl) return;

    var remaining = parseInt(timerEl.dataset.seconds, 10);

    // 0 or missing disables the redirect. Pages that should never move on their
    // own — a 500, say, where somebody may be reading the page to report it —
    // pass 0 and get no countdown at all.
    if (!isFinite(remaining) || remaining <= 0) return;

    var interval = null;
    var stopped = false;

    function render() {
        timerEl.textContent = '';

        var text = document.createTextNode(
            'Taking you back in ' + remaining + ' second' + (remaining === 1 ? '' : 's') + '. '
        );

        var stop = document.createElement('button');
        stop.type = 'button';
        stop.textContent = 'Stay here';
        stop.addEventListener('click', halt);

        timerEl.appendChild(text);
        timerEl.appendChild(stop);
    }

    function halt() {
        if (stopped) return;

        stopped = true;
        window.clearInterval(interval);

        // Cleared rather than left saying "stopped". Once it is not counting,
        // the line has nothing to tell anyone, and a leftover message is just
        // something else on a page that already reports a problem.
        timerEl.textContent = '';
    }

    function tick() {
        remaining--;

        if (remaining <= 0) {
            window.clearInterval(interval);
            window.location.href = homeEl.getAttribute('href');
            return;
        }

        render();
    }

    render();
    interval = window.setInterval(tick, 1000);

    /*
     | Any sign of a person cancels it.
     |
     | Somebody who has started reading, scrolling or selecting the failed
     | address is using this page, and pulling it out from under them mid-word
     | is worse than never having offered the redirect. Passive listeners so
     | none of this touches scroll performance.
     */
    ['click', 'keydown', 'wheel', 'touchstart'].forEach(function (event) {
        document.addEventListener(event, function (e) {
            // Except the button that goes home, which should not be cancelled
            // by the click that pressed it.
            if (e.target && e.target.closest && e.target.closest('[data-error-home]')) return;

            halt();
        }, { passive: true, once: false });
    });
})();
