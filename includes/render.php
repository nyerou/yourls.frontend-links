<?php
/**
 * Frontend Links - Public page rendering
 * Function fl_render_page() can be included from any PHP file.
 *
 * Usage from an external file (exact path shown in the plugin admin page):
 *   <?php
 *   require_once __DIR__ . '/includes/load-yourls.php'; // adjust based on YOURLS location
 *   fl_render_page();
 *
 * Automatic usage: enable "auto" mode in plugin options.
 */

if (!defined('YOURLS_ABSPATH')) die();

function fl_render_page(): void {
    // Check that tables exist
    if (!fl_tables_exist()) {
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Frontend Links</title></head>';
        echo '<body style="font-family:system-ui;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#141621;color:#f2f2f2;">';
        echo '<div style="text-align:center;"><h1>' . yourls__('Installation required', 'frontend-links') . '</h1>';
        echo '<p>' . yourls__('The Frontend Links plugin is not yet installed.', 'frontend-links') . '</p>';
        echo '<p><a href="' . fl_escape(yourls_admin_url('plugins.php?page=frontend_admin')) . '" style="color:#7c6bc4;">' . yourls__('Go to admin to install', 'frontend-links') . '</a></p>';
        echo '</div></body></html>';
        return;
    }

    // Retrieve data
    $settings = fl_get_settings();
    $sections = fl_get_sections(true);
    $links = fl_get_links(true);

    // Group links by section
    $linksBySection = [];
    foreach ($links as $link) {
        $linksBySection[$link['section_id']][] = $link;
    }

    // Profile data
    $profileName = $settings['profile_name'] ?? yourls__('My Profile', 'frontend-links');
    $profileBio = $settings['profile_bio'] ?? '';
    $profileAvatar = $settings['profile_avatar'] ?? '';
    $metaTitle = $settings['meta_title'] ?? $profileName . yourls__(' - Links', 'frontend-links');
    $metaDescription = $settings['meta_description'] ?? $profileBio;

    // Site URL (root domain without YOURLS subdirectory)
    $siteUrl = fl_get_root_url();

    // Plugin assets URL (CSS/JS/images)
    $assetsUrl = yourls_plugin_url(FL_PLUGIN_DIR) . '/assets';

    // Admin URL
    $adminPageUrl = yourls_admin_url();

    // Initials for avatar fallback
    $initials = implode('', array_map(fn($w) => mb_substr($w, 0, 1), explode(' ', $profileName)));

    // Dynamic locale for html lang attribute
    $htmlLang = substr(yourls_get_locale(), 0, 2);

    // Shorthand
    $e = 'fl_escape';
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
        "name": "<?= $e($profileName) ?>",
        "description": "<?= $e($profileBio) ?>",
        "url": "<?= $e($siteUrl) ?>"
        <?php if ($profileAvatar): ?>,
        "image": "<?= $e($profileAvatar) ?>"
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
<?php
}
