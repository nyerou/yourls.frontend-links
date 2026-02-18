<?php
/**
 * Default Theme - 404 Error Page
 * ================================
 *
 * Available variables (set by fl_serve_404_page() in functions.php):
 *   $request, $homeUrl, $authorName
 *   $sharedAssetsUrl  - Plugin shared assets
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
    <title>404 - <?= $e($authorName) ?></title>
    <meta name="robots" content="noindex">

    <link rel="stylesheet" href="<?= $e($themeAssetsUrl) ?>/css/pages.css">
</head>
<body>
    <div class="fl-card">
        <div class="fl-code">404</div>
        <div class="fl-message"><?= $e(yourls__('This link does not exist.', 'frontend-links')) ?></div>
        <div class="fl-path">/<?= $e($request) ?></div>
        <a href="<?= $e($homeUrl) ?>" class="fl-btn"><?= $e(yourls__('Back to homepage', 'frontend-links')) ?></a>
    </div>
</body>
</html>
