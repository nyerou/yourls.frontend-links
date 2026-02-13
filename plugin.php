<?php
/*
Plugin Name: Frontend Links
Plugin URI: https://github.com/nyerou/yourls.frontend-links
Description: Customizable link page with section, link, and profile management from the YOURLS admin.
Version: 1.1
Author: Nyerou
Author URI: https://nyerou.link
*/

// No direct access
if (!defined('YOURLS_ABSPATH')) die();

// Plugin constants
define('FL_PLUGIN_DIR', __DIR__);
define('FL_PLUGIN_SLUG', basename(__DIR__));
define('FL_TABLE_PREFIX', 'frontend_');

// Uploads directory inside the plugin itself (user/plugins/frontend-links/uploads/)
define('FL_UPLOADS_DIR', FL_PLUGIN_DIR . '/uploads');
define('FL_UPLOADS_URL', yourls_plugin_url(FL_PLUGIN_DIR) . '/uploads');

// Subdirectory for custom icons (uploaded images)
define('FL_ICONS_DIR', FL_UPLOADS_DIR . '/icons');
define('FL_ICONS_URL', FL_UPLOADS_URL . '/icons');

// AJAX endpoint (dedicated file, outside admin template)
define('FL_AJAX_URL', yourls_plugin_url(FL_PLUGIN_DIR) . '/ajax.php');

// Load dependencies
require_once FL_PLUGIN_DIR . '/includes/functions.php';
require_once FL_PLUGIN_DIR . '/includes/icons.php';
require_once FL_PLUGIN_DIR . '/includes/render.php';

// ─── Load textdomain for i18n ──────────────────────────────
yourls_add_action('plugins_loaded', 'fl_load_textdomain');
function fl_load_textdomain() {
    yourls_load_custom_textdomain('frontend-links', FL_PLUGIN_DIR . '/languages');
}

// ─── Register admin page ───────────────────────────────────
yourls_add_action('plugins_loaded', 'fl_register_admin_page');
function fl_register_admin_page() {
    yourls_register_plugin_page('frontend_admin', 'Frontend Links', 'fl_admin_page');
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

// ─── Display admin page ────────────────────────────────────
function fl_admin_page() {
    require_once FL_PLUGIN_DIR . '/includes/admin-page.php';
}
