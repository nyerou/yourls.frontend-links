<?php
/**
 * Default Theme - Homepage Template
 * ===================================
 *
 * Minimal, neutral link-in-bio page. No frameworks, no particles.
 *
 * Available variables (set by fl_render_page() in render.php):
 *   $profileName, $profileBio, $profileAvatar, $initials
 *   $metaTitle, $metaDescription, $siteUrl
 *   $sharedAssetsUrl  - Plugin shared assets (Font Awesome)
 *   $themeAssetsUrl   - This theme's assets (CSS)
 *   $assetsUrl        - Alias for $sharedAssetsUrl
 *   $adminPageUrl, $htmlLang, $sections, $linksBySection, $e
 *
 * @package FrontendLinks
 */

if (!defined('YOURLS_ABSPATH')) die();
?>
<!DOCTYPE html>
<html lang="<?= $e($htmlLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= $e($metaTitle) ?></title>
    <meta name="description" content="<?= $e($metaDescription) ?>">
    <meta name="author" content="<?= $e($profileName) ?>">
    <link rel="canonical" href="<?= $e($siteUrl) ?>">

    <!-- Open Graph -->
    <meta property="og:title" content="<?= $e($metaTitle) ?>">
    <meta property="og:description" content="<?= $e($metaDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?= $e($siteUrl) ?>">
    <?php if ($profileAvatar): ?>
    <meta property="og:image" content="<?= $e($profileAvatar) ?>">
    <?php endif; ?>

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= $e($metaTitle) ?>">
    <meta name="twitter:description" content="<?= $e($metaDescription) ?>">

    <!-- Styles -->
    <link rel="stylesheet" href="<?= $e($sharedAssetsUrl) ?>/css/all.min.css">
    <link rel="stylesheet" href="<?= $e($themeAssetsUrl) ?>/css/home.css">

    <!-- Schema.org structured data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Person",
        "name": <?= json_encode($profileName) ?>,
        "description": <?= json_encode($profileBio) ?>,
        "url": <?= json_encode($siteUrl) ?>
        <?php if ($profileAvatar): ?>,
        "image": <?= json_encode($profileAvatar) ?>
        <?php endif; ?>
    }
    </script>
</head>
<body>
    <main class="fl-page">
        <div class="fl-card">

            <!-- Profile -->
            <header class="fl-profile">
                <div class="fl-avatar">
                    <?php if ($profileAvatar): ?>
                        <img src="<?= $e($profileAvatar) ?>" alt="<?= $e($profileName) ?>">
                    <?php else: ?>
                        <span class="fl-initials"><?= $e($initials) ?></span>
                    <?php endif; ?>
                </div>
                <h1 class="fl-name"><?= $e($profileName) ?></h1>
                <?php if ($profileBio): ?>
                <p class="fl-bio"><?= $e($profileBio) ?></p>
                <?php endif; ?>
            </header>

            <!-- Links -->
            <nav class="fl-links" aria-label="<?= $e(yourls__('Links', 'frontend-links')) ?>">
                <?php foreach ($sections as $section):
                    $sectionLinks = $linksBySection[$section['id']] ?? [];
                    if (empty($sectionLinks)) continue;
                ?>
                <div class="fl-section">
                    <h2 class="fl-section-title"><?= $e($section['title']) ?></h2>
                    <?php foreach ($sectionLinks as $link): ?>
                    <a href="<?= $e(fl_strip_base_path($link['url'])) ?>"
                       target="_blank"
                       rel="noopener"
                       class="fl-link">
                        <span class="fl-link-icon"><?= fl_get_icon($link['icon']) ?></span>
                        <span class="fl-link-label"><?= $e($link['label']) ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
            </nav>

            <!-- Footer -->
            <footer class="fl-footer">
                <span>&copy; <?= date('Y') ?> <?= $e($profileName) ?></span>
                <a href="<?= $e($adminPageUrl) ?>" class="fl-admin-link" title="<?= $e(yourls__('Administration', 'frontend-links')) ?>">
                    <?= fl_get_icon('settings', 12) ?>
                    <?php yourls_e('Admin', 'frontend-links'); ?>
                </a>
            </footer>
        </div>
    </main>
</body>
</html>
