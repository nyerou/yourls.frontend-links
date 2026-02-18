<?php
/*
Plugin Name: Frontend Links
Plugin URI: https://github.com/nyerou/yourls.frontend-links
Description: Customizable link page with section, link, and profile management from the YOURLS admin.
Version: 1.3
Author: Nyerou
Author URI: https://nyerou.link
*/

/**
 * Frontend Links - Plugin Entry Point
 * =====================================
 *
 * This is the main bootstrap file loaded by YOURLS.
 * It defines constants, loads dependencies, and registers all hooks.
 *
 * Hooks registered:
 *   - plugins_loaded     → Load textdomain + register admin page + htaccess update
 *   - activated_*        → Create tables on activation
 *   - loader_failed      → Serve homepage or 404 in auto mode
 *   - redirect_shorturl  → Intercept redirects → mini page with OG metadata
 *   - shorturl (filter)  → Strip subdirectory from displayed short URLs
 *   - yourls_link (filter) → Strip subdirectory from generated links
 *   - html_head          → Inject stats link subdirectory fix script
 *
 * File structure:
 *   plugin.php              ← You are here (entry point)
 *   ajax.php                ← Dedicated AJAX endpoint for admin CRUD
 *   includes/
 *     functions.php         ← Core logic (CRUD, URL helpers, file management)
 *     icons.php             ← Font Awesome + custom icon system
 *     install.php           ← Database table creation
 *     render.php            ← Homepage rendering logic
 *     themes.php            ← Theme resolution and discovery
 *   templates/
 *     admin.php             ← Admin panel interface
 *   assets/
 *     css/admin.css         ← Admin panel styles
 *     css/all.min.css       ← Font Awesome (vendor)
 *     js/admin.js           ← Admin panel logic (CSP-compliant)
 *     js/redirect.js        ← Redirect delay script (shared)
 *     js/stats-rewrite.js   ← Admin stats fix
 *   themes/
 *     default/              ← Minimal default theme
 *     nyerou-original/      ← Original dark theme with particles
 *
 * @package FrontendLinks
 * @author  Nyerou
 * @link    https://github.com/nyerou/yourls.frontend-links
 */

// No direct access
if (!defined('YOURLS_ABSPATH')) die();

// ─── Plugin constants ───────────────────────────────────────
define('FL_VERSION',    '1.3');
define('FL_PLUGIN_DIR', __DIR__);
define('FL_PLUGIN_SLUG', basename(__DIR__));
define('FL_TABLE_PREFIX', 'frontend_');

// Themes directory (user/plugins/frontend-links/themes/)
define('FL_THEMES_DIR', FL_PLUGIN_DIR . '/themes');

// Uploads directory (user/plugins/frontend-links/uploads/)
define('FL_UPLOADS_DIR', FL_PLUGIN_DIR . '/uploads');
define('FL_UPLOADS_URL', yourls_plugin_url(FL_PLUGIN_DIR) . '/uploads');

// Custom icons subdirectory (uploaded images)
define('FL_ICONS_DIR', FL_UPLOADS_DIR . '/icons');
define('FL_ICONS_URL', FL_UPLOADS_URL . '/icons');

// AJAX endpoint (standalone file, not inside admin template)
define('FL_AJAX_URL', yourls_plugin_url(FL_PLUGIN_DIR) . '/ajax.php');

// ─── Load dependencies ──────────────────────────────────────
require_once FL_PLUGIN_DIR . '/includes/functions.php';
require_once FL_PLUGIN_DIR . '/includes/icons.php';
require_once FL_PLUGIN_DIR . '/includes/themes.php';
require_once FL_PLUGIN_DIR . '/includes/render.php';

// ─── Load textdomain for i18n ──────────────────────────────
yourls_add_action('plugins_loaded', 'fl_load_textdomain');
function fl_load_textdomain() {
    yourls_load_custom_textdomain('frontend-links', FL_PLUGIN_DIR . '/languages');
}

// ─── Register admin page ───────────────────────────────────
yourls_add_action('plugins_loaded', 'fl_register_admin_page');
function fl_register_admin_page() {
    yourls_register_plugin_page('frontend_admin', 'Frontend Administration', 'fl_admin_page');
}

// ─── Create tables on activation ───────────────────────────
yourls_add_action('activated_frontend-links/plugin.php', 'fl_on_activate');
function fl_on_activate() {
    require_once FL_PLUGIN_DIR . '/includes/install.php';
    fl_install_tables();

    // Create uploads directories if they don't exist
    if (!is_dir(FL_UPLOADS_DIR)) {
        mkdir(FL_UPLOADS_DIR, 0755, true);
    }
    if (!is_dir(FL_ICONS_DIR)) {
        mkdir(FL_ICONS_DIR, 0755, true);
    }
    fl_write_uploads_htaccess();

    // If auto mode was previously set, recreate the index.php files
    if (yourls_get_option('fl_display_mode') === 'auto') {
        fl_create_homepage_file();
    }
}

// ─── Auto hook "/" if auto mode (fallback) ─────────────────
// The hook acts as a safety net if the created index.php is not reached
if (yourls_get_option('fl_display_mode') === 'auto') {
    yourls_add_action('loader_failed', 'fl_serve_homepage');
}

function fl_serve_homepage($args) {
    $request = isset($args[0]) ? trim($args[0], '/') : '';
    if ($request === '') {
        fl_render_page();
        die();
    }
    // Short URL not found → branded 404
    fl_serve_404_page($request);
}

// ─── Intercept short URL redirects → mini redirect page ─────
// Uses the redirect_shorturl action which fires ONLY for short URL
// redirects (not admin redirects). Serves a branded HTML page with
// OG metadata before redirecting.
if (yourls_get_option('fl_display_mode') === 'auto') {
    yourls_add_action('redirect_shorturl', 'fl_intercept_shorturl_redirect');
}

function fl_intercept_shorturl_redirect($args) {
    $url = $args[0] ?? '';
    $keyword = $args[1] ?? '';
    if (empty($url) || empty($keyword)) return;

    // Don't intercept admin context
    if (defined('YOURLS_ADMIN')) return;

    // Let YOURLS do its native redirect if branded redirect page is disabled
    if (yourls_get_option('fl_disable_redirect_page') === '1') return;

    fl_serve_redirect_page($keyword, $url);
    exit;
}

// ─── Rewrite short URLs in the entire YOURLS admin ──────────
// Strips the subdirectory from short URLs displayed everywhere
// (main admin table, API responses, "Your short URL" box, etc.)
yourls_add_filter('shorturl', 'fl_filter_shorturl');
function fl_filter_shorturl($shorturl) {
    return fl_strip_base_path($shorturl);
}

// Also filter the base YOURLS link generation
yourls_add_filter('yourls_link', 'fl_filter_shorturl');

// ─── Fix stats links subdirectory ────────────────────────────
// The shorturl filter above strips the YOURLS subdirectory from displayed
// short URLs. YOURLS admin JS builds stats links by appending "+" to the
// displayed short URL, producing e.g. "https://example.com/keyword+" instead
// of "https://example.com/yourls/keyword+". This script adds the subdirectory
// back to stats links so they reach YOURLS correctly.
yourls_add_action('html_head', 'fl_inject_stats_fix_js');
function fl_inject_stats_fix_js() {
    $basePath = fl_get_yourls_base_path();
    if ($basePath === '') return; // No subdirectory, nothing to fix

    $jsUrl   = yourls_plugin_url(FL_PLUGIN_DIR) . '/assets/js/stats-rewrite.js';
    $rootUrl = fl_get_root_url();

    echo '<script src="' . fl_escape($jsUrl) . '"'
        . ' data-base-path="' . fl_escape($basePath) . '"'
        . ' data-root-url="' . fl_escape($rootUrl) . '"'
        . ' defer></script>' . "\n";
}

// ─── Auto-update .htaccess when rules change ────────────────
// Regenerates the root .htaccess if the plugin rules version has changed.
// This ensures rewrite rules stay up to date without requiring
// the user to manually re-save the display mode.
yourls_add_action('plugins_loaded', 'fl_maybe_update_htaccess');
function fl_maybe_update_htaccess() {
    if (yourls_get_option('fl_display_mode') !== 'auto') return;

    $currentVersion = '4'; // Bump this when .htaccess rules change
    if (yourls_get_option('fl_htaccess_version', '0') !== $currentVersion) {
        fl_create_homepage_file();
        yourls_update_option('fl_htaccess_version', $currentVersion);
    }
}

// ─── Display admin page ────────────────────────────────────
function fl_admin_page() {
    require_once FL_PLUGIN_DIR . '/templates/admin.php';
}
