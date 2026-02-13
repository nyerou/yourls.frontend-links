# Frontend Links - YOURLS Plugin

Customizable link page with section, link, and profile management from the YOURLS admin. Designed for **link-in-bio** style pages.

## Features

- **Sections & Links** management with drag-friendly sort order
- **Profile** customization (name, bio, avatar with upload/restore)
- **Custom icons** (SVG code or image upload) alongside built-in Font Awesome icons
- **SEO** meta tags, Open Graph, Twitter Card, and Schema.org (JSON-LD)
- **Auto mode**: generates `index.php` and `.htaccess` at the document root, no manual setup needed
- **Manual mode**: include `fl_render_page()` from any PHP file
- **Subdirectory support**: works when YOURLS is installed in a subdirectory (e.g. `example.com/yourls`)
  - Short URLs resolve at the root (`/keyword` not `/yourls/keyword`)
  - Auto-generated `.htaccess` handles URL rewriting
  - JSON-LD and meta tags use the root domain
- **i18n ready** with French translation included

## Requirements

- [YOURLS](https://yourls.org/) 1.9+
- PHP 8.0+
- Apache with `mod_rewrite` (for auto mode)

## Installation

1. Download or clone this repository into `user/plugins/frontend-links/`
2. Activate the plugin in the YOURLS admin (`Manage Plugins`)
3. Go to the **Frontend Links** admin page to install tables and configure

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
├── plugin.php            # Plugin entry point
├── ajax.php              # AJAX endpoint for admin CRUD
├── includes/
│   ├── admin-page.php    # Admin interface
│   ├── functions.php     # CRUD, helpers, file management
│   ├── icons.php         # Icon registry
│   ├── install.php       # Table creation
│   └── render.php        # Public page rendering
├── assets/
│   ├── css/
│   │   ├── my.css        # Page styles
│   │   └── all.min.css   # Font Awesome
│   └── js/
│       └── app.js        # Particles & animations
├── uploads/              # Avatars & custom icon images
│   └── icons/
└── languages/            # Translation files (.pot, .po)
```

## Changelog

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
