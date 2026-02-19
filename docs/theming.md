# Creating a Theme for Frontend Links

This guide covers everything needed to build a custom theme from scratch.

## How themes work

Themes live in the `themes/` directory inside the plugin folder. The plugin scans that directory at runtime — any folder containing a valid `theme.json` is treated as an available theme and appears in the admin **Options → Theme** selector.

When a page is requested the plugin resolves templates in this order:

1. `themes/<active-theme>/templates/<template>.php`
2. `themes/default/templates/<template>.php` *(fallback)*

This means a theme only needs to include the templates it actually customises. Missing templates are silently served from `default`.

---

## Directory structure

```
themes/
└── my-theme/
    ├── theme.json           ← required
    ├── templates/
    │   ├── home.php         ← link-in-bio page
    │   ├── redirect.php     ← short URL interstitial
    │   └── 404.php          ← not found page
    └── assets/
        ├── css/
        │   ├── home.css
        │   └── pages.css
        └── js/
            └── app.js
```

Only `theme.json` is strictly required. Every template and asset file is optional.

---

## theme.json

```json
{
    "name":        "My Theme",
    "author":      "Your Name",
    "version":     "1.0",
    "description": "A short description shown in the admin panel."
}
```

| Field         | Required | Description                              |
| ------------- | -------- | ---------------------------------------- |
| `name`        | yes      | Display name shown in the theme selector |
| `author`      | no       | Shown next to the name in the selector   |
| `version`     | no       | Free-form version string                 |
| `description` | no       | Shown below the selector when active     |

---

## Templates

Each template is a plain PHP file that outputs a full HTML document. Two PHP variables are always available regardless of the template:

| Variable          | Value                                                    |
| ----------------- | -------------------------------------------------------- |
| `$e`              | Shorthand for `fl_escape()` — use it on every output     |
| `$sharedAssetsUrl`| URL to the plugin's shared `assets/` folder (Font Awesome, `redirect.js`) |
| `$themeAssetsUrl` | URL to your theme's `assets/` folder                    |
| `$assetsUrl`      | Alias for `$sharedAssetsUrl` (backward compatibility)   |

> **Security**: always wrap every echoed value with `$e(...)` or `fl_escape()`.
> **CSP**: no inline `<script>` or `style="..."` attributes — use external files only.

---

### home.php

The main link-in-bio page.

| Variable          | Type     | Description                                    |
| ----------------- | -------- | ---------------------------------------------- |
| `$profileName`    | string   | Display name                                   |
| `$profileBio`     | string   | Bio / tagline                                  |
| `$profileAvatar`  | string   | Avatar URL (empty string if not set)           |
| `$initials`       | string   | First letter of each word in the name          |
| `$metaTitle`      | string   | SEO `<title>` value                            |
| `$metaDescription`| string   | SEO description                                |
| `$siteUrl`        | string   | Root URL of the site                           |
| `$adminPageUrl`   | string   | URL to the YOURLS admin panel                  |
| `$htmlLang`       | string   | Two-letter language code (e.g. `en`)           |
| `$sections`       | array    | Active sections, sorted. Each has `id`, `title`. |
| `$linksBySection` | array    | Links keyed by section `id`. Each link has `label`, `url`, `icon`, `sort_order`. |

**Rendering an icon:**

```php
<?= fl_get_icon($link['icon'], 18) ?>
```

**Stripping the YOURLS subdirectory from a URL:**

```php
<?= $e(fl_strip_base_path($link['url'])) ?>
```

**Minimal home.php skeleton:**

```php
<?php if (!defined('YOURLS_ABSPATH')) die(); ?>
<!DOCTYPE html>
<html lang="<?= $e($htmlLang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $e($metaTitle) ?></title>
    <link rel="stylesheet" href="<?= $e($sharedAssetsUrl) ?>/css/all.min.css">
    <link rel="stylesheet" href="<?= $e($themeAssetsUrl) ?>/css/home.css">
</head>
<body>
    <h1><?= $e($profileName) ?></h1>
    <?php foreach ($sections as $section):
        $links = $linksBySection[$section['id']] ?? [];
        if (empty($links)) continue;
    ?>
    <section>
        <h2><?= $e($section['title']) ?></h2>
        <?php foreach ($links as $link): ?>
        <a href="<?= $e(fl_strip_base_path($link['url'])) ?>" target="_blank" rel="noopener noreferrer">
            <?= fl_get_icon($link['icon'], 16) ?>
            <?= $e($link['label']) ?>
        </a>
        <?php endforeach; ?>
    </section>
    <?php endforeach; ?>
</body>
</html>
```

---

### redirect.php

Shown for 1 second before redirecting to the destination. The redirect is handled by `assets/js/redirect.js` (reads `<meta name="fl-redirect-url">`). Include it via `$sharedAssetsUrl`.

| Variable          | Type        | Description                                        |
| ----------------- | ----------- | -------------------------------------------------- |
| `$keyword`        | string      | Short URL keyword                                  |
| `$url`            | string      | Full destination URL                               |
| `$shortUrl`       | string      | Full short URL (e.g. `https://example.com/git`)    |
| `$linkTitle`      | string      | YOURLS title for the keyword                       |
| `$authorName`     | string      | Profile name (or domain if not set)                |
| `$metaTitle`      | string      | Target page `<title>`                              |
| `$metaDescription`| string      | Target page description                            |
| `$metaImage`      | string      | Target page OG image URL (empty if none)           |
| `$metaType`       | string      | Target page OG type                                |
| `$metaThemeColor` | string\|null| Target page `theme-color` (null if none)           |
| `$metaAuthor`     | string      | Shortener domain                                   |
| `$twitterCard`    | string      | `summary_large_image` or `summary`                 |
| `$cleanShort`     | string      | Short URL without protocol/trailing slash          |
| `$cleanDest`      | string      | Destination URL without protocol/query string      |

**Minimal redirect.php skeleton:**

```php
<?php if (!defined('YOURLS_ABSPATH')) die(); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= $e($metaTitle) ?></title>
    <meta http-equiv="refresh" content="1;url=<?= $e($url) ?>">
    <meta name="fl-redirect-url" content="<?= $e($url) ?>">
    <link rel="stylesheet" href="<?= $e($themeAssetsUrl) ?>/css/pages.css">
</head>
<body>
    <p>Redirecting to <a href="<?= $e($url) ?>"><?= $e($cleanDest) ?></a>…</p>
    <script src="<?= $e($sharedAssetsUrl) ?>/js/redirect.js"></script>
</body>
</html>
```

> The `<meta http-equiv="refresh">` acts as a no-JS fallback. The JS script handles the actual timed redirect and is CSP-safe.

---

### 404.php

Shown for unknown short URL keywords.

| Variable      | Type   | Description                         |
| ------------- | ------ | ----------------------------------- |
| `$request`    | string | The requested keyword / path        |
| `$homeUrl`    | string | Root URL (link back to homepage)    |
| `$authorName` | string | Profile name (or domain if not set) |

**Minimal 404.php skeleton:**

```php
<?php if (!defined('YOURLS_ABSPATH')) die(); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>404</title>
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="<?= $e($themeAssetsUrl) ?>/css/pages.css">
</head>
<body>
    <h1>404</h1>
    <p>/<?= $e($request) ?> does not exist.</p>
    <a href="<?= $e($homeUrl) ?>">Back to homepage</a>
</body>
</html>
```

---

## Assets

Reference your theme's CSS and JS files through `$themeAssetsUrl`:

```php
<!-- Your theme CSS -->
<link rel="stylesheet" href="<?= $e($themeAssetsUrl) ?>/css/home.css">

<!-- Your theme JS -->
<script src="<?= $e($themeAssetsUrl) ?>/js/app.js"></script>
```

Reference shared plugin assets (Font Awesome, redirect script) through `$sharedAssetsUrl`:

```php
<!-- Font Awesome icons -->
<link rel="stylesheet" href="<?= $e($sharedAssetsUrl) ?>/css/all.min.css">

<!-- Redirect delay script (redirect.php only) -->
<script src="<?= $e($sharedAssetsUrl) ?>/js/redirect.js"></script>
```

---

## Step-by-step: creating a theme

1. **Create the folder** `themes/my-theme/`

2. **Add `theme.json`**:
   ```json
   {
       "name": "My Theme",
       "author": "Your Name",
       "version": "1.0"
   }
   ```

3. **Copy the templates you want to customise** from `themes/default/templates/` into `themes/my-theme/templates/`. Any template you do not include will fall back to the default automatically.

4. **Add your CSS/JS** in `themes/my-theme/assets/` and reference them via `$themeAssetsUrl` in your templates.

5. **Activate the theme** in the YOURLS admin under **Frontend Administration → Options → Theme**.

---

## Notes

- The `<meta name="generator">` tag is injected automatically by the plugin via output buffering — you do not need to add it yourself.
- The HTTP status code (`404 Not Found`) and `Content-Type` headers are sent by the plugin before the template is loaded — do not send headers inside a template.
- Templates run inside YOURLS context: all YOURLS functions (`yourls__()`, `yourls_e()`, etc.) are available.
