<?php
/**
 * Frontend Links - Mini redirect page
 *
 * Branded interstitial page served before redirecting short URLs.
 * Edit this file to customize the design, delay, or metadata.
 *
 * Available variables (set by fl_serve_redirect_page()):
 *   $keyword    - YOURLS keyword (e.g. "vrc")
 *   $url        - Destination long URL
 *   $linkTitle  - Title of the short URL from YOURLS DB
 *   $shortUrl   - Full short URL without subdirectory (e.g. "https://example.com/vrc")
 *   $authorName - Profile name from Frontend Links settings
 *   $siteImage  - Profile avatar URL (may be empty)
 *   $ogTitle    - Formatted title: "Author → Link Title"
 *   $cleanShort - Short URL without protocol (e.g. "example.com/vrc")
 *   $cleanDest  - Destination URL without protocol/query (e.g. "github.com/user")
 *   $e          - Shorthand for fl_escape() function
 *
 * Redirect delay: change the meta refresh "content" value and the
 * setTimeout delay (in ms) + CSS animation duration to match.
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

    <!-- Open Graph -->
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

    <!-- Schema.org -->
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

    <!-- Auto redirect -->
    <meta http-equiv="refresh" content="1;url=<?= $e($url) ?>">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, -apple-system, sans-serif;
            background: #141621;
            color: #f2f2f2;
        }

        .card {
            max-width: 420px;
            width: 90%;
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 1rem;
            padding: 2.5rem 2rem;
            text-align: center;
            box-shadow: 0 0 80px -20px rgba(124, 107, 196, 0.3);
            animation: card-in 0.4s ease-out;
        }

        @keyframes card-in {
            from { opacity: 0; transform: translateY(12px) scale(0.97); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .author {
            font-size: 0.7rem;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.35);
            text-transform: uppercase;
            letter-spacing: 0.12em;
            margin-bottom: 0.75rem;
        }

        .title {
            font-size: 1.15rem;
            font-weight: 600;
            margin-bottom: 1.25rem;
        }

        .dest {
            font-size: 0.72rem;
            color: rgba(255, 255, 255, 0.4);
            margin-bottom: 1.5rem;
        }

        .dest .arrow {
            color: rgba(255, 255, 255, 0.25);
        }

        .dest a {
            color: rgba(255, 255, 255, 0.55);
            text-decoration: none;
            word-break: break-all;
        }

        .dest a:hover {
            color: #f2f2f2;
            text-decoration: underline;
        }

        .dots {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-top: 0.25rem;
        }

        .dots span {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #7c6bc4;
            animation: dot-pulse 1s ease-in-out infinite;
        }

        .dots span:nth-child(2) { animation-delay: 0.15s; }
        .dots span:nth-child(3) { animation-delay: 0.3s; }

        @keyframes dot-pulse {
            0%, 100% { opacity: 0.2; transform: scale(0.8); }
            50%      { opacity: 1;   transform: scale(1.2); }
        }
    </style>
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
    <script>
        setTimeout(function () {
            window.location.replace(<?= json_encode($url) ?>);
        }, 1000);
    </script>
</body>
</html>
