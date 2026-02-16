<?php
/**
 * Frontend Links - Icon System
 * ==============================
 *
 * Provides a unified icon API for the plugin. Icons come from two sources:
 *
 *   1. Built-in Font Awesome icons (brands + solid)
 *      Defined statically in fl_get_builtin_icons().
 *      Rendered as <i class="fa-..."> tags.
 *
 *   2. Custom icons from the database
 *      Added by users via the admin panel.
 *      Two types: SVG code (inline) or uploaded images.
 *
 * Main functions:
 *   fl_get_builtin_icons()     — Static FA icon definitions
 *   fl_get_available_icons()   — Merged list (FA + custom) for <select> dropdowns
 *   fl_get_icon($name, $size)  — Returns the HTML for any icon by name
 *
 * On the public page, custom image icons get CSS filter coloring
 * via the .fl-icon-img class (see assets/css/my.css).
 *
 * @see includes/functions.php  fl_get_custom_icons(), fl_create_custom_icon()
 * @see templates/admin.php     Icon management UI
 *
 * @package FrontendLinks
 */

if (!defined('YOURLS_ABSPATH')) die();

/**
 * Returns the built-in Font Awesome icons array (static cache)
 * Encapsulated in a function to avoid scope issues
 * when YOURLS loads plugins inside a function.
 */
function fl_get_builtin_icons(): array {
    static $icons = null;
    if ($icons !== null) return $icons;

    $icons = [
        // ─ Brands ──────────────────────────
        'instagram' => ['type' => 'fa', 'class' => 'fa-brands fa-instagram'],
        'youtube'   => ['type' => 'fa', 'class' => 'fa-brands fa-youtube'],
        'twitter'   => ['type' => 'fa', 'class' => 'fa-brands fa-x-twitter'],
        'facebook'  => ['type' => 'fa', 'class' => 'fa-brands fa-facebook'],
        'linkedin'  => ['type' => 'fa', 'class' => 'fa-brands fa-linkedin'],
        'github'    => ['type' => 'fa', 'class' => 'fa-brands fa-github'],
        'discord'   => ['type' => 'fa', 'class' => 'fa-brands fa-discord'],
        'twitch'    => ['type' => 'fa', 'class' => 'fa-brands fa-twitch'],
        'spotify'   => ['type' => 'fa', 'class' => 'fa-brands fa-spotify'],
        'steam'     => ['type' => 'fa', 'class' => 'fa-brands fa-steam'],
        'tiktok'    => ['type' => 'fa', 'class' => 'fa-brands fa-tiktok'],
        'snapchat'  => ['type' => 'fa', 'class' => 'fa-brands fa-snapchat'],
        'pinterest' => ['type' => 'fa', 'class' => 'fa-brands fa-pinterest'],
        'reddit'    => ['type' => 'fa', 'class' => 'fa-brands fa-reddit'],
        'whatsapp'  => ['type' => 'fa', 'class' => 'fa-brands fa-whatsapp'],
        'telegram'  => ['type' => 'fa', 'class' => 'fa-brands fa-telegram'],
        'paypal'    => ['type' => 'fa', 'class' => 'fa-brands fa-paypal'],
        'patreon'   => ['type' => 'fa', 'class' => 'fa-brands fa-patreon'],
        'soundcloud'=> ['type' => 'fa', 'class' => 'fa-brands fa-soundcloud'],
        'bandcamp'  => ['type' => 'fa', 'class' => 'fa-brands fa-bandcamp'],
        'itchio'    => ['type' => 'fa', 'class' => 'fa-brands fa-itch-io'],
        'mastodon'  => ['type' => 'fa', 'class' => 'fa-brands fa-mastodon'],
        'bluesky'   => ['type' => 'fa', 'class' => 'fa-brands fa-bluesky'],
        'threads'   => ['type' => 'fa', 'class' => 'fa-brands fa-threads'],

        // ─ Solid (generic) ─────────────────
        'globe'     => ['type' => 'fa', 'class' => 'fa-solid fa-globe'],
        'music'     => ['type' => 'fa', 'class' => 'fa-solid fa-music'],
        'folder'    => ['type' => 'fa', 'class' => 'fa-solid fa-folder'],
        'book'      => ['type' => 'fa', 'class' => 'fa-solid fa-book'],
        'mic'       => ['type' => 'fa', 'class' => 'fa-solid fa-microphone'],
        'tv'        => ['type' => 'fa', 'class' => 'fa-solid fa-tv'],
        'mail'      => ['type' => 'fa', 'class' => 'fa-solid fa-envelope'],
        'phone'     => ['type' => 'fa', 'class' => 'fa-solid fa-phone'],
        'mappin'    => ['type' => 'fa', 'class' => 'fa-solid fa-location-dot'],
        'link'      => ['type' => 'fa', 'class' => 'fa-solid fa-link'],
        'settings'  => ['type' => 'fa', 'class' => 'fa-solid fa-gear'],
        'user'      => ['type' => 'fa', 'class' => 'fa-solid fa-user'],
        'plus'      => ['type' => 'fa', 'class' => 'fa-solid fa-plus'],
        'edit'      => ['type' => 'fa', 'class' => 'fa-solid fa-pen'],
        'trash'     => ['type' => 'fa', 'class' => 'fa-solid fa-trash'],
        'logout'    => ['type' => 'fa', 'class' => 'fa-solid fa-right-from-bracket'],
        'save'      => ['type' => 'fa', 'class' => 'fa-solid fa-floppy-disk'],
        'x'         => ['type' => 'fa', 'class' => 'fa-solid fa-xmark'],
        'heart'     => ['type' => 'fa', 'class' => 'fa-solid fa-heart'],
        'star'      => ['type' => 'fa', 'class' => 'fa-solid fa-star'],
        'camera'    => ['type' => 'fa', 'class' => 'fa-solid fa-camera'],
        'gamepad'   => ['type' => 'fa', 'class' => 'fa-solid fa-gamepad'],
        'code'      => ['type' => 'fa', 'class' => 'fa-solid fa-code'],
        'shop'      => ['type' => 'fa', 'class' => 'fa-solid fa-shop'],
        'gift'      => ['type' => 'fa', 'class' => 'fa-solid fa-gift'],
        'bolt'      => ['type' => 'fa', 'class' => 'fa-solid fa-bolt'],
        'palette'   => ['type' => 'fa', 'class' => 'fa-solid fa-palette'],
        'podcast'   => ['type' => 'fa', 'class' => 'fa-solid fa-podcast'],
    ];

    return $icons;
}

/**
 * List of available icons (name => label)
 * Merges Font Awesome icons + custom icons from DB
 */
function fl_get_available_icons(): array {
    $available = [];

    // Built-in icons (FA)
    foreach (fl_get_builtin_icons() as $name => $data) {
        $label = ucfirst(str_replace(['_', '-'], ' ', $name));
        $available[$name] = $label;
    }

    // Custom icons from DB
    foreach (fl_get_custom_icons() as $icon) {
        $available[$icon['name']] = ucfirst(str_replace(['_', '-'], ' ', $icon['name'])) . ' ✦';
    }

    return $available;
}

/**
 * Returns the HTML for an icon by its name
 * Searches first in Font Awesome icons, then in custom icons (DB)
 *
 * For custom images: the CSS class "fl-icon-img" is applied.
 * On the public page (my.css loaded), a CSS filter colors the image
 * to match the theme. On the admin (no my.css), the image
 * displays as-is.
 */
function fl_get_icon(string $name, int $size = 18): string {
    $builtins = fl_get_builtin_icons();

    // 1. Search in Font Awesome icons
    if (isset($builtins[$name])) {
        $icon = $builtins[$name];

        if ($icon['type'] === 'fa') {
            $class = fl_escape($icon['class']);
            return '<i class="' . $class . '" style="font-size:' . $size . 'px"></i>';
        }
    }

    // 2. Search in custom icons (DB)
    $customs = fl_get_custom_icons_indexed();
    if (isset($customs[$name])) {
        $custom = $customs[$name];

        if ($custom['type'] === 'svg') {
            $svg = $custom['content'];
            $svg = preg_replace('/\bwidth="[^"]*"/', 'width="' . $size . '"', $svg, 1);
            $svg = preg_replace('/\bheight="[^"]*"/', 'height="' . $size . '"', $svg, 1);
            return $svg;
        }
        if ($custom['type'] === 'image') {
            $url = fl_escape(FL_ICONS_URL . '/' . $custom['content']);
            return '<img src="' . $url . '" width="' . $size . '" height="' . $size . '" class="fl-icon-img" draggable="false" alt="' . fl_escape($name) . '">';
        }
    }

    // 3. Fallback
    return '<i class="fa-solid fa-circle-question" style="font-size:' . $size . 'px"></i>';
}
