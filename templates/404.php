<?php
/**
 * Frontend Links - 404 Error Page
 * =================================
 *
 * Displayed when a short URL keyword does not exist.
 * Uses the same glass-card design as the redirect page.
 *
 * Available variables (set by fl_serve_404_page() in functions.php):
 *   $request    - The requested path that was not found
 *   $homeUrl    - Root URL to link back to homepage
 *   $authorName - Profile name from Frontend Links settings
 *   $assetsUrl  - Base URL to the assets/ directory
 *   $e          - Shorthand for fl_escape() function
 *
 * @see includes/functions.php  fl_serve_404_page()
 * @see assets/css/pages.css    Stylesheet (shared with redirect page)
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

    <!-- Stylesheet -->
    <link rel="stylesheet" href="<?= $e($assetsUrl) ?>/css/pages.css">
</head>
<body>
    <div class="card">
        <div class="code">404</div>
        <div class="message"><?= $e(yourls__('This link does not exist.', 'frontend-links')) ?></div>
        <div class="path">/<?= $e($request) ?></div>
        <a href="<?= $e($homeUrl) ?>" class="home-btn"><?= $e(yourls__('Back to homepage', 'frontend-links')) ?></a>
    </div>
</body>
</html>
