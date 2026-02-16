<?php
/**
 * Frontend Links - Homepage Rendering Logic
 * ===========================================
 *
 * Prepares all template variables then loads templates/home.php.
 * This file contains ONLY logic — no HTML output.
 *
 * To customize the homepage design, edit templates/home.php instead.
 * To customize the homepage styles, edit assets/css/my.css.
 *
 * Variables prepared for the template:
 *   $profileName, $profileBio, $profileAvatar, $initials
 *   $metaTitle, $metaDescription, $siteUrl, $assetsUrl
 *   $adminPageUrl, $htmlLang, $sections, $linksBySection, $e
 *
 * @see templates/home.php  The HTML template loaded by this file
 * @see assets/css/my.css   Homepage stylesheet (compiled Tailwind)
 * @see assets/js/app.js    Homepage JavaScript (particle system)
 *
 * @package FrontendLinks
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

    // Template variables
    $profileName    = $settings['profile_name'] ?? yourls__('My Profile', 'frontend-links');
    $profileBio     = $settings['profile_bio'] ?? '';
    $profileAvatar  = $settings['profile_avatar'] ?? '';
    $metaTitle      = $settings['meta_title'] ?? $profileName . yourls__(' - Links', 'frontend-links');
    $metaDescription = $settings['meta_description'] ?? $profileBio;
    $siteUrl        = fl_get_root_url();
    $assetsUrl      = yourls_plugin_url(FL_PLUGIN_DIR) . '/assets';
    $adminPageUrl   = yourls_admin_url();
    $initials       = implode('', array_map(fn($w) => mb_substr($w, 0, 1), explode(' ', $profileName)));
    $htmlLang       = substr(yourls_get_locale(), 0, 2);
    $e              = 'fl_escape';

    require FL_PLUGIN_DIR . '/templates/home.php';
}
