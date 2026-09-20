/**
 * Registers the keyboard shortcut the Gantt link in the project header
 * advertises.
 *
 * This is loaded on every page rather than only on the chart, because the
 * point of the shortcut is to reach the chart from the board or the list.
 * Kanboard exposes KB.onKey for exactly this, and the link already carries
 * the class the handler looks for.
 */
(function () {
    'use strict';

    function goToGantt() {
        var link = document.querySelector('a.view-gantt');

        if (link && link.href) {
            window.location.href = link.href;
        }
    }

    function register() {
        if (!window.KB || typeof window.KB.onKey !== 'function') {
            return;
        }

        // Matches how Kanboard registers its own view shortcuts ("v b", "v l").
        window.KB.onKey('v+g', goToGantt);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', register);
    } else {
        register();
    }
}());
