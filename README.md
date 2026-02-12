# Frontend Links

A [YOURLS](https://yourls.org) plugin that turns your short URL domain into a customizable **link-in-bio page** — managed entirely from the YOURLS admin panel.

![YOURLS](https://img.shields.io/badge/YOURLS-1.9+-blue) ![PHP](https://img.shields.io/badge/PHP-8.0+-purple) ![License](https://img.shields.io/badge/license-MIT-green)

---

## Features

- **Profile** — Name, bio, avatar (upload or external URL), with avatar history (restore previous)
- **Sections & Links** — Organize links into named sections, with drag-friendly sort order
- **Icons** — 50+ built-in [Font Awesome](https://fontawesome.com) icons (brands + solid) + upload your own (SVG or image)
- **SEO** — Custom meta title, description, Open Graph, Twitter Card, and Schema.org structured data
- **Short URL integration** — Enter a YOURLS keyword (e.g. `git`) as a link URL and the domain is added automatically
- **Display modes** — Automatic (serves the page at `/`) or Manual (call `fl_render_page()` from your own file)
- **Dark theme** — Glassmorphism design with animated particle background
- **i18n** — English by default, French translation included. Supports any YOURLS locale.
- **Full AJAX admin** — All operations (add, edit, delete, reorder) work without page reloads

---

## Installation

1. Download or clone this repository into your YOURLS plugins directory:

```
user/plugins/frontend-links/
```

2. Activate the plugin from the YOURLS admin panel (**Manage Plugins**).

3. Go to the **Frontend Links** admin page — the database tables will be created automatically on first visit.

---

## Display Modes

The plugin offers two ways to serve your link page.

### Automatic mode

The plugin creates an `index.php` file at your document root (or above the YOURLS subdirectory) that serves the link page at `/`.

> **How it works:** The generated `index.php` simply loads YOURLS and calls `fl_render_page()`. It contains a marker comment (`/* FRONTEND_LINKS_AUTO_GENERATED */`) so the plugin can safely manage it. Switching back to manual mode deletes this file automatically.

Enable it from the **Options** panel in the admin page.

**Example — YOURLS installed at the root** (`https://example.com`):
```
/                       ← link page (generated index.php)
/admin/                 ← YOURLS admin
/yourls-keyword         ← short URLs work normally
```

**Example — YOURLS installed in a subdirectory** (`https://example.com/yourls`):
```
/                       ← link page (generated index.php)
/yourls/admin/          ← YOURLS admin
/yourls/keyword         ← short URLs
```

> If an `index.php` already exists at the target location and wasn't created by this plugin, the plugin will not overwrite it. You'll need to delete it manually or use manual mode.

### Manual mode

Call `fl_render_page()` from any PHP file that loads YOURLS. This gives you full control over where and how the page is served.

**Example `index.php`:**

```php
<?php
require_once __DIR__ . '/path/to/yourls/includes/load-yourls.php';
fl_render_page();
```

Adjust the `require_once` path based on where your file is relative to YOURLS.

---

## Short URL Links

When adding a link in the admin, if you enter a URL **without a protocol** (e.g. `git` instead of `https://github.com/me`), the plugin automatically prepends your YOURLS domain.

| You enter | Result |
|-----------|--------|
| `git` | `https://example.com/git` |
| `https://github.com` | `https://github.com` (unchanged) |
| `/page` | `/page` (unchanged) |

An option in the admin lets you choose whether to **include the YOURLS subdirectory** in generated URLs (relevant when YOURLS is installed in a subdirectory like `/yourls/`).

---

## Custom Icons

The plugin includes 50+ Font Awesome icons (brands like Instagram, YouTube, GitHub... and generic icons like globe, book, mail...).

You can also add your own icons from the **Icons** panel:

- **SVG** — Paste SVG code directly. Use `currentColor` in your SVG for automatic theme color adaptation.
- **Image** — Upload a JPG, PNG, GIF, WebP, or SVG file (max 1 MB). Images are automatically styled to match the theme on the public page.

Custom icons appear in the icon selector when adding or editing links, marked with a `✦` symbol.

---

## File Structure

```
frontend-links/
├── plugin.php              # Plugin entry point, hooks, constants
├── ajax.php                # Dedicated AJAX endpoint (auth + nonce)
├── includes/
│   ├── install.php         # Database table creation + defaults
│   ├── functions.php       # CRUD, uploads, URL normalization, helpers
│   ├── icons.php           # Font Awesome + custom icon system
│   ├── render.php          # Public page rendering (fl_render_page)
│   └── admin-page.php      # Admin interface (full AJAX)
├── assets/
│   ├── css/
│   │   ├── all.min.css     # Font Awesome Free (local, no CDN)
│   │   └── my.css          # Public page styles (Tailwind + custom)
│   ├── js/app.js           # Particle system + animations
│   └── webfonts/           # Font Awesome webfonts (.woff2)
├── languages/
│   ├── frontend-links.pot          # Translation template
│   ├── frontend-links-fr_FR.po     # French translation (source)
│   └── frontend-links-fr_FR.mo     # French translation (compiled)
└── uploads/                # Created at runtime
    └── icons/              # Custom icon image files
```

---

## Translation

The plugin uses the YOURLS i18n system with textdomain `frontend-links`.

To add a new language:

1. Copy `languages/frontend-links.pot` to `languages/frontend-links-xx_XX.po` (where `xx_XX` is your locale, e.g. `de_DE`)
2. Translate all `msgstr` entries using a PO editor like [Poedit](https://poedit.net)
3. Compile to `.mo` (Poedit does this automatically on save)
4. Set `YOURLS_LANG` to your locale in your YOURLS `config.php`:

```php
define('YOURLS_LANG', 'de_DE');
```

The plugin will automatically load the matching `.mo` file.

---

## Database

The plugin creates 4 tables (prefixed with `frontend_`):

| Table | Purpose |
|-------|---------|
| `frontend_settings` | Profile data (name, bio, avatar URL, SEO meta) |
| `frontend_sections` | Link sections (title, sort order, active state) |
| `frontend_links` | Links (label, URL, icon, section, sort order) |
| `frontend_icons` | Custom icons (name, type, SVG/image content) |

Two YOURLS options are also stored:
- `fl_display_mode` — `manual` or `auto`
- `fl_shorturl_include_path` — `0` or `1`

---

## Requirements

- YOURLS 1.9+
- PHP 8.0+
- MySQL / MariaDB with InnoDB support

---

## License

MIT

---

## Author

[Liwue](https://liwue.link)
