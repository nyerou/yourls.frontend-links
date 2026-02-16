<?php
/**
 * Frontend Links - Core Functions
 * =================================
 *
 * All server-side logic for the plugin:
 *   - Helpers (escape, URL parsing, base path stripping)
 *   - Avatar management (upload, rotate, restore, delete)
 *   - Custom icon CRUD (SVG + image upload)
 *   - URL normalization (short keyword → full URL)
 *   - Short URL resolution (keyword lookup → redirect or 404)
 *   - Redirect & 404 page serving (loads templates with variables)
 *   - Homepage file management (auto-generated index.php + .htaccess)
 *   - Settings / Sections / Links CRUD (DB operations)
 *
 * Database: Uses YOURLS DB connection (Aura.SQL / PDO).
 * All tables are prefixed with FL_TABLE_PREFIX ("frontend_").
 *
 * @see plugin.php   Entry point that loads this file
 * @see ajax.php     AJAX endpoint that calls these functions
 *
 * @package FrontendLinks
 */

if (!defined('YOURLS_ABSPATH')) die();

// ─── Helpers ────────────────────────────────────────────────

function fl_table(string $name): string {
    return FL_TABLE_PREFIX . $name;
}

function fl_escape(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Extract the path from YOURLS_SITE
 * E.g.: "https://example.com/yourls" → "/yourls"
 * E.g.: "https://example.com" → ""
 */
function fl_get_yourls_base_path(): string {
    $parsed = parse_url(YOURLS_SITE);
    return rtrim($parsed['path'] ?? '', '/');
}

/**
 * Get the root URL (scheme + host + port) without the YOURLS subdirectory.
 * E.g.: "https://example.com/yourls" → "https://example.com"
 * E.g.: "https://example.com:8080/yourls" → "https://example.com:8080"
 */
function fl_get_root_url(): string {
    $parsed = parse_url(YOURLS_SITE);
    $scheme = $parsed['scheme'] ?? 'https';
    $host = $parsed['host'] ?? '';
    $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
    return $scheme . '://' . $host . $port;
}

/**
 * Strip the YOURLS subdirectory from a URL if present.
 * Only modifies URLs on the same host as YOURLS_SITE.
 * E.g.: "https://example.com/yourls/git" → "https://example.com/git"
 * External URLs are returned unchanged.
 */
function fl_strip_base_path(string $url): string {
    $basePath = fl_get_yourls_base_path();
    if ($basePath === '') return $url;

    $parsedSite = parse_url(YOURLS_SITE);
    $parsed = parse_url($url);

    // Only strip if same host
    if (($parsed['host'] ?? '') !== ($parsedSite['host'] ?? '')) return $url;

    // Strip the base path if present
    $path = $parsed['path'] ?? '';
    if ($path === $basePath || str_starts_with($path, $basePath . '/')) {
        $newPath = substr($path, strlen($basePath));
        if ($newPath === '') $newPath = '/';
        $result = ($parsed['scheme'] ?? 'https') . '://' . $parsed['host'];
        if (isset($parsed['port'])) $result .= ':' . $parsed['port'];
        $result .= $newPath;
        if (isset($parsed['query'])) $result .= '?' . $parsed['query'];
        if (isset($parsed['fragment'])) $result .= '#' . $parsed['fragment'];
        return $result;
    }

    return $url;
}

/**
 * Check if the plugin tables exist in the database
 */
function fl_tables_exist(): bool {
    try {
        $db = yourls_get_db();
        $table = fl_table('settings');
        $db->query("SELECT 1 FROM `$table` LIMIT 1");
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// ─── SVG Sanitization ───────────────────────────────────────

/**
 * Sanitize SVG content by removing dangerous elements and attributes.
 * Prevents stored XSS via <script> tags, event handlers, javascript: URLs,
 * and <foreignObject> (which can embed arbitrary HTML).
 */
function fl_sanitize_svg(string $svg): string {
    // Remove <script> tags and their content
    $svg = preg_replace('#<script[^>]*>.*?</script>#is', '', $svg);
    // Remove event handlers (onclick, onload, onerror, etc.)
    $svg = preg_replace('#\s+on\w+\s*=\s*["\'][^"\']*["\']#is', '', $svg);
    $svg = preg_replace('#\s+on\w+\s*=\s*[^\s>]+#is', '', $svg);
    // Remove javascript: and data: URLs in href/xlink:href
    $svg = preg_replace('#(href|xlink:href)\s*=\s*["\'](?:javascript|data):[^"\']*["\']#is', '', $svg);
    // Remove <foreignObject> (can contain arbitrary HTML/JS)
    $svg = preg_replace('#<foreignObject[^>]*>.*?</foreignObject>#is', '', $svg);
    // Remove <use> with external references (can bypass CSP)
    $svg = preg_replace('#<use[^>]+href\s*=\s*["\']https?://[^"\']*["\'][^>]*/?\s*>#is', '', $svg);
    return $svg;
}

// ─── Avatar Management ──────────────────────────────────────
// Only 2 files are kept in the uploads folder:
//   fl_avatars_current.<ext>   → active avatar
//   fl_avatars_previous.<ext>  → previous avatar (restorable)

/**
 * Find an avatar file by prefix (without extension)
 * Returns the full path or null
 */
function fl_find_avatar_file(string $name): ?string {
    if (!is_dir(FL_UPLOADS_DIR)) return null;
    $files = glob(FL_UPLOADS_DIR . '/' . $name . '.*');
    return $files ? $files[0] : null;
}

/**
 * Delete an avatar file by prefix
 */
function fl_delete_avatar_file(string $name): bool {
    $file = fl_find_avatar_file($name);
    if ($file && file_exists($file)) {
        return unlink($file);
    }
    return false;
}

/**
 * Clean all files in uploads folder
 * except fl_avatars_current.* and fl_avatars_previous.*
 */
function fl_cleanup_uploads(): void {
    if (!is_dir(FL_UPLOADS_DIR)) return;
    $files = glob(FL_UPLOADS_DIR . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            $basename = pathinfo($file, PATHINFO_FILENAME);
            if ($basename !== 'fl_avatars_current' && $basename !== 'fl_avatars_previous') {
                unlink($file);
            }
        }
    }
}

/**
 * Upload a new avatar with rotation:
 *  1. Old "previous" is deleted
 *  2. "current" becomes "previous"
 *  3. New upload becomes "current"
 *  4. Orphan files are cleaned up
 *
 * Returns the URL of the new avatar or false on error
 */
function fl_upload_avatar(array $file): string|false {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;

    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed)) return false;
    if ($file['size'] > 2 * 1024 * 1024) return false;

    if (!is_dir(FL_UPLOADS_DIR)) {
        mkdir(FL_UPLOADS_DIR, 0755, true);
    }

    $extensions = [
        'image/jpeg'    => 'jpg',
        'image/png'     => 'png',
        'image/gif'     => 'gif',
        'image/webp'    => 'webp',
        'image/svg+xml' => 'svg',
    ];
    $ext = $extensions[$mime] ?? 'bin';

    // 1. Delete old previous
    fl_delete_avatar_file('fl_avatars_previous');

    // 2. Rename current → previous
    $currentFile = fl_find_avatar_file('fl_avatars_current');
    if ($currentFile) {
        $curExt = pathinfo($currentFile, PATHINFO_EXTENSION);
        rename($currentFile, FL_UPLOADS_DIR . '/fl_avatars_previous.' . $curExt);
    }

    // 3. Save new upload as current
    $dest = FL_UPLOADS_DIR . '/fl_avatars_current.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return false;
    }

    // 4. Clean up orphans (migration from old unique-name system)
    fl_cleanup_uploads();

    return FL_UPLOADS_URL . '/fl_avatars_current.' . $ext;
}

/**
 * Restore previous avatar as current
 * Returns the restored URL or false
 */
function fl_restore_previous_avatar(): string|false {
    $previousFile = fl_find_avatar_file('fl_avatars_previous');
    if (!$previousFile) return false;

    // Delete current
    fl_delete_avatar_file('fl_avatars_current');

    // Rename previous → current
    $ext = pathinfo($previousFile, PATHINFO_EXTENSION);
    rename($previousFile, FL_UPLOADS_DIR . '/fl_avatars_current.' . $ext);

    return FL_UPLOADS_URL . '/fl_avatars_current.' . $ext;
}

/**
 * Delete current avatar (previous remains available for restoration)
 */
function fl_delete_current_avatar(): bool {
    return fl_delete_avatar_file('fl_avatars_current');
}

// ─── Custom Icons ───────────────────────────────────────────

/**
 * Retrieve all custom icons from DB
 * Uses static cache to avoid multiple queries per page
 */
function fl_get_custom_icons(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    try {
        $db = yourls_get_db();
        $table = fl_table('icons');
        $stmt = $db->query("SELECT * FROM `$table` ORDER BY name ASC");
        $cache = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $cache = [];
    }
    return $cache;
}

/**
 * Retrieve custom icons indexed by name (cached)
 */
function fl_get_custom_icons_indexed(): array {
    static $indexed = null;
    if ($indexed !== null) return $indexed;
    $indexed = [];
    foreach (fl_get_custom_icons() as $icon) {
        $indexed[$icon['name']] = $icon;
    }
    return $indexed;
}

/**
 * Invalidate icon cache (after add/delete)
 */
function fl_invalidate_icons_cache(): void {
    global $_fl_icons_cache_invalid;
    $_fl_icons_cache_invalid = true;
}

function fl_create_custom_icon(array $data): int|false {
    $db = yourls_get_db();
    $table = fl_table('icons');
    $stmt = $db->prepare("INSERT INTO `$table` (name, type, content) VALUES (?, ?, ?)");
    $success = $stmt->execute([
        $data['name'],
        $data['type'],
        $data['content']
    ]);
    return $success ? (int)$db->lastInsertId() : false;
}

function fl_delete_custom_icon(int $id): bool {
    $db = yourls_get_db();
    $table = fl_table('icons');

    // Retrieve icon to delete image file if needed
    $stmt = $db->prepare("SELECT * FROM `$table` WHERE id = ?");
    $stmt->execute([$id]);
    $icon = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($icon && $icon['type'] === 'image') {
        $filePath = FL_ICONS_DIR . '/' . $icon['content'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    $stmt = $db->prepare("DELETE FROM `$table` WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Upload an image for custom icon
 * Returns the filename or false
 */
function fl_upload_icon_image(array $file, string $name): string|false {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;

    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed)) return false;
    if ($file['size'] > 1024 * 1024) return false; // 1 MB max

    if (!is_dir(FL_ICONS_DIR)) {
        mkdir(FL_ICONS_DIR, 0755, true);
    }

    $extensions = [
        'image/jpeg'    => 'jpg',
        'image/png'     => 'png',
        'image/gif'     => 'gif',
        'image/webp'    => 'webp',
        'image/svg+xml' => 'svg',
    ];
    $ext = $extensions[$mime] ?? 'bin';

    $safeName = preg_replace('/[^a-z0-9_-]/', '', strtolower($name));
    if ($safeName === '') $safeName = uniqid('icon_');
    $filename = 'icon_' . $safeName . '.' . $ext;
    $dest = FL_ICONS_DIR . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return false;
    }

    // Sanitize uploaded SVG files to prevent stored XSS
    if ($mime === 'image/svg+xml') {
        $svgContent = file_get_contents($dest);
        if ($svgContent !== false) {
            file_put_contents($dest, fl_sanitize_svg($svgContent));
        }
    }

    return $filename;
}

// ─── URL Normalization ──────────────────────────────────────

/**
 * Normalize a user-entered URL.
 * If the URL has no protocol (e.g.: "git", "mylink"),
 * it is transformed into a YOURLS short URL by prepending the domain.
 * The fl_shorturl_include_path option determines whether the YOURLS
 * subdirectory is included in the generated URL.
 *
 * Examples (YOURLS_SITE = "https://example.com/yourls"):
 *   "git"                  → "https://example.com/git"        (include_path = 0)
 *   "git"                  → "https://example.com/yourls/git" (include_path = 1)
 *   "https://github.com"   → "https://github.com"             (unchanged)
 *   "/page"                → "/page"                          (unchanged)
 */
function fl_normalize_url(string $url): string {
    $url = trim($url);
    if ($url === '') return '';

    // If the URL already has a protocol or starts with / or #, don't modify
    if (preg_match('#^(https?://|ftp://|mailto:|tel:|/|\#)#i', $url)) {
        return $url;
    }

    // Build base URL from YOURLS_SITE
    $parsed = parse_url(YOURLS_SITE);
    $scheme = $parsed['scheme'] ?? 'https';
    $host = $parsed['host'] ?? '';
    $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
    $baseUrl = $scheme . '://' . $host . $port;

    // Include YOURLS path if option is enabled
    $includePath = yourls_get_option('fl_shorturl_include_path', '0');
    if ($includePath === '1') {
        $path = rtrim($parsed['path'] ?? '', '/');
        $baseUrl .= $path;
    }

    return $baseUrl . '/' . ltrim($url, '/');
}

// ─── Target URL Metadata ────────────────────────────────────

/**
 * Fetch OG metadata from a target URL.
 *
 * Extracts: <title>, og:image, og:type, og:description (or meta description),
 * and theme-color. Uses a short timeout to avoid blocking the redirect page.
 *
 * @return array{title:string, description:string, image:string, type:string, theme_color:string}
 */
function fl_fetch_target_meta(string $url): array {
    $result = [
        'title'       => '',
        'description' => '',
        'image'       => '',
        'type'        => '',
        'theme_color' => '',
    ];

    if (!function_exists('curl_init')) return $result;

    // SSRF protection: only allow http(s) and block private/reserved IPs
    $parsed = parse_url($url);
    if (!in_array($parsed['scheme'] ?? '', ['http', 'https'])) return $result;
    $host = $parsed['host'] ?? '';
    if ($host === '') return $result;
    $ip = gethostbyname($host);
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return $result;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_TIMEOUT        => 2,
        CURLOPT_CONNECTTIMEOUT => 1,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; FrontendLinks/1.2; +' . YOURLS_SITE . ')',
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$html || $httpCode >= 400) return $result;

    // Only parse <head> for performance
    if (preg_match('#<head[^>]*>(.*?)</head>#is', $html, $m)) {
        $head = $m[1];
    } else {
        return $result;
    }

    // <title> tag
    if (preg_match('#<title[^>]*>(.*?)</title>#is', $head, $m)) {
        $result['title'] = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
    }

    // Collect all <meta> tags into a property → content map
    $metas = [];
    preg_match_all('#<meta\s+([^>]+)>#is', $head, $tags);
    foreach ($tags[1] as $attrs) {
        $key = '';
        $content = '';
        if (preg_match('#(?:property|name)\s*=\s*["\']([^"\']+)["\']#i', $attrs, $a)) {
            $key = strtolower($a[1]);
        }
        if (preg_match('#content\s*=\s*["\']([^"\']*)["\']#i', $attrs, $a)) {
            $content = $a[1];
        }
        if ($key !== '' && $content !== '') {
            $metas[$key] = html_entity_decode($content, ENT_QUOTES, 'UTF-8');
        }
    }

    if (!empty($metas['og:description']))  $result['description'] = $metas['og:description'];
    elseif (!empty($metas['description'])) $result['description'] = $metas['description'];

    if (!empty($metas['og:image']))   $result['image']       = $metas['og:image'];
    if (!empty($metas['og:type']))    $result['type']        = $metas['og:type'];
    if (!empty($metas['theme-color'])) $result['theme_color'] = $metas['theme-color'];

    return $result;
}

// ─── Short URL Resolution ───────────────────────────────────

/**
 * Serve a short URL request (called from generated index.php).
 * - keyword not found → 404
 * - Otherwise → delegate to mini redirect page
 *
 * Note: Stats (keyword+) are only accessible via the YOURLS admin
 * subdirectory (e.g. /-/keyword+). Requests without the subdirectory
 * are intentionally NOT redirected to stats for security.
 */
function fl_serve_short_url(string $request): void {
    // Stats format (keyword+) → 404 at root level
    if (str_contains($request, '+')) {
        fl_serve_404_page($request);
        exit;
    }

    $keyword = yourls_sanitize_keyword($request);
    $url = yourls_get_keyword_longurl($keyword);

    if (!$url) {
        fl_serve_404_page($request);
        exit;
    }

    // Log the click
    if (function_exists('yourls_log_redirect')) {
        yourls_log_redirect($keyword);
    }

    fl_serve_redirect_page($keyword, $url);
    exit;
}

/**
 * Render the branded mini redirect page with OG metadata.
 * Called either from fl_serve_short_url() or from the
 * redirect_shorturl hook in plugin.php.
 *
 * Template: templates/redirect.php (edit that file to customize)
 */
function fl_serve_redirect_page(string $keyword, string $url): void {
    // If branded redirect page is disabled, do a direct redirect
    if (yourls_get_option('fl_disable_redirect_page') === '1') {
        header('Location: ' . $url, true, 302);
        exit;
    }

    // Get title from YOURLS DB
    $db = yourls_get_db();
    $table = YOURLS_DB_TABLE_URL;
    $stmt = $db->prepare("SELECT title FROM `$table` WHERE keyword = ? LIMIT 1");
    $stmt->execute([$keyword]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $linkTitle = !empty($row['title']) ? $row['title'] : $keyword;

    // Fetch OG metadata from the target page
    $targetMeta  = fl_fetch_target_meta($url);

    // Template variables
    $shortUrl    = fl_get_root_url() . '/' . $keyword;
    $settings    = fl_tables_exist() ? fl_get_settings() : [];
    $authorName  = $settings['profile_name'] ?? parse_url(YOURLS_SITE, PHP_URL_HOST);
    $e           = 'fl_escape';

    // Meta tags: identity of the target page, authored by our domain
    $metaAuthor      = parse_url(YOURLS_SITE, PHP_URL_HOST);
    $metaTitle       = $targetMeta['title'] ?: $linkTitle;
    $metaDescription = $targetMeta['description'] ?: $linkTitle;
    $metaImage       = $targetMeta['image'] ?: ($settings['profile_avatar'] ?? '');
    $metaType        = $targetMeta['type'] ?: 'website';
    $metaThemeColor  = $targetMeta['theme_color'];
    $twitterCard     = $metaImage ? 'summary_large_image' : 'summary';

    // Clean URLs for display (strip protocol, query string, trailing slash)
    $cleanShort  = preg_replace('#^https?://#', '', rtrim($shortUrl, '/'));
    $cleanDest   = preg_replace('#^https?://#', '', preg_replace('/[?#].*$/', '', rtrim($url, '/')));

    // Assets URL for external CSS/JS references
    $assetsUrl   = yourls_plugin_url(FL_PLUGIN_DIR) . '/assets';

    header('Content-Type: text/html; charset=UTF-8');
    require FL_PLUGIN_DIR . '/templates/redirect.php';
    exit;
}

/**
 * Render a branded 404 page.
 * Template: templates/404.php (edit that file to customize)
 */
function fl_serve_404_page(string $request): void {
    // If branded 404 page is disabled, send a basic 404 and stop
    if (yourls_get_option('fl_disable_404_page') === '1') {
        header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
        exit;
    }

    $homeUrl    = fl_get_root_url() . '/';
    $settings   = fl_tables_exist() ? fl_get_settings() : [];
    $authorName = $settings['profile_name'] ?? parse_url(YOURLS_SITE, PHP_URL_HOST);
    $e          = 'fl_escape';

    // Assets URL for external CSS/JS references
    $assetsUrl  = yourls_plugin_url(FL_PLUGIN_DIR) . '/assets';

    header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
    header('Content-Type: text/html; charset=UTF-8');
    require FL_PLUGIN_DIR . '/templates/404.php';
    exit;
}

// ─── Homepage File Management ───────────────────────────────

/**
 * Create an index.php file at the document root
 * to serve the links page in automatic mode.
 * The file contains a marker so it can be properly removed.
 *
 * Also creates a security index.php inside the YOURLS subdirectory
 * (if any) to redirect to admin.
 */
function fl_create_homepage_file(): array {
    $yourlsBasePath = fl_get_yourls_base_path();

    if ($yourlsBasePath !== '') {
        // YOURLS is in a subdirectory: create index.php at the document root
        $segments = array_filter(explode('/', trim($yourlsBasePath, '/')));
        $docRoot = YOURLS_ABSPATH;
        for ($i = 0, $n = count($segments); $i < $n; $i++) {
            $docRoot = dirname($docRoot);
        }
    } else {
        // YOURLS is at the root: create index.php in YOURLS directory
        $docRoot = YOURLS_ABSPATH;
    }

    $filePath = rtrim($docRoot, '/\\') . '/index.php';

    // Use absolute path to load-yourls.php (no relative ./ ambiguity)
    $yourlsLoadPath = rtrim(YOURLS_ABSPATH, '/\\') . '/includes/load-yourls.php';
    // Normalize to forward slashes for cross-platform compatibility
    $yourlsLoadPath = str_replace('\\', '/', $yourlsLoadPath);

    $marker = '/* FRONTEND_LINKS_AUTO_GENERATED */';
    $content = "<?php\n"
        . "$marker\n"
        . "// Auto-generated file by the Frontend Links plugin.\n"
        . "// Do not modify - it will be deleted if you switch to manual mode.\n"
        . "require_once '$yourlsLoadPath';\n"
        . "\n"
        . "\$request = trim(parse_url(\$_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');\n"
        . "\n"
        . "if (\$request === '') {\n"
        . "    fl_render_page();\n"
        . "} else {\n"
        . "    fl_serve_short_url(\$request);\n"
        . "}\n";

    // Check if an index.php already exists and is not ours
    if (file_exists($filePath)) {
        $existing = file_get_contents($filePath);
        if (strpos($existing, $marker) === false) {
            return [
                'success' => false,
                'message' => yourls__('An index.php file already exists at the root and was not created by this plugin. Delete it manually or use manual mode.', 'frontend-links')
            ];
        }
    }

    if (file_put_contents($filePath, $content) === false) {
        return [
            'success' => false,
            'message' => yourls__('Unable to write the index.php file. Check folder permissions.', 'frontend-links')
        ];
    }

    // Store the path for later cleanup
    yourls_update_option('fl_homepage_file_path', $filePath);

    // Create security redirect in YOURLS subdirectory (only if there is one)
    if ($yourlsBasePath !== '') {
        fl_create_yourls_root_index();
    }

    // Create .htaccess at the document root for short URL rewriting
    $htaccessResult = fl_create_root_htaccess($docRoot, $yourlsBasePath);

    $msg = sprintf(yourls__('index.php file created: %s', 'frontend-links'), $filePath);
    if (!$htaccessResult['success']) {
        $msg .= ' ' . $htaccessResult['message'];
    }

    return [
        'success' => true,
        'message' => $msg
    ];
}

/**
 * Create or update .htaccess at the document root to rewrite short URLs.
 *
 * Rules generated (in order):
 *  1. YOURLS subdirectory passthrough (if subdirectory exists)
 *     /{subdir}/* → let YOURLS handle it (admin, loader, stats, etc.)
 *  2. Existing files/directories → serve as-is
 *  3. Everything else → index.php (Frontend Links handler)
 */
function fl_create_root_htaccess(string $docRoot, string $yourlsBasePath): array {
    $htaccessPath = rtrim($docRoot, '/\\') . '/.htaccess';
    $marker = '# BEGIN Frontend Links';
    $markerEnd = '# END Frontend Links';

    // Build the rules
    $rules = "$marker\n"
        . "<IfModule mod_rewrite.c>\n"
        . "RewriteEngine On\n"
        . "RewriteBase /\n";

    // If YOURLS is in a subdirectory, add stats routing + passthrough
    if ($yourlsBasePath !== '') {
        // Trim leading slash for .htaccess pattern matching
        $pathForRegex = ltrim($yourlsBasePath, '/');

        // Let YOURLS handle its own subdirectory (admin, loader, stats, etc.)
        $rules .= "# Let YOURLS handle its subdirectory\n"
            . "RewriteRule ^" . $pathForRegex . "/ - [L]\n";
    }

    $rules .= "# Existing files/directories pass through\n"
        . "RewriteCond %{REQUEST_FILENAME} -f [OR]\n"
        . "RewriteCond %{REQUEST_FILENAME} -d\n"
        . "RewriteRule ^ - [L]\n"
        . "# Everything else -> Frontend Links handler\n"
        . "RewriteRule ^(.*)$ index.php [L]\n"
        . "</IfModule>\n"
        . "$markerEnd\n";

    // If .htaccess exists, replace our block or append
    if (file_exists($htaccessPath)) {
        $existing = file_get_contents($htaccessPath);

        if (strpos($existing, $marker) !== false) {
            // Replace existing block
            $pattern = '/' . preg_quote($marker, '/') . '.*?' . preg_quote($markerEnd, '/') . '\n?/s';
            $content = preg_replace($pattern, $rules, $existing);
        } else {
            // Append our block
            $content = rtrim($existing) . "\n\n" . $rules;
        }
    } else {
        $content = $rules;
    }

    if (file_put_contents($htaccessPath, $content) === false) {
        return [
            'success' => false,
            'message' => yourls__('Unable to write the .htaccess file. Short URLs at the root may not work.', 'frontend-links')
        ];
    }

    yourls_update_option('fl_htaccess_file_path', $htaccessPath);

    return ['success' => true, 'message' => ''];
}

/**
 * Create a security index.php inside the YOURLS subdirectory
 * that redirects to admin. Prevents directory listing and
 * secures the YOURLS root when the frontend page is at /.
 * Only creates the file if none exists or if it was created by us.
 */
function fl_create_yourls_root_index(): void {
    $filePath = rtrim(YOURLS_ABSPATH, '/\\') . '/index.php';
    $marker = '/* FRONTEND_LINKS_YOURLS_REDIRECT */';

    // Don't overwrite existing files not created by us
    if (file_exists($filePath)) {
        $existing = file_get_contents($filePath);
        if (strpos($existing, $marker) === false) {
            return;
        }
    }

    $content = "<?php\n"
        . "$marker\n"
        . "header('Location: admin/');\n"
        . "exit;\n";

    if (file_put_contents($filePath, $content) !== false) {
        yourls_update_option('fl_yourls_root_index_path', $filePath);
    }
}

/**
 * Delete the index.php files created by fl_create_homepage_file()
 * Only deletes if the files contain the plugin markers.
 */
function fl_delete_homepage_file(): array {
    // Delete the document root index.php
    $filePath = yourls_get_option('fl_homepage_file_path', '');

    if (!empty($filePath) && file_exists($filePath)) {
        $content = file_get_contents($filePath);
        $marker = '/* FRONTEND_LINKS_AUTO_GENERATED */';

        if (strpos($content, $marker) === false) {
            return [
                'success' => false,
                'message' => yourls__('The index.php file has been manually modified. Deletion cancelled for safety.', 'frontend-links')
            ];
        }

        if (!unlink($filePath)) {
            return [
                'success' => false,
                'message' => yourls__('Unable to delete the file. Check permissions.', 'frontend-links')
            ];
        }
    }

    yourls_update_option('fl_homepage_file_path', '');

    // Delete the YOURLS subdirectory security index.php
    $yourlsIndexPath = yourls_get_option('fl_yourls_root_index_path', '');
    if (!empty($yourlsIndexPath) && file_exists($yourlsIndexPath)) {
        $existing = file_get_contents($yourlsIndexPath);
        if (strpos($existing, '/* FRONTEND_LINKS_YOURLS_REDIRECT */') !== false) {
            unlink($yourlsIndexPath);
        }
    }
    yourls_update_option('fl_yourls_root_index_path', '');

    // Remove .htaccess rules
    fl_delete_root_htaccess();

    return ['success' => true, 'message' => yourls__('index.php file deleted.', 'frontend-links')];
}

/**
 * Remove the Frontend Links rewrite block from the root .htaccess.
 * If the file only contains our block, delete it entirely.
 */
function fl_delete_root_htaccess(): void {
    $htaccessPath = yourls_get_option('fl_htaccess_file_path', '');
    if (empty($htaccessPath) || !file_exists($htaccessPath)) return;

    $content = file_get_contents($htaccessPath);
    $marker = '# BEGIN Frontend Links';
    $markerEnd = '# END Frontend Links';

    if (strpos($content, $marker) === false) return;

    // Remove our block
    $pattern = '/' . preg_quote($marker, '/') . '.*?' . preg_quote($markerEnd, '/') . '\n?/s';
    $cleaned = preg_replace($pattern, '', $content);
    $cleaned = trim($cleaned);

    if ($cleaned === '') {
        // File only contained our rules, delete it
        unlink($htaccessPath);
    } else {
        file_put_contents($htaccessPath, $cleaned . "\n");
    }

    yourls_update_option('fl_htaccess_file_path', '');
}

// ─── Settings ───────────────────────────────────────────────

function fl_get_settings(): array {
    $db = yourls_get_db();
    $table = fl_table('settings');
    $stmt = $db->query("SELECT setting_key, setting_value FROM `$table`");
    $settings = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

function fl_get_setting(string $key, string $default = ''): string {
    $db = yourls_get_db();
    $table = fl_table('settings');
    $stmt = $db->prepare("SELECT setting_value FROM `$table` WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['setting_value'] : $default;
}

function fl_update_setting(string $key, string $value): bool {
    $db = yourls_get_db();
    $table = fl_table('settings');
    $stmt = $db->prepare("INSERT INTO `$table` (setting_key, setting_value) VALUES (?, ?)
                           ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    return $stmt->execute([$key, $value]);
}

// ─── Sections ───────────────────────────────────────────────

function fl_get_sections(bool $activeOnly = true): array {
    $db = yourls_get_db();
    $table = fl_table('sections');
    $sql = "SELECT * FROM `$table`";
    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY sort_order ASC";
    $stmt = $db->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fl_get_section(int $id): ?array {
    $db = yourls_get_db();
    $table = fl_table('sections');
    $stmt = $db->prepare("SELECT * FROM `$table` WHERE id = ?");
    $stmt->execute([$id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: null;
}

function fl_create_section(array $data): int|false {
    $db = yourls_get_db();
    $table = fl_table('sections');
    $stmt = $db->prepare("INSERT INTO `$table` (section_key, title, sort_order, is_active)
                           VALUES (?, ?, ?, ?)");
    $success = $stmt->execute([
        $data['section_key'],
        $data['title'],
        $data['sort_order'] ?? 0,
        $data['is_active'] ?? 1
    ]);
    return $success ? (int)$db->lastInsertId() : false;
}

function fl_update_section(int $id, array $data): bool {
    $db = yourls_get_db();
    $table = fl_table('sections');
    $fields = [];
    $values = [];

    foreach (['section_key', 'title', 'sort_order', 'is_active'] as $field) {
        if (isset($data[$field])) {
            $fields[] = "`$field` = ?";
            $values[] = $data[$field];
        }
    }

    if (empty($fields)) return false;

    $values[] = $id;
    $sql = "UPDATE `$table` SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    return $stmt->execute($values);
}

function fl_delete_section(int $id): bool {
    $db = yourls_get_db();
    $table = fl_table('sections');
    $stmt = $db->prepare("DELETE FROM `$table` WHERE id = ?");
    return $stmt->execute([$id]);
}

// ─── Links ──────────────────────────────────────────────────

function fl_get_links(bool $activeOnly = true): array {
    $db = yourls_get_db();
    $links = fl_table('links');
    $sections = fl_table('sections');
    $sql = "SELECT l.*, s.section_key, s.title as section_title
            FROM `$links` l
            JOIN `$sections` s ON l.section_id = s.id";
    if ($activeOnly) {
        $sql .= " WHERE l.is_active = 1 AND s.is_active = 1";
    }
    $sql .= " ORDER BY s.sort_order ASC, l.sort_order ASC";
    $stmt = $db->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fl_get_link(int $id): ?array {
    $db = yourls_get_db();
    $table = fl_table('links');
    $stmt = $db->prepare("SELECT * FROM `$table` WHERE id = ?");
    $stmt->execute([$id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ?: null;
}

function fl_get_links_by_section(int $sectionId, bool $activeOnly = true): array {
    $db = yourls_get_db();
    $table = fl_table('links');
    $sql = "SELECT * FROM `$table` WHERE section_id = ?";
    if ($activeOnly) {
        $sql .= " AND is_active = 1";
    }
    $sql .= " ORDER BY sort_order ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$sectionId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fl_create_link(array $data): int|false {
    $db = yourls_get_db();
    $table = fl_table('links');
    $stmt = $db->prepare("INSERT INTO `$table` (section_id, label, url, icon, sort_order, is_active)
                           VALUES (?, ?, ?, ?, ?, ?)");
    $success = $stmt->execute([
        $data['section_id'],
        $data['label'],
        $data['url'],
        $data['icon'] ?? 'globe',
        $data['sort_order'] ?? 0,
        $data['is_active'] ?? 1
    ]);
    return $success ? (int)$db->lastInsertId() : false;
}

function fl_update_link(int $id, array $data): bool {
    $db = yourls_get_db();
    $table = fl_table('links');
    $fields = [];
    $values = [];

    foreach (['section_id', 'label', 'url', 'icon', 'sort_order', 'is_active'] as $field) {
        if (isset($data[$field])) {
            $fields[] = "`$field` = ?";
            $values[] = $data[$field];
        }
    }

    if (empty($fields)) return false;

    $values[] = $id;
    $sql = "UPDATE `$table` SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    return $stmt->execute($values);
}

function fl_delete_link(int $id): bool {
    $db = yourls_get_db();
    $table = fl_table('links');
    $stmt = $db->prepare("DELETE FROM `$table` WHERE id = ?");
    return $stmt->execute([$id]);
}
