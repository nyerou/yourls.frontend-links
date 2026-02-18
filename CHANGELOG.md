# Changelog

All notable changes to Frontend Links are documented here.

## [1.3] - 2026-02-18

### Added

- **Theme system**: themes live in `themes/<slug>/` with a `theme.json` manifest, `templates/` (home, redirect, 404) and `assets/`. Active theme is selected from the Options panel in the admin.
- **Default theme**: minimal responsive design using CSS custom properties — automatically adapts to light/dark system preference via `prefers-color-scheme`.
- **Nyerou Original theme**: previous design preserved as a dedicated theme (dark glassmorphism with particle system).
- **Dark mode** for the default theme — all colors defined as CSS variables, a single `@media (prefers-color-scheme: dark)` block overrides the root variables, no CSS duplication.
- **Generator meta tag**: `<meta name="generator" content="Frontend Links 1.3 by www.Nyerou.link">` injected via output buffering on every frontend page (home, redirect, 404), independent of the active theme.
- **PHP version & extensions** displayed in the Information panel with green/red status indicators (`fileinfo`, `curl`).
- **Uploads `.htaccess` auto-generation**: `fl_write_uploads_htaccess()` generates the security file at activation with the correct `RewriteBase` for root and subdirectory installs. No longer a static committed file.

### Changed

- `FL_VERSION` constant defined in `plugin.php` — single source of truth for the version number.
- `includes/themes.php` added to the plugin bootstrap.

### Fixed

- **PHP 8.5 compatibility**: replaced deprecated procedural `finfo_open()` / `finfo_close()` with OOP `new finfo()` (auto-freed). Removed deprecated `curl_close()` call (no-op since PHP 8.0).
- **`yourls_get_db()` context**: all 23 database calls now pass the required `"(read|write)-description"` context string, eliminating PHP notices that corrupted AJAX JSON responses.

## [1.2]

### Added

- Redirect page fetches OG metadata from target URL (image, type, description, theme-color, title) for accurate social link previews (Discord, Slack, Twitter, Facebook).
- Branded redirect interstitial page with target page metadata.
- Branded 404 error page.
- Feature toggles to disable branded redirect and/or 404 pages.
- Security: SVG sanitization (XSS prevention), SSRF protection on URL fetching, uploads directory lockdown.
- Comprehensive file header comments on all source files.

### Changed

- Admin page renamed to "Frontend Administration".
- Author in meta tags is now the shortener domain (e.g. `nyerou.link`).
- All CSS and JS externalized to separate asset files (CSP compliant with YOURLS 1.10+).
- New templates directory: `home.php`, `admin.php`, `redirect.php`, `404.php`.

### Fixed

- Stats links corrected to include YOURLS subdirectory when stripped by the short URL filter.

## [1.1]

### Added

- Subdirectory support: short URLs now resolve at the root domain.
- Auto mode generates `.htaccess` with rewrite rules for short URL resolution.

### Changed

- JSON-LD, canonical, and Open Graph URLs use the root domain (without subdirectory).
- Link URLs displayed in admin and frontend strip the YOURLS subdirectory.

## [1.0]

- Initial release.
