<?php
/**
 * Frontend Links - Redirect Interstitial Page
 * =============================================
 *
 * Branded page shown briefly before redirecting a short URL.
 * Displays OG/Twitter metadata for rich social link previews
 * (Discord, Slack, Twitter, Facebook, etc.).
 *
 * Redirect mechanism (belt & suspenders):
 *   1. JavaScript redirect via assets/js/redirect.js (1000ms delay)
 *   2. <meta http-equiv="refresh"> fallback if JS is disabled
 *
 * To change the delay, update BOTH:
 *   - The meta refresh "content" value below
 *   - The setTimeout in assets/js/redirect.js
 *
 * Available variables (set by fl_serve_redirect_page() in functions.php):
 *   $keyword    - YOURLS keyword (e.g. "vrc")
 *   $url        - Destination long URL
 *   $linkTitle  - Title of the short URL from YOURLS DB
 *   $shortUrl   - Full short URL without subdirectory (e.g. "https://example.com/vrc")
 *   $authorName - Profile name from Frontend Links settings
 *   $siteImage  - Profile avatar URL (may be empty)
 *   $ogTitle    - Formatted title: "Author → Link Title"
 *   $cleanShort - Short URL without protocol (e.g. "example.com/vrc")
 *   $cleanDest  - Destination URL without protocol/query (e.g. "github.com/user")
 *   $assetsUrl  - Base URL to the assets/ directory
 *   $e          - Shorthand for fl_escape() function
 *
 * @see includes/functions.php  fl_serve_redirect_page()
 * @see assets/css/pages.css    Stylesheet
 * @see assets/js/redirect.js   Redirect logic
 */

if (!defined('YOURLS_ABSPATH')) die();
?>
<!DOCTYPE html>
<html lang="<?= $e(substr(yourls_get_locale(), 0, 2)) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $e($ogTitle) ?></title>

    <!-- SEO -->
    <link rel="canonical" href="<?= $e($shortUrl) ?>">
    <meta name="description" content="<?= $e($ogTitle) ?>">
    <meta name="author" content="<?= $e($authorName) ?>">

    <!-- Open Graph (Facebook, Discord, Slack...) -->
    <meta property="og:title" content="<?= $e($ogTitle) ?>">
    <meta property="og:url" content="<?= $e($shortUrl) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= $e($authorName) ?>">
    <meta property="og:description" content="<?= $e($linkTitle) ?>">
    <?php if ($siteImage): ?>
    <meta property="og:image" content="<?= $e($siteImage) ?>">
    <?php endif; ?>

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= $e($ogTitle) ?>">
    <meta name="twitter:description" content="<?= $e($linkTitle) ?>">
    <?php if ($siteImage): ?>
    <meta name="twitter:image" content="<?= $e($siteImage) ?>">
    <?php endif; ?>

    <!-- Schema.org / JSON-LD -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebPage",
        "name": <?= json_encode($ogTitle) ?>,
        "url": <?= json_encode($shortUrl) ?>,
        "author": {
            "@type": "Person",
            "name": <?= json_encode($authorName) ?>
        }<?php if ($siteImage): ?>,
        "image": <?= json_encode($siteImage) ?>
        <?php endif; ?>
    }
    </script>

    <!-- Auto redirect (fallback for no-JS) -->
    <meta http-equiv="refresh" content="1;url=<?= $e($url) ?>">

    <!-- Redirect URL for JS (read by redirect.js) -->
    <meta name="fl-redirect-url" content="<?= $e($url) ?>">

    <!-- Stylesheet -->
    <link rel="stylesheet" href="<?= $e($assetsUrl) ?>/css/pages.css">
</head>
<body>
    <div class="card">
        <div class="author"><?= $e($authorName) ?></div>
        <div class="title"><?= $e($linkTitle) ?></div>
        <div class="dest">
            <a href="<?= $e($shortUrl) ?>" title="<?= $e($shortUrl) ?>" rel="noopener"><?= $e($cleanShort) ?></a>
            <span class="arrow">&rarr;</span>
            <a href="<?= $e($url) ?>" title="<?= $e($url) ?>" rel="noopener"><?= $e($cleanDest) ?></a>
        </div>
        <div class="dots">
            <span></span><span></span><span></span>
        </div>
    </div>

    <!-- Redirect script -->
    <script src="<?= $e($assetsUrl) ?>/js/redirect.js"></script>
</body>
</html>
