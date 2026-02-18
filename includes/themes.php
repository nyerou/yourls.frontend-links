<?php
/**
 * Frontend Links - Theme System
 * ==============================
 *
 * Theme resolution and discovery functions.
 * Themes live in the themes/ directory, each containing:
 *   theme.json    - Metadata (name, author, version, description)
 *   templates/    - PHP template files (home.php, redirect.php, 404.php)
 *   assets/       - CSS, JS, images used by the theme
 *
 * @package FrontendLinks
 */

if (!defined('YOURLS_ABSPATH')) die();

/**
 * Get the active theme slug.
 * Validates that the directory exists, falls back to 'default'.
 */
function fl_get_active_theme(): string {
    static $cached = null;
    if ($cached !== null) return $cached;

    $theme = yourls_get_option('fl_active_theme', 'default');

    // yourls_get_option may return false/null if the option doesn't exist
    if (!empty($theme) && is_string($theme) && is_dir(FL_THEMES_DIR . '/' . $theme)) {
        $cached = $theme;
    } else {
        $cached = 'default';
    }

    return $cached;
}

/**
 * Get the filesystem path for a theme directory.
 */
function fl_get_theme_dir(string $theme = ''): string {
    if ($theme === '') $theme = fl_get_active_theme();
    return FL_THEMES_DIR . '/' . $theme;
}

/**
 * Get the web-accessible URL for a theme's assets/ directory.
 */
function fl_get_theme_assets_url(string $theme = ''): string {
    if ($theme === '') $theme = fl_get_active_theme();
    return yourls_plugin_url(FL_PLUGIN_DIR) . '/themes/' . $theme . '/assets';
}

/**
 * Resolve a template file path with fallback chain: active theme -> default.
 *
 * @param string $name  Template filename (e.g. 'home.php')
 * @param string $theme Theme slug (empty = active theme)
 * @return string Filesystem path to the template
 */
function fl_get_theme_template(string $name, string $theme = ''): string {
    if ($theme === '') $theme = fl_get_active_theme();

    $path = FL_THEMES_DIR . '/' . $theme . '/templates/' . $name;
    if (file_exists($path)) {
        return $path;
    }

    // Fallback to default theme
    $fallback = FL_THEMES_DIR . '/default/templates/' . $name;
    if (file_exists($fallback)) {
        return $fallback;
    }

    // Should not happen, but return the expected path for error reporting
    return $path;
}

/**
 * Scan themes/ directory for valid themes (must contain theme.json).
 *
 * @return array<string, array> Keyed by slug, value is decoded theme.json
 */
function fl_get_available_themes(): array {
    $themes = [];
    $dir = FL_THEMES_DIR;

    if (!is_dir($dir)) return $themes;

    foreach (scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $jsonPath = $dir . '/' . $entry . '/theme.json';
        if (is_file($jsonPath)) {
            $meta = json_decode(file_get_contents($jsonPath), true);
            if (is_array($meta)) {
                $themes[$entry] = $meta;
            }
        }
    }

    return $themes;
}
