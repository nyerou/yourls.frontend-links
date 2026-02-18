<?php
/**
 * Default Theme - Redirect Interstitial Page
 * ============================================
 *
 * Available variables (set by fl_serve_redirect_page() in functions.php):
 *   $keyword, $url, $linkTitle, $shortUrl, $authorName
 *   $metaAuthor, $metaTitle, $metaDescription, $metaImage
 *   $metaType, $metaThemeColor, $twitterCard
 *   $cleanShort, $cleanDest
 *   $sharedAssetsUrl  - Plugin shared assets (redirect.js)
 *   $themeAssetsUrl   - This theme's assets (CSS)
 *   $assetsUrl        - Alias for $sharedAssetsUrl
 *   $e               - Shorthand for fl_escape()
 *
 * @package FrontendLinks
 */

if (!defined('YOURLS_ABSPATH')) die();
?>
<!DOCTYPE html>
<html lang="<?= $e(substr(yourls_get_locale(), 0, 2)) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $e($metaTitle) ?></title>

    <link rel="canonical" href="<?= $e($shortUrl) ?>">
    <meta name="description" content="<?= $e($metaDescription) ?>">
    <meta name="author" content="<?= $e($metaAuthor) ?>">
    <?php if ($metaThemeColor): ?>
    <meta name="theme-color" content="<?= $e($metaThemeColor) ?>">
    <?php endif; ?>

    <!-- Open Graph -->
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
    <meta name="fl-redirect-url" content="<?= $e($url) ?>">

    <!-- Stylesheet -->
    <link rel="stylesheet" href="<?= $e($themeAssetsUrl) ?>/css/pages.css">
</head>
<body>
    <div class="fl-card">
        <div class="fl-author"><?= $e($metaAuthor) ?></div>
        <div class="fl-title"><?= $e($metaTitle) ?></div>
        <div class="fl-dest">
            <a href="<?= $e($shortUrl) ?>" rel="noopener"><?= $e($cleanShort) ?></a>
            <span class="fl-arrow">&rarr;</span>
            <a href="<?= $e($url) ?>" rel="noopener"><?= $e($cleanDest) ?></a>
        </div>
        <div class="fl-loader">
            <div class="fl-spinner"></div>
        </div>
    </div>

    <script src="<?= $e($sharedAssetsUrl) ?>/js/redirect.js"></script>
</body>
</html>
