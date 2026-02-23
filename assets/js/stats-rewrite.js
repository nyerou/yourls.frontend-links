/**
 * Frontend Links - Stats Link Subdirectory Fix
 * ==============================================
 *
 * Injected into ALL YOURLS admin pages via the html_head hook.
 *
 * Problem: The shorturl filter strips the YOURLS subdirectory from
 * displayed short URLs (e.g. "https://example.com/keyword" instead of
 * "https://example.com/-/keyword"). YOURLS admin JS then builds stats
 * links by appending "+" to those stripped URLs, producing broken links
 * like "https://example.com/keyword+" that don't reach YOURLS.
 *
 * Fix: This script detects stats links (ending in "+" or "+all") that
 * are missing the YOURLS subdirectory and adds it back, so they become
 * "https://example.com/-/keyword+" and reach YOURLS correctly.
 *
 * Configuration is read from the script tag's data attributes:
 *   data-base-path  — YOURLS subdirectory path (e.g. "/-")
 *   data-root-url   — Root URL without subdirectory (e.g. "https://example.com")
 *
 * Uses MutationObserver to catch dynamically loaded table rows (AJAX).
 *
 * @see plugin.php  fl_inject_stats_fix_js() injects this script
 */

(function () {
    'use strict';

    // Read config from our own script tag
    var script = document.currentScript;
    if (!script) return;

    var basePath = script.getAttribute('data-base-path') || '';
    var rootUrl = script.getAttribute('data-root-url') || '';

    // Only fix if there is a subdirectory
    if (!basePath || !rootUrl) return;

    // Match stats URLs: origin/keyword+ or origin/keyword+all (no subdirectory)
    // Does NOT match URLs that already contain the basePath (extra path segments)
    var plusPattern = /^(https?:\/\/[^\/]+)\/([^\/+]+)\+(all)?$/;

    function fixLink(a) {
        var href = a.getAttribute('href');
        if (!href || href.indexOf('+') === -1) return;

        var m = href.match(plusPattern);
        if (!m) return;

        // Only fix links on our own domain
        if (m[1] !== rootUrl) return;

        var keyword = m[2];
        var allSuffix = m[3] || '';

        // Add the YOURLS subdirectory back
        a.setAttribute('href', rootUrl + basePath + '/' + keyword + '+' + allSuffix);
    }

    function fixAll() {
        document.querySelectorAll('a[href*="+"]').forEach(fixLink);
    }

    // Initial pass
    fixAll();

    // Watch for dynamically added rows (YOURLS admin table loads via AJAX)
    var observer = new MutationObserver(function (mutations) {
        for (var i = 0; i < mutations.length; i++) {
            if (mutations[i].addedNodes.length > 0) {
                fixAll();
                return;
            }
        }
    });

    observer.observe(document.body, { childList: true, subtree: true });
})();
