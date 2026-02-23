<?php
/**
 * Frontend Links - Settings Migration Page
 * ==========================================
 *
 * One-time interactive migration: moves fl_* plugin options from the shared
 * yourls_options table to the dedicated frontend_settings table.
 *
 * Accessed automatically when fl_check_pending_migration() detects legacy
 * options in yourls_options and the current user is in the YOURLS admin.
 *
 * The textdomain 'frontend-links' is loaded via fl_load_textdomain(), which
 * hooks into 'plugins_loaded' — fired during load-yourls.php below.
 * No explicit yourls_load_custom_textdomain() call is needed here.
 *
 * Flow:
 *   GET  → show migration prompt listing detected options
 *   POST migrate_action=yes → run migration, redirect to plugin admin page
 *   POST migrate_action=no  → deactivate the plugin, redirect to plugins page
 *
 * Security:
 *   - Requires YOURLS authentication (yourls_is_valid_user)
 *   - CSRF protection via YOURLS nonce
 *
 * @package FrontendLinks
 */

// Load YOURLS (3 levels up: user/plugins/frontend-links/ → user/plugins/ → user/ → root)
require_once dirname(dirname(dirname(__DIR__))) . '/includes/load-yourls.php';

// ─── Authentication ──────────────────────────────────────────
$auth = yourls_is_valid_user();
if ($auth !== true) {
    yourls_redirect(yourls_admin_url('index.php'));
    die();
}

// ─── Detect legacy options ───────────────────────────────────
$allKeys = [
    'display_mode', 'homepage_file_path', 'disable_redirect_page',
    'htaccess_version', 'robots_txt_path', 'active_theme',
    'shorturl_include_path', 'redirect_https', 'redirect_www',
    'robots_shorturl_index', 'disable_404_page', 'htaccess_file_path',
    'yourls_root_index_path',
];

$foundKeys = [];
foreach ($allKeys as $key) {
    if (yourls_get_option('fl_' . $key) !== false) {
        $foundKeys[] = 'fl_' . $key;
    }
}

// Nothing to migrate → redirect to plugin admin
if (empty($foundKeys)) {
    yourls_redirect(yourls_admin_url('plugins.php?page=frontend_admin'));
    die();
}

// ─── Handle form submission ──────────────────────────────────
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nonce  = $_POST['nonce'] ?? '';
    $action = $_POST['migrate_action'] ?? '';

    if ($nonce !== yourls_create_nonce('fl_migrate')) {
        $error = yourls__('Session expired. Please reload the page and try again.', 'frontend-links');
    } elseif ($action === 'yes') {
        fl_maybe_migrate_options();
        yourls_redirect(yourls_admin_url('plugins.php?page=frontend_admin'));
        die();
    } elseif ($action === 'no') {
        yourls_deactivate_plugin(FL_PLUGIN_SLUG . '/plugin.php');
        yourls_redirect(yourls_admin_url('plugins.php'));
        die();
    }
}

// ─── Template helpers ────────────────────────────────────────
$nonce    = yourls_create_nonce('fl_migrate');
$count    = count($foundKeys);
$e        = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

// JS confirm string: json_encode handles escaping for the JS context,
// htmlspecialchars handles the HTML attribute context.
$confirmAttr = 'return confirm(' . json_encode(yourls__('The plugin will be deactivated and you will need to reactivate it later to use it. Continue?', 'frontend-links')) . ')';

?><!DOCTYPE html>
<html lang="<?= $e(yourls_get_locale()) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Frontend Links — <?php yourls_e('Migration required', 'frontend-links'); ?></title>
<style>
*, *::before, *::after { box-sizing: border-box; }

body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, sans-serif;
    background: #f0f0f1;
    margin: 0;
    padding: 48px 20px 80px;
    color: #1d2327;
    font-size: 14px;
    line-height: 1.6;
}

.wrap { max-width: 640px; margin: 0 auto; }

/* ── Brand header ── */
.brand {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 24px;
    font-size: 17px;
    font-weight: 700;
    color: #1d2327;
}
.brand-badge {
    background: #2271b1;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    padding: 3px 9px;
    border-radius: 3px;
}

/* ── Card ── */
.card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,.07);
    overflow: hidden;
}
.card-head {
    padding: 20px 28px 18px;
    border-bottom: 1px solid #e5e5e5;
    background: #f6f7f7;
}
.card-head h1 { font-size: 17px; margin: 0 0 4px; font-weight: 600; }
.card-head .subtitle { margin: 0; color: #646970; font-size: 13px; }
.card-body { padding: 24px 28px 28px; }

/* ── Notices ── */
.notice {
    border-left: 4px solid #dba617;
    background: #fcf9e8;
    padding: 12px 16px;
    border-radius: 0 3px 3px 0;
    margin-bottom: 22px;
    font-size: 13px;
}
.notice strong { display: block; margin-bottom: 3px; }
.notice-error { border-left-color: #b32d2e; background: #fce8e8; color: #b32d2e; }

/* ── Body copy ── */
h2 {
    font-size: 11px;
    font-weight: 700;
    margin: 20px 0 6px;
    color: #646970;
    text-transform: uppercase;
    letter-spacing: .06em;
}
h2:first-of-type { margin-top: 0; }
p { margin: 0 0 10px; font-size: 13px; }
p:last-of-type { margin-bottom: 0; }
code {
    font-family: ui-monospace, "SF Mono", Consolas, "Courier New", monospace;
    font-size: 12px;
    background: #f0f0f1;
    padding: 1px 5px;
    border-radius: 2px;
}

/* ── Keys grid ── */
.keys-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2px 16px;
    background: #f6f7f7;
    border: 1px solid #dcdcde;
    border-radius: 3px;
    padding: 12px 16px;
    margin: 8px 0 20px;
    list-style: none;
}
.keys-grid li {
    font-family: ui-monospace, "SF Mono", Consolas, "Courier New", monospace;
    font-size: 12px;
    color: #50575e;
    padding: 2px 0;
}
.keys-grid li::before { content: "→ "; color: #aaa; }

/* ── Divider ── */
hr { border: none; border-top: 1px solid #f0f0f1; margin: 24px 0; }

/* ── Actions ── */
.actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
.actions form { margin: 0; }

.btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 18px;
    border-radius: 3px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid transparent;
    line-height: 1;
    transition: background .12s, color .12s, border-color .12s;
    white-space: nowrap;
}
.btn-yes { background: #2271b1; color: #fff; border-color: #2271b1; }
.btn-yes:hover { background: #135e96; border-color: #135e96; }
.btn-no { background: #fff; color: #b32d2e; border-color: #b32d2e; }
.btn-no:hover { background: #b32d2e; color: #fff; }

.actions-note { margin-left: auto; font-size: 12px; color: #a7aaad; font-style: italic; }

@media (max-width: 480px) {
    .keys-grid { grid-template-columns: 1fr; }
    .actions-note { display: none; }
}
</style>
</head>
<body>
<div class="wrap">

    <div class="brand">
        Frontend Links
        <span class="brand-badge"><?php yourls_e('Migration required', 'frontend-links'); ?></span>
    </div>

    <div class="card">

        <div class="card-head">
            <h1><?php yourls_e('Settings structure update', 'frontend-links'); ?></h1>
            <p class="subtitle">
                <?php echo $e(sprintf(yourls__('%d option(s) found in yourls_options — action required to continue.', 'frontend-links'), $count)); ?>
            </p>
        </div>

        <div class="card-body">

            <?php if ($error): ?>
            <div class="notice notice-error">
                <strong><?php yourls_e('Error', 'frontend-links'); ?></strong>
                <?= $e($error) ?>
            </div>
            <?php endif; ?>

            <div class="notice">
                <strong><?php yourls_e('Action required before continuing', 'frontend-links'); ?></strong>
                <?php yourls_e('The plugin settings (<code>fl_*</code>) are currently stored in <code>yourls_options</code>, the global YOURLS table shared with the core and other plugins. The current version requires them to be in <code>frontend_settings</code>, the plugin\'s own private table.', 'frontend-links'); ?>
            </div>

            <h2><?php yourls_e('Why this migration?', 'frontend-links'); ?></h2>
            <p><?php yourls_e('Previous versions stored the plugin\'s configuration in <code>yourls_options</code> — YOURLS\'s global table — alongside core options and other plugins. This worked, but polluted a shared space and required multiple separate queries per page load.', 'frontend-links'); ?></p>
            <p><?php yourls_e('The current version uses <code>frontend_settings</code> exclusively, a dedicated table created by the plugin. This avoids naming conflicts, improves performance (single cached load per request), and keeps the global table clean.', 'frontend-links'); ?></p>
            <p><?php yourls_e('<strong>No data will be lost.</strong> Your current settings will be copied as-is to <code>frontend_settings</code>, then removed from <code>yourls_options</code>.', 'frontend-links'); ?></p>

            <h2><?php echo $e(sprintf(yourls__('Options to move (%d)', 'frontend-links'), $count)); ?></h2>
            <ul class="keys-grid">
                <?php foreach ($foundKeys as $key): ?>
                <li><?= $e($key) ?></li>
                <?php endforeach; ?>
            </ul>

            <hr>

            <div class="actions">
                <form method="POST">
                    <input type="hidden" name="migrate_action" value="yes">
                    <input type="hidden" name="nonce" value="<?= $e($nonce) ?>">
                    <button type="submit" class="btn btn-yes">
                        &#10003;&nbsp;<?php yourls_e('Yes, move the settings', 'frontend-links'); ?>
                    </button>
                </form>

                <form method="POST" onsubmit="<?= $e($confirmAttr) ?>">
                    <input type="hidden" name="migrate_action" value="no">
                    <input type="hidden" name="nonce" value="<?= $e($nonce) ?>">
                    <button type="submit" class="btn btn-no">
                        &#10007;&nbsp;<?php yourls_e('No, deactivate the plugin', 'frontend-links'); ?>
                    </button>
                </form>

                <span class="actions-note"><?php yourls_e('The plugin will be deactivated if you decline.', 'frontend-links'); ?></span>
            </div>

        </div><!-- .card-body -->
    </div><!-- .card -->

</div><!-- .wrap -->
</body>
</html>
