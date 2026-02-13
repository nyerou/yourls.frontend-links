<?php
/**
 * Frontend Links - 404 error page (System in development)
 *
 * Displayed when a short URL keyword is not found.
 * Edit this file to customize the design.
 *
 * Available variables (set by fl_serve_404_page()):
 *   $request    - The requested path that was not found
 *   $homeUrl    - Root URL to link back to homepage
 *   $authorName - Profile name from Frontend Links settings
 *   $e          - Shorthand for fl_escape() function
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

        .code {
            font-size: 3.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #7c6bc4, #a78bfa);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            margin-bottom: 0.75rem;
        }

        .message {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 0.5rem;
        }

        .path {
            font-size: 0.72rem;
            color: rgba(255, 255, 255, 0.25);
            font-family: monospace;
            margin-bottom: 2rem;
            word-break: break-all;
        }

        .home-btn {
            display: inline-block;
            padding: 0.6rem 1.8rem;
            border-radius: 0.5rem;
            background: rgba(124, 107, 196, 0.15);
            border: 1px solid rgba(124, 107, 196, 0.3);
            color: #a78bfa;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: background 0.2s, border-color 0.2s;
        }

        .home-btn:hover {
            background: rgba(124, 107, 196, 0.25);
            border-color: rgba(124, 107, 196, 0.5);
        }
    </style>
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
