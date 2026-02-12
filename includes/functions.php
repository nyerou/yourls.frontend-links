<?php
/**
 * Frontend Links - CRUD functions + Uploads
 * Uses YOURLS DB connection (Aura.SQL / PDO)
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

// ─── Homepage File Management ───────────────────────────────

/**
 * Create an index.php file at the document root
 * to serve the links page in automatic mode.
 * The file contains a marker so it can be properly removed.
 */
function fl_create_homepage_file(): array {
    // Determine the path of the file to create
    $yourlsBasePath = fl_get_yourls_base_path();
    if ($yourlsBasePath !== '') {
        // YOURLS is in a subdirectory: create index.php above
        $docRoot = dirname(YOURLS_ABSPATH);
    } else {
        // YOURLS is at the root: use DOCUMENT_ROOT
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? YOURLS_ABSPATH;
    }

    $filePath = rtrim($docRoot, '/\\') . '/index.php';

    // Calculate relative path to load-yourls.php
    if ($yourlsBasePath !== '') {
        $loadPath = '.' . $yourlsBasePath . '/includes/load-yourls.php';
    } else {
        $loadPath = './includes/load-yourls.php';
    }

    $marker = '/* FRONTEND_LINKS_AUTO_GENERATED */';
    $content = "<?php\n"
        . "$marker\n"
        . "// Auto-generated file by the Frontend Links plugin.\n"
        . "// Do not modify - it will be deleted if you switch to manual mode.\n"
        . "require_once __DIR__ . '/$loadPath';\n"
        . "fl_render_page();\n";

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

    return [
        'success' => true,
        'message' => sprintf(yourls__('index.php file created: %s', 'frontend-links'), $filePath)
    ];
}

/**
 * Delete the index.php file created by fl_create_homepage_file()
 * Only deletes if the file contains the plugin marker.
 */
function fl_delete_homepage_file(): array {
    $filePath = yourls_get_option('fl_homepage_file_path', '');

    if (empty($filePath) || !file_exists($filePath)) {
        return ['success' => true, 'message' => yourls__('No file to delete.', 'frontend-links')];
    }

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

    yourls_update_option('fl_homepage_file_path', '');

    return ['success' => true, 'message' => yourls__('index.php file deleted.', 'frontend-links')];
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
