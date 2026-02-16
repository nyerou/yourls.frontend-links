# Frontend Links - YOURLS Plugin

Customizable link page with section, link, and profile management from the YOURLS admin. Designed for **link-in-bio** style pages.

## Features

- **Sections & Links** management with drag-friendly sort order
- **Profile** customization (name, bio, avatar with upload/restore)
- **Custom icons** (SVG code or image upload) alongside built-in Font Awesome icons
- **SEO** meta tags, Open Graph, Twitter Card, and Schema.org (JSON-LD)
- **Branded redirect page**: mini interstitial with OG metadata before redirecting (can be disabled)
- **Branded 404 page**: custom error page matching your site design (can be disabled)
- **Auto mode**: generates `index.php` and `.htaccess` at the document root, no manual setup needed
- **Manual mode**: include `fl_render_page()` from any PHP file
- **Subdirectory support**: works when YOURLS is installed in a subdirectory (e.g. `example.com/yourls`)
  - Short URLs resolve at the root (`/keyword` not `/yourls/keyword`)
  - Stats links (`keyword+`) are corrected to include the subdirectory
  - Auto-generated `.htaccess` handles URL rewriting
  - JSON-LD and meta tags use the root domain
- **CSP compliant**: no inline scripts or styles, compatible with YOURLS 1.10+ strict Content Security Policy
- **i18n ready** with French translation included

## Requirements

- [YOURLS](https://yourls.org/) 1.9+
- PHP 8.0+
- Apache with `mod_rewrite` (for auto mode)

## Installation

1. Download or clone this repository into `user/plugins/frontend-links/`
2. Activate the plugin in the YOURLS admin (`Manage Plugins`)
3. Go to the **Frontend Administration** admin page to install tables and configure

## Configuration

### Display mode

| Mode | Description |
|------|-------------|
| **Automatic** | The plugin creates `index.php` and `.htaccess` at the document root. The link page is served at `/` and short URLs resolve at the root. |
| **Manual** | Include the rendering function in your own PHP file. |

Manual mode example:

```php
<?php
require_once __DIR__ . '/yourls/includes/load-yourls.php';
fl_render_page();
```

### Features

| Option | Description |
|--------|-------------|
| **Branded redirect page** | When enabled (default), short URL clicks show a branded interstitial page with OG metadata before redirecting. When disabled, uses a direct HTTP 302 redirect. |
| **Branded 404 page** | When enabled (default), unknown URLs show a branded error page. When disabled, the server or another plugin handles the 404. |

### Short links

When a link is entered without a protocol (e.g. `git`), the YOURLS domain is added automatically:

- `git` &rarr; `https://example.com/git`

An option allows including the YOURLS subdirectory in generated URLs if needed.

### Profile

- **Name** and **Bio** displayed on the page
- **Avatar**: upload an image or use an external URL. Previous avatar can be restored.
- **SEO Title** and **Description** for meta tags

### Custom icons

- **SVG code**: paste inline SVG, use `stroke="currentColor"` for theme adaptation
- **Image**: upload JPG, PNG, GIF, WebP, or SVG (max 1 MB)

## File structure

```
frontend-links/
├── plugin.php              # Plugin entry point, hooks and filters
├── ajax.php                # AJAX endpoint for admin CRUD
├── includes/
│   ├── functions.php       # Core logic (CRUD, URL helpers, file management)
│   ├── icons.php           # Font Awesome + custom icon system
│   ├── install.php         # Database table creation
│   └── render.php          # Homepage rendering logic
├── templates/
│   ├── home.php            # Homepage (link-in-bio page)
│   ├── admin.php           # Admin panel interface
│   ├── redirect.php        # Branded redirect interstitial
│   └── 404.php             # Branded 404 error page
├── assets/
│   ├── css/
│   │   ├── admin.css       # Admin panel styles
│   │   ├── pages.css       # Redirect + 404 page styles
│   │   ├── my.css          # Homepage styles (compiled Tailwind)
│   │   └── all.min.css     # Font Awesome (vendor)
│   └── js/
│       ├── admin.js        # Admin panel logic (CSP-compliant)
│       ├── redirect.js     # Redirect delay script
│       ├── stats-rewrite.js # Stats link subdirectory fix
│       └── app.js          # Homepage particle system
├── uploads/                # Avatars & custom icon images
│   └── icons/
└── languages/              # Translation files (.pot, .po)
```

## Changelog

### 1.2
- Admin page renamed to "Frontend Administration"
- All CSS and JS externalized to separate asset files (CSP compliant with YOURLS 1.10+)
- New templates directory: `home.php`, `admin.php`, `redirect.php`, `404.php`
- Branded redirect interstitial page with OG metadata for social previews
- Branded 404 error page with glass-card design
- Feature toggles to disable branded redirect and/or 404 pages
- Stats links fixed to include YOURLS subdirectory when stripped by short URL filter
- Comprehensive file header comments on all source files

### 1.1
- Subdirectory support: short URLs now resolve at the root domain
- Auto mode generates `.htaccess` with rewrite rules for short URL resolution
- JSON-LD, canonical, and Open Graph URLs use the root domain (without subdirectory)
- Link URLs displayed in admin and frontend strip the YOURLS subdirectory

### 1.0
- Initial release

## License

MIT

## Author

[Nyerou](https://nyerou.link)
