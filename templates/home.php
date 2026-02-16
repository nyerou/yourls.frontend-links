<?php
/**
 * Frontend Links - Homepage Template
 * ====================================
 *
 * Public link-in-bio page. Edit this file to customize the HTML/design.
 * Do NOT modify includes/render.php — it prepares the variables below.
 *
 * Assets:
 *   assets/css/my.css  — Stylesheet (compiled Tailwind, edit with care)
 *   assets/js/app.js   — Particle system JavaScript
 *
 * Available variables (set by fl_render_page() in render.php):
 *   $profileName     - Display name
 *   $profileBio      - Short bio text
 *   $profileAvatar   - Avatar URL (may be empty)
 *   $initials        - Initials fallback for avatar
 *   $metaTitle       - SEO title
 *   $metaDescription - SEO description
 *   $siteUrl         - Root URL without subdirectory
 *   $assetsUrl       - Plugin assets URL (css/, js/)
 *   $adminPageUrl    - YOURLS admin URL
 *   $htmlLang        - Language code (e.g. "fr")
 *   $sections        - Array of active sections
 *   $linksBySection  - Links grouped by section ID
 *   $e               - Shorthand for fl_escape()
 *
 * @see includes/render.php   Logic that prepares variables
 * @see assets/css/my.css     Stylesheet
 * @see assets/js/app.js      Particle system
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

    <!-- SEO Meta Tags -->
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
    <link rel="stylesheet" href="<?= $e($assetsUrl) ?>/css/all.min.css">
    <link rel="stylesheet" href="<?= $e($assetsUrl) ?>/css/my.css">

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
<body class="bg-background text-foreground">

    <!-- Background gradient -->
    <div class="fixed inset-0 -z-20" id="bg-gradient"></div>

    <!-- Particles container -->
    <div class="fixed inset-0 -z-10 overflow-hidden pointer-events-none" id="particles"></div>

    <!-- Main content -->
    <main class="relative min-h-screen flex items-center justify-center px-4 py-16 overflow-hidden">

        <!-- Glass card -->
        <article class="w-full max-w-md backdrop-blur-2xl bg-white/[0.04] border border-white/[0.08] rounded-2xl p-8 shadow-[0_0_80px_-20px_hsl(250_40%_30%/0.3)]">

            <!-- Profile -->
            <header class="flex flex-col items-center mb-10 opacity-0 animate-fade-in">
                <!-- Avatar -->
                <div class="h-20 w-20 mb-4 ring-1 ring-white/10 rounded-full bg-white/[0.06] backdrop-blur-sm flex items-center justify-center overflow-hidden">
                    <?php if ($profileAvatar): ?>
                        <img src="<?= $e($profileAvatar) ?>" alt="<?= $e($profileName) ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <span class="text-foreground text-lg font-semibold"><?= $e($initials) ?></span>
                    <?php endif; ?>
                </div>
                <h1 class="text-xl font-semibold tracking-tight text-foreground"><?= $e($profileName) ?></h1>
                <p class="text-xs text-muted-foreground mt-1.5 tracking-wide"><?= $e($profileBio) ?></p>
            </header>

            <!-- Links -->
            <nav aria-label="<?= $e(yourls__('Links', 'frontend-links')) ?>">
                <?php
                $globalIndex = 0;
                foreach ($sections as $section):
                    $sectionLinks = $linksBySection[$section['id']] ?? [];
                    if (empty($sectionLinks)) continue;
                ?>
                <section class="mb-7 last:mb-0">
                    <h2 class="section-title"><?= $e($section['title']) ?></h2>
                    <div class="flex flex-col gap-2.5">
                        <?php foreach ($sectionLinks as $link):
                            $delay = $globalIndex * 60;
                            $globalIndex++;
                        ?>
                        <a href="<?= $e(fl_strip_base_path($link['url'])) ?>"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="link-item group flex items-center gap-3.5 px-5 py-3 rounded-xl bg-white/[0.03] border border-white/[0.06] text-foreground opacity-0"
                           style="animation: fade-in 0.5s ease-out <?= $delay ?>ms forwards;">
                            <span class="link-icon"><?= fl_get_icon($link['icon']) ?></span>
                            <span class="link-label"><?= $e($link['label']) ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endforeach; ?>
            </nav>

            <!-- Footer -->
            <footer class="text-center text-[10px] text-muted-foreground/50 mt-10 tracking-wider flex items-center justify-center gap-3">
                <span>&copy; <?= date('Y') ?> <?= $e($profileName) ?></span>
                <a href="<?= $e($adminPageUrl) ?>"
                   class="inline-flex items-center gap-1 px-2 py-1 rounded bg-white/[0.05] hover:bg-white/[0.1] transition-colors"
                   title="<?= $e(yourls__('Administration', 'frontend-links')) ?>">
                    <?= fl_get_icon('settings', 12) ?>
                    <span><?php yourls_e('Admin', 'frontend-links'); ?></span>
                </a>
            </footer>
        </article>
    </main>

    <script src="<?= $e($assetsUrl) ?>/js/app.js"></script>
</body>
</html>
