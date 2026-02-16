<?php
/**
 * Frontend Links - Redirect Interstitial Page
 * =============================================
 *
 * Branded page shown briefly before redirecting a short URL.
 * Displays OG/Twitter metadata fetched from the TARGET page for
 * rich social link previews (Discord, Slack, Twitter, Facebook, etc.).
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
 *   $keyword         - YOURLS keyword (e.g. "vrc")
 *   $url             - Destination long URL
 *   $linkTitle       - Title of the short URL from YOURLS DB
 *   $shortUrl        - Full short URL without subdirectory
 *   $authorName      - Profile name from Frontend Links settings
 *   $metaAuthor      - Shortener domain (e.g. "nyerou.link")
 *   $metaTitle       - Target page's <title> (fallback: YOURLS title)
 *   $metaDescription - Target page's og:description (fallback: YOURLS title)
 *   $metaImage       - Target page's og:image (fallback: profile avatar)
 *   $metaType        - Target page's og:type (fallback: "website")
 *   $metaThemeColor  - Target page's theme-color (may be empty)
 *   $twitterCard     - "summary_large_image" if image exists, else "summary"
 *   $cleanShort      - Short URL without protocol
 *   $cleanDest       - Destination URL without protocol/query
 *   $assetsUrl       - Base URL to the assets/ directory
 *   $e               - Shorthand for fl_escape() function
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
    <title><?= $e($metaTitle) ?></title>

    <!-- SEO -->
    <link rel="canonical" href="<?= $e($shortUrl) ?>">
    <meta name="description" content="<?= $e($metaDescription) ?>">
    <meta name="author" content="<?= $e($metaAuthor) ?>">
    <?php if ($metaThemeColor): ?>
    <meta name="theme-color" content="<?= $e($metaThemeColor) ?>">
    <?php endif; ?>

    <!-- Open Graph (Facebook, Discord, Slack...) -->
    <meta property="og:title" content="<?= $e($metaTitle) ?>">
    <meta property="og:url" content="<?= $e($shortUrl) ?>">
    <meta property="og:type" content="<?= $e($metaType) ?>">
    <meta property="og:site_name" content="<?= $e($metaAuthor) ?>">
    <meta property="og:description" content="<?= $e($metaDescription) ?>">
    <?php if ($metaImage): ?>
    <meta property="og:image" content="<?= $e($metaImage) ?>">
    <?php endif; ?>

    <!-- Twitter Card -->
    <meta name="twitter:card" content="<?= $e($twitterCard) ?>">
    <meta name="twitter:title" content="<?= $e($metaTitle) ?>">
    <meta name="twitter:description" content="<?= $e($metaDescription) ?>">
    <?php if ($metaImage): ?>
    <meta name="twitter:image" content="<?= $e($metaImage) ?>">
    <?php endif; ?>

    <!-- Schema.org / JSON-LD -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebPage",
        "name": <?= json_encode($metaTitle) ?>,
        "url": <?= json_encode($shortUrl) ?>,
        "description": <?= json_encode($metaDescription) ?>,
        "author": {
            "@type": "Organization",
            "name": <?= json_encode($metaAuthor) ?>
        }<?php if ($metaImage): ?>,
        "image": <?= json_encode($metaImage) ?>
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
        <div class="author"><?= $e($metaAuthor) ?></div>
        <div class="title"><?= $e($metaTitle) ?></div>
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
