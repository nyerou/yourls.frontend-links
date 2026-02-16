/**
 * Frontend Links - Redirect Page Script
 * =======================================
 *
 * Performs a JavaScript redirect after a short delay.
 * Works alongside the <meta http-equiv="refresh"> as a fallback:
 *   - JS redirect fires first (faster, uses replaceState)
 *   - Meta refresh fires if JS is disabled
 *
 * The destination URL is read from a <meta> tag in the page:
 *   <meta name="fl-redirect-url" content="https://...">
 *
 * Delay: 1000ms (matches the meta refresh "content=1")
 * To change the delay, update both this value AND the meta refresh.
 *
 * @see templates/redirect.php
 */

(function () {
    'use strict';

    var meta = document.querySelector('meta[name="fl-redirect-url"]');
    if (!meta) return;

    var url = meta.getAttribute('content');
    if (!url) return;

    setTimeout(function () {
        window.location.replace(url);
    }, 1000);
})();
