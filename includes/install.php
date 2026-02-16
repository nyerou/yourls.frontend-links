<?php
/**
 * Frontend Links - Database Installation
 * ========================================
 *
 * Creates the plugin's MySQL tables on first activation.
 * All tables use InnoDB + utf8mb4 and are prefixed with FL_TABLE_PREFIX.
 *
 * Tables created:
 *   frontend_settings  — Key/value plugin settings (profile name, bio, etc.)
 *   frontend_sections  — Link sections (groups) with sort order
 *   frontend_links     — Individual links (URL, label, icon, section FK)
 *   frontend_icons     — Custom icons (SVG code or uploaded image filename)
 *
 * Default data is inserted on first install (sample sections + links).
 * Subsequent activations are safe (CREATE TABLE IF NOT EXISTS).
 *
 * @see plugin.php  fl_on_activate() triggers this on activation
 *
 * @package FrontendLinks
 */

if (!defined('YOURLS_ABSPATH')) die();

function fl_install_tables() {
    $db = yourls_get_db();
    $prefix = FL_TABLE_PREFIX;

    // Settings table
    $db->query("CREATE TABLE IF NOT EXISTS `{$prefix}settings` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `setting_key` VARCHAR(50) NOT NULL UNIQUE,
        `setting_value` TEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Sections table
    $db->query("CREATE TABLE IF NOT EXISTS `{$prefix}sections` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `section_key` VARCHAR(50) NOT NULL UNIQUE,
        `title` VARCHAR(100) NOT NULL,
        `sort_order` INT DEFAULT 0,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Links table
    $db->query("CREATE TABLE IF NOT EXISTS `{$prefix}links` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `section_id` INT NOT NULL,
        `label` VARCHAR(100) NOT NULL,
        `url` VARCHAR(500) NOT NULL,
        `icon` VARCHAR(50) DEFAULT 'globe',
        `sort_order` INT DEFAULT 0,
        `is_active` TINYINT(1) DEFAULT 1,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`section_id`) REFERENCES `{$prefix}sections`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Custom icons table
    $db->query("CREATE TABLE IF NOT EXISTS `{$prefix}icons` (
        `id` INT PRIMARY KEY AUTO_INCREMENT,
        `name` VARCHAR(50) NOT NULL UNIQUE,
        `type` VARCHAR(10) NOT NULL DEFAULT 'svg',
        `content` TEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Insert default data if tables are empty
    fl_insert_defaults();

    // Initialize options
    if (yourls_get_option('fl_display_mode') === false) {
        yourls_add_option('fl_display_mode', 'manual');
    }
    if (yourls_get_option('fl_shorturl_include_path') === false) {
        yourls_add_option('fl_shorturl_include_path', '0');
    }
}

function fl_insert_defaults() {
    $db = yourls_get_db();
    $prefix = FL_TABLE_PREFIX;

    // Check if settings already exist
    $stmt = $db->query("SELECT COUNT(*) FROM `{$prefix}settings`");
    if ($stmt->fetchColumn() > 0) return;

    // Default settings
    $db->query("INSERT INTO `{$prefix}settings` (`setting_key`, `setting_value`) VALUES
        ('profile_name', 'My Profile'),
        ('profile_bio', 'My bio'),
        ('profile_avatar', ''),
        ('meta_title', 'My Profile - Links'),
        ('meta_description', 'Find all my links and social networks in one place.')");

    // Default sections
    $db->query("INSERT INTO `{$prefix}sections` (`section_key`, `title`, `sort_order`) VALUES
        ('social', 'Social networks', 1),
        ('portfolio', 'Portfolio', 2),
        ('content', 'Content', 3)");

    // Default links
    $db->query("INSERT INTO `{$prefix}links` (`section_id`, `label`, `url`, `icon`, `sort_order`) VALUES
        (1, 'Instagram', 'https://instagram.com/', 'instagram', 1),
        (1, 'TikTok', 'https://tiktok.com/', 'music', 2),
        (1, 'YouTube', 'https://youtube.com/', 'youtube', 3),
        (1, 'Twitter / X', 'https://twitter.com/', 'twitter', 4),
        (2, 'My Website', 'https://example.com/', 'globe', 1),
        (2, 'My Projects', 'https://github.com/', 'folder', 2),
        (3, 'Blog', 'https://blog.example.com/', 'book', 1),
        (3, 'Podcast', 'https://podcast.example.com/', 'mic', 2),
        (3, 'YouTube Channel', 'https://youtube.com/', 'tv', 3)");
}
