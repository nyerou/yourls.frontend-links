<?php
/**
 * Frontend Links - Admin Page Template
 * ======================================
 *
 * YOURLS admin interface for managing sections, links, profile,
 * icons, and display options.
 *
 * Architecture:
 *   - PHP config is passed to JS via a <script type="application/json"> block
 *   - All JavaScript is in assets/js/admin.js (CSP-compliant)
 *   - All CSS is in assets/css/admin.css (external stylesheet)
 *   - Event handling uses data-action attributes + event delegation
 *   - Forms use data-fl-submit attributes for AJAX submission
 *
 * Data attributes used:
 *   data-action="..."       — Click handler identifier (processed by admin.js)
 *   data-fl-submit="..."    — Form submit handler identifier
 *   data-modal="..."        — Target modal ID for open-modal action
 *   data-panel="..."        — Target panel ID for toggle-panel action
 *   data-id, data-title...  — Row data for section/link editing
 *   data-link='{ JSON }'    — Full link object for editing
 *
 * @see assets/js/admin.js   JavaScript logic
 * @see assets/css/admin.css  Stylesheet
 * @see ajax.php              AJAX endpoint
 */

if (!defined('YOURLS_ABSPATH')) die();

$fl_error = '';
$fl_success = '';
$adminUrl = $_SERVER['REQUEST_URI'];
$pluginAssetsUrl = yourls_plugin_url(FL_PLUGIN_DIR) . '/assets';

// Load Font Awesome + admin CSS
echo '<link rel="stylesheet" href="' . fl_escape($pluginAssetsUrl . '/css/all.min.css') . '">';
echo '<link rel="stylesheet" href="' . fl_escape($pluginAssetsUrl . '/css/admin.css') . '">';

// ─── Table installation ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fl_action']) && $_POST['fl_action'] === 'install_tables') {
    yourls_verify_nonce('frontend_links');
    require_once FL_PLUGIN_DIR . '/includes/install.php';
    fl_install_tables();
    if (!is_dir(FL_UPLOADS_DIR)) mkdir(FL_UPLOADS_DIR, 0755, true);
    if (!is_dir(FL_ICONS_DIR)) mkdir(FL_ICONS_DIR, 0755, true);
    $fl_success = yourls__('Installation complete! Tables and default data have been created.', 'frontend-links');
}

// ─── Check if tables exist ─────────────────────────────────
if (!fl_tables_exist()) {
    $installNonce = yourls_create_nonce('frontend_links');
    ?>
    <?php if ($fl_error): ?>
    <div class="notice notice-warning"><?= fl_escape($fl_error) ?></div>
    <?php endif; ?>

    <h2><?php yourls_e('Frontend Links - Installation required', 'frontend-links'); ?></h2>
    <p><?php yourls_e('The plugin tables have not yet been created in the database.', 'frontend-links'); ?></p>

    <!-- Config for install page -->
    <script type="application/json" id="fl-config"><?= json_encode([
        'ajaxUrl' => FL_AJAX_URL,
        'nonce' => $installNonce,
        'i18n' => [
            'confirmInstall' => yourls__('Create the Frontend Links plugin tables?', 'frontend-links'),
        ]
    ]) ?></script>

    <form method="POST" action="<?= fl_escape($adminUrl) ?>" data-fl-submit="install">
        <input type="hidden" name="fl_action" value="install_tables">
        <input type="hidden" name="nonce" value="<?= $installNonce ?>">
        <p><input type="submit" class="button button-primary" value="<?= fl_escape(yourls__('Install plugin', 'frontend-links')) ?>"></p>
    </form>

    <script src="<?= fl_escape($pluginAssetsUrl) ?>/js/admin.js"></script>
    <?php
    return;
}

// ─── Data retrieval ────────────────────────────────────────
$settings = fl_get_settings();
$sections = fl_get_sections(false);
$links = fl_get_links(false);
$availableIcons = fl_get_available_icons();
$customIcons = fl_get_custom_icons();
$displayMode = yourls_get_option('fl_display_mode', 'manual');
$shorturlIncludePath = yourls_get_option('fl_shorturl_include_path', '0');
$disableRedirectPage = yourls_get_option('fl_disable_redirect_page', '0');
$disable404Page = yourls_get_option('fl_disable_404_page', '0');
$nonce = yourls_create_nonce('frontend_links');
$currentAvatar = $settings['profile_avatar'] ?? '';
$previousAvatarFile = fl_find_avatar_file('fl_avatars_previous');
$previousAvatarUrl = $previousAvatarFile ? FL_UPLOADS_URL . '/' . basename($previousAvatarFile) : '';
$yourlsBasePath = fl_get_yourls_base_path();

$linksBySection = [];
foreach ($links as $link) {
    $linksBySection[$link['section_id']][] = $link;
}

$parsed = parse_url(YOURLS_SITE);
$exDomain = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? 'example.com');
$exPath = rtrim($parsed['path'] ?? '', '/');
?>

<!-- Config passed to external JS as JSON (CSP-safe) -->
<script type="application/json" id="fl-config"><?= json_encode([
    'ajaxUrl' => FL_AJAX_URL,
    'nonce' => $nonce,
    'i18n' => [
        'confirmDeleteLink' => yourls__('Delete this link?', 'frontend-links'),
        'confirmDeleteSection' => yourls__('Delete this section and all its links?', 'frontend-links'),
        'confirmDeleteIcon' => yourls__('Delete this icon?', 'frontend-links'),
        'confirmDeleteAvatar' => yourls__('Delete the current avatar?', 'frontend-links'),
        'confirmRestoreAvatar' => yourls__('Restore the previous avatar?', 'frontend-links'),
        'connectionError' => yourls__('Connection error.', 'frontend-links'),
        'noLinksInSection' => yourls__('No links in this section.', 'frontend-links'),
        'noCustomIcons' => yourls__('No custom icons.', 'frontend-links'),
        'noSections' => yourls__('No sections.', 'frontend-links'),
        'yes' => yourls__('Yes', 'frontend-links'),
        'no' => yourls__('No', 'frontend-links'),
        'edit' => yourls__('Edit', 'frontend-links'),
        'delete_' => yourls__('Delete', 'frontend-links'),
        'thIcon' => yourls__('Icon', 'frontend-links'),
        'thLabel' => yourls__('Label', 'frontend-links'),
        'thUrl' => yourls__('URL', 'frontend-links'),
        'thOrder' => yourls__('Order', 'frontend-links'),
        'thActive' => yourls__('Active', 'frontend-links'),
        'thActions' => yourls__('Actions', 'frontend-links'),
        'thTitle' => yourls__('Title', 'frontend-links'),
        'thLinks' => yourls__('Links', 'frontend-links'),
        'thPreview' => yourls__('Preview', 'frontend-links'),
        'thName' => yourls__('Name', 'frontend-links'),
        'thType' => yourls__('Type', 'frontend-links'),
        'image' => yourls__('Image', 'frontend-links'),
    ]
]) ?></script>

<!-- Toast -->
<div id="fl-toast" class="fl-toast"></div>

<!-- Server notifications -->
<?php if ($fl_success): ?>
<div class="notice"><?= fl_escape($fl_success) ?></div>
<?php endif; ?>
<?php if ($fl_error): ?>
<div class="notice notice-warning"><?= fl_escape($fl_error) ?></div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════
     SECTIONS & LINKS
     ═══════════════════════════════════════════════════════════ -->
<h2><?php yourls_e('Sections', 'frontend-links'); ?></h2>

<?php if (!empty($sections)): ?>
<table class="tblUrl" id="fl-sections-table">
    <thead>
        <tr><th><?php yourls_e('Title', 'frontend-links'); ?></th><th><?php yourls_e('Links', 'frontend-links'); ?></th><th><?php yourls_e('Order', 'frontend-links'); ?></th><th><?php yourls_e('Active', 'frontend-links'); ?></th><th><?php yourls_e('Actions', 'frontend-links'); ?></th></tr>
    </thead>
    <tbody>
        <?php foreach ($sections as $section): ?>
        <tr data-id="<?= $section['id'] ?>" data-title="<?= fl_escape($section['title']) ?>" data-order="<?= $section['sort_order'] ?>" data-active="<?= $section['is_active'] ?>">
            <td><strong class="fl-section-title"><?= fl_escape($section['title']) ?></strong></td>
            <td class="fl-section-count"><?= count($linksBySection[$section['id']] ?? []) ?></td>
            <td class="fl-section-order"><?= $section['sort_order'] ?></td>
            <td class="fl-section-active"><?= $section['is_active'] ? yourls__('Yes', 'frontend-links') : '<em>' . yourls__('No', 'frontend-links') . '</em>' ?></td>
            <td class="fl-actions">
                <a href="#" data-action="edit-section"><?php yourls_e('Edit', 'frontend-links'); ?></a>
                &nbsp;|&nbsp;
                <a href="#" data-action="delete-section" style="color:#a00;"><?php yourls_e('Delete', 'frontend-links'); ?></a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p id="fl-no-sections"><em><?php yourls_e('No sections.', 'frontend-links'); ?></em></p>
<?php endif; ?>

<p><a href="#" class="button" data-action="open-modal" data-modal="flAddSectionModal"><?php yourls_e('+ Add a section', 'frontend-links'); ?></a></p>

<hr>

<h2><?php yourls_e('Links', 'frontend-links'); ?></h2>

<?php if (!empty($sections)): ?>
<p style="margin-bottom:12px;"><a href="#" class="button" data-action="open-modal" data-modal="flAddLinkModal"><?php yourls_e('+ Add a link', 'frontend-links'); ?></a></p>
<?php endif; ?>

<?php foreach ($sections as $section):
    $sectionLinks = $linksBySection[$section['id']] ?? [];
?>
<div id="fl-link-section-<?= $section['id'] ?>" class="fl-link-section" data-section-id="<?= $section['id'] ?>">
    <h3 class="fl-section-heading"><?= fl_escape($section['title']) ?></h3>
    <?php if (empty($sectionLinks)): ?>
    <p class="fl-empty-msg"><em><?php yourls_e('No links in this section.', 'frontend-links'); ?></em></p>
    <?php else: ?>
    <table class="tblUrl fl-links-table">
        <thead>
            <tr><th><?php yourls_e('Icon', 'frontend-links'); ?></th><th><?php yourls_e('Label', 'frontend-links'); ?></th><th><?php yourls_e('URL', 'frontend-links'); ?></th><th><?php yourls_e('Order', 'frontend-links'); ?></th><th><?php yourls_e('Active', 'frontend-links'); ?></th><th><?php yourls_e('Actions', 'frontend-links'); ?></th></tr>
        </thead>
        <tbody>
            <?php foreach ($sectionLinks as $link):
                $linkDisplay = $link;
                $linkDisplay['url'] = fl_strip_base_path($link['url']);
            ?>
            <tr data-id="<?= $link['id'] ?>" data-link="<?= fl_escape(json_encode($linkDisplay)) ?>">
                <td style="text-align:center;"><?= fl_get_icon($link['icon'], 16) ?></td>
                <td><strong class="fl-link-label"><?= fl_escape($linkDisplay['label']) ?></strong></td>
                <td class="fl-link-url"><a href="<?= fl_escape($linkDisplay['url']) ?>" target="_blank" style="font-size:12px;"><?= fl_escape($linkDisplay['url']) ?></a></td>
                <td class="fl-link-order"><?= $link['sort_order'] ?></td>
                <td class="fl-link-active"><?= $link['is_active'] ? yourls__('Yes', 'frontend-links') : '<em>' . yourls__('No', 'frontend-links') . '</em>' ?></td>
                <td class="fl-actions">
                    <a href="#" data-action="edit-link"><?php yourls_e('Edit', 'frontend-links'); ?></a>
                    &nbsp;|&nbsp;
                    <a href="#" data-action="delete-link" data-section-id="<?= $link['section_id'] ?>" style="color:#a00;"><?php yourls_e('Delete', 'frontend-links'); ?></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<hr>

<!-- ═══════════════════════════════════════════════════════════
     PROFILE (collapsible)
     ═══════════════════════════════════════════════════════════ -->
<h2 class="fl-panel-toggle" data-action="toggle-panel" data-panel="fl-panel-profile">
    <span class="fl-arrow">&#9654;</span> <?php yourls_e('Profile', 'frontend-links'); ?>
</h2>
<div id="fl-panel-profile" style="display:none;">
    <form id="fl-form-profile" data-fl-submit="profile" enctype="multipart/form-data">
        <input type="hidden" name="fl_action" value="update_profile">
        <input type="hidden" name="nonce" value="<?= $nonce ?>">

        <table class="form-table">
            <tr>
                <th><label for="fl_profile_name"><?php yourls_e('Name', 'frontend-links'); ?></label></th>
                <td><input type="text" id="fl_profile_name" name="profile_name" value="<?= fl_escape($settings['profile_name'] ?? '') ?>" size="40" required></td>
            </tr>
            <tr>
                <th><label for="fl_profile_bio"><?php yourls_e('Bio', 'frontend-links'); ?></label></th>
                <td><input type="text" id="fl_profile_bio" name="profile_bio" value="<?= fl_escape($settings['profile_bio'] ?? '') ?>" size="60"></td>
            </tr>
            <tr>
                <th><?php yourls_e('Avatar', 'frontend-links'); ?></th>
                <td>
                    <div id="fl-avatar-current" style="<?= $currentAvatar ? '' : 'display:none;' ?>margin-bottom:8px;">
                        <img id="fl-avatar-current-img" src="<?= fl_escape($currentAvatar) ?>" alt="<?= fl_escape(yourls__('Current avatar', 'frontend-links')) ?>" style="max-width:80px;max-height:80px;border-radius:50%;vertical-align:middle;border:2px solid #ddd;">
                        <span style="color:#666;font-size:11px;margin-left:8px;">(<?php yourls_e('current', 'frontend-links'); ?>)</span>
                    </div>
                    <div id="fl-avatar-previous" style="<?= $previousAvatarUrl ? '' : 'display:none;' ?>margin-bottom:8px;">
                        <img id="fl-avatar-previous-img" src="<?= fl_escape($previousAvatarUrl) ?>" alt="<?= fl_escape(yourls__('Previous avatar', 'frontend-links')) ?>" style="max-width:50px;max-height:50px;border-radius:50%;vertical-align:middle;border:2px dashed #ccc;opacity:.7;">
                        <span style="color:#666;font-size:11px;margin-left:8px;">(<?php yourls_e('previous', 'frontend-links'); ?>)</span>
                    </div>
                    <p>
                        <label><strong><?php yourls_e('Upload an image:', 'frontend-links'); ?></strong></label><br>
                        <input type="file" name="avatar_file" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml">
                        <br><span style="color:#666;font-size:11px;">JPG, PNG, GIF, WebP, SVG &mdash; <?php yourls_e('max 2 MB.', 'frontend-links'); ?></span>
                    </p>
                    <p style="margin-top:6px;">
                        <label><strong><?php yourls_e('Or external URL:', 'frontend-links'); ?></strong></label><br>
                        <input type="url" name="profile_avatar" value="<?= fl_escape($currentAvatar) ?>" size="50" placeholder="https://...">
                    </p>
                    <p style="margin-top:6px;">
                        <button type="button" id="fl-btn-delete-avatar" class="button" data-action="delete-avatar" style="<?= $currentAvatar ? '' : 'display:none;' ?>"><?php yourls_e('Delete avatar', 'frontend-links'); ?></button>
                        <button type="button" id="fl-btn-restore-avatar" class="button" data-action="restore-avatar" style="<?= $previousAvatarUrl ? '' : 'display:none;' ?>"><?php yourls_e('Restore previous', 'frontend-links'); ?></button>
                    </p>
                </td>
            </tr>
            <tr>
                <th><label for="fl_meta_title"><?php yourls_e('SEO Title', 'frontend-links'); ?></label></th>
                <td><input type="text" id="fl_meta_title" name="meta_title" value="<?= fl_escape($settings['meta_title'] ?? '') ?>" size="50"></td>
            </tr>
            <tr>
                <th><label for="fl_meta_desc"><?php yourls_e('SEO Description', 'frontend-links'); ?></label></th>
                <td><input type="text" id="fl_meta_desc" name="meta_description" value="<?= fl_escape($settings['meta_description'] ?? '') ?>" size="60"></td>
            </tr>
        </table>

        <p><input type="submit" class="button button-primary" value="<?= fl_escape(yourls__('Save profile', 'frontend-links')) ?>"></p>
    </form>
</div>

<hr>

<!-- ═══════════════════════════════════════════════════════════
     ICONS (collapsible, closed by default)
     ═══════════════════════════════════════════════════════════ -->
<h2 class="fl-panel-toggle" data-action="toggle-panel" data-panel="fl-panel-icons">
    <span class="fl-arrow">&#9654;</span> <?php yourls_e('Icons', 'frontend-links'); ?>
</h2>
<div id="fl-panel-icons" style="display:none;">

    <h3><?php yourls_e('Built-in icons (Font Awesome)', 'frontend-links'); ?></h3>
    <p style="margin-bottom:8px;color:#666;font-size:12px;"><?php yourls_e('Available by default. Cannot be deleted.', 'frontend-links'); ?></p>
    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;">
        <?php foreach ($availableIcons as $key => $label):
            if (str_ends_with($label, "\xE2\x9C\xA6")) continue; // skip custom
        ?>
        <span style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;background:#f5f5f5;border:1px solid #ddd;border-radius:4px;font-size:11px;" title="<?= fl_escape($key) ?>">
            <?= fl_get_icon($key, 14) ?>
            <?= fl_escape($key) ?>
        </span>
        <?php endforeach; ?>
    </div>

    <h3><?php yourls_e('Custom icons', 'frontend-links'); ?></h3>
    <?php if (empty($customIcons)): ?>
    <p id="fl-no-custom-icons"><em><?php yourls_e('No custom icons.', 'frontend-links'); ?></em></p>
    <?php else: ?>
    <table class="tblUrl" id="fl-custom-icons-table">
        <thead>
            <tr><th><?php yourls_e('Preview', 'frontend-links'); ?></th><th><?php yourls_e('Name', 'frontend-links'); ?></th><th><?php yourls_e('Type', 'frontend-links'); ?></th><th><?php yourls_e('Actions', 'frontend-links'); ?></th></tr>
        </thead>
        <tbody>
            <?php foreach ($customIcons as $ci): ?>
            <tr data-id="<?= $ci['id'] ?>">
                <td style="text-align:center;"><?= fl_get_icon($ci['name'], 20) ?></td>
                <td><strong><?= fl_escape($ci['name']) ?></strong></td>
                <td><?= $ci['type'] === 'svg' ? 'SVG' : yourls__('Image', 'frontend-links') ?></td>
                <td class="fl-actions">
                    <a href="#" data-action="delete-icon" style="color:#a00;"><?php yourls_e('Delete', 'frontend-links'); ?></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <p style="margin-top:12px;">
        <a href="#" class="button" data-action="open-modal" data-modal="flAddIconModal"><?php yourls_e('+ Add an icon', 'frontend-links'); ?></a>
    </p>
</div>

<hr>

<!-- ═══════════════════════════════════════════════════════════
     OPTIONS (collapsible)
     ═══════════════════════════════════════════════════════════ -->
<h2 class="fl-panel-toggle" data-action="toggle-panel" data-panel="fl-panel-options">
    <span class="fl-arrow">&#9654;</span> <?php yourls_e('Options', 'frontend-links'); ?>
</h2>
<div id="fl-panel-options" style="display:none;">

    <h3><?php yourls_e('Display mode', 'frontend-links'); ?></h3>
    <form id="fl-form-display-mode" data-fl-submit="ajax">
        <input type="hidden" name="fl_action" value="update_display_mode">
        <input type="hidden" name="nonce" value="<?= $nonce ?>">
        <table class="form-table">
            <tr>
                <td>
                    <p>
                        <label><input type="radio" name="display_mode" value="manual" <?= $displayMode !== 'auto' ? 'checked' : '' ?>>
                        <strong><?php yourls_e('Manual', 'frontend-links'); ?></strong> &mdash; <?php yourls_e('Include <code>fl_render_page()</code> in an external PHP file.', 'frontend-links'); ?></label>
                    </p>
                    <?php if ($displayMode !== 'auto'): ?>
                    <pre style="background:#f5f5f5;padding:10px;margin:8px 0 0 24px;border:1px solid #ddd;font-size:12px;overflow-x:auto;">&lt;?php
require_once __DIR__ . '<?= fl_escape($yourlsBasePath) ?>/includes/load-yourls.php';
fl_render_page();</pre>
                    <?php endif; ?>
                    <p style="margin-top:12px;">
                        <label><input type="radio" name="display_mode" value="auto" <?= $displayMode === 'auto' ? 'checked' : '' ?>>
                        <strong><?php yourls_e('Automatic', 'frontend-links'); ?></strong> &mdash; <?php yourls_e('The plugin serves the page at <code>/</code>.', 'frontend-links'); ?></label>
                    </p>
                </td>
            </tr>
        </table>
        <p><input type="submit" class="button button-primary" value="<?= fl_escape(yourls__('Save mode', 'frontend-links')) ?>"></p>
    </form>

    <h3 style="margin-top:20px;"><?php yourls_e('Short links', 'frontend-links'); ?></h3>
    <p><?php yourls_e('When a link is entered without a protocol (e.g.: <code>git</code>), the YOURLS domain is added automatically.', 'frontend-links'); ?></p>
    <form id="fl-form-shorturl" data-fl-submit="ajax">
        <input type="hidden" name="fl_action" value="update_shorturl_option">
        <input type="hidden" name="nonce" value="<?= $nonce ?>">
        <table class="form-table">
            <tr>
                <th><label><?php yourls_e('Include subdirectory', 'frontend-links'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="shorturl_include_path" <?= $shorturlIncludePath === '1' ? 'checked' : '' ?>>
                        <?= sprintf(yourls__('Include <code>%s</code> in URLs', 'frontend-links'), fl_escape($exPath ?: '/')) ?>
                    </label>
                    <?php if ($exPath): ?>
                    <br><span style="color:#666;font-size:11px;">
                        <code>git</code> : <?php yourls_e('unchecked', 'frontend-links'); ?> &rarr; <code><?= fl_escape($exDomain) ?>/git</code>
                        &nbsp;|&nbsp;
                        <?php yourls_e('checked', 'frontend-links'); ?> &rarr; <code><?= fl_escape($exDomain . $exPath) ?>/git</code>
                    </span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <p><input type="submit" class="button button-primary" value="<?= fl_escape(yourls__('Save', 'frontend-links')) ?>"></p>
    </form>

    <h3 style="margin-top:20px;"><?php yourls_e('Features', 'frontend-links'); ?></h3>
    <form id="fl-form-feature-toggles" data-fl-submit="ajax">
        <input type="hidden" name="fl_action" value="update_feature_toggles">
        <input type="hidden" name="nonce" value="<?= $nonce ?>">
        <table class="form-table">
            <tr>
                <th><label><?php yourls_e('Redirect page', 'frontend-links'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="disable_redirect_page" <?= $disableRedirectPage === '1' ? 'checked' : '' ?>>
                        <?php yourls_e('Disable branded redirect page', 'frontend-links'); ?>
                    </label>
                    <br><span style="color:#666;font-size:11px;">
                        <?php yourls_e('When checked, short URL clicks use a direct HTTP redirect instead of the branded interstitial page.', 'frontend-links'); ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th><label><?php yourls_e('404 page', 'frontend-links'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" name="disable_404_page" <?= $disable404Page === '1' ? 'checked' : '' ?>>
                        <?php yourls_e('Disable branded 404 page', 'frontend-links'); ?>
                    </label>
                    <br><span style="color:#666;font-size:11px;">
                        <?php yourls_e('When checked, unknown URLs are handled by the server or another plugin instead of the branded 404 page.', 'frontend-links'); ?>
                    </span>
                </td>
            </tr>
        </table>
        <p><input type="submit" class="button button-primary" value="<?= fl_escape(yourls__('Save', 'frontend-links')) ?>"></p>
    </form>
</div>

<hr>

<!-- ═══════════════════════════════════════════════════════════
     INFORMATION (collapsible)
     ═══════════════════════════════════════════════════════════ -->
<h2 class="fl-panel-toggle" data-action="toggle-panel" data-panel="fl-panel-info">
    <span class="fl-arrow">&#9654;</span> <?php yourls_e('Information', 'frontend-links'); ?>
</h2>
<div id="fl-panel-info" style="display:none;">
    <table class="form-table">
        <tr><th><?php yourls_e('YOURLS Site', 'frontend-links'); ?></th><td><code><?= fl_escape(YOURLS_SITE) ?></code></td></tr>
        <tr><th><?php yourls_e('Detected path', 'frontend-links'); ?></th><td><code><?= fl_escape($yourlsBasePath ?: yourls__('(root)', 'frontend-links')) ?></code></td></tr>
        <tr><th><?php yourls_e('Uploads folder', 'frontend-links'); ?></th><td><code>user/plugins/<?= fl_escape(FL_PLUGIN_SLUG) ?>/uploads/</code></td></tr>
        <tr><th><?php yourls_e('Current mode', 'frontend-links'); ?></th><td><strong><?= $displayMode === 'auto' ? yourls__('Automatic (/)', 'frontend-links') : yourls__('Manual', 'frontend-links') ?></strong></td></tr>
        <?php $homepageFilePath = yourls_get_option('fl_homepage_file_path', ''); ?>
        <?php if ($homepageFilePath): ?>
        <tr><th><?php yourls_e('Auto index.php file', 'frontend-links'); ?></th><td><code><?= fl_escape($homepageFilePath) ?></code></td></tr>
        <?php endif; ?>
    </table>
</div>

<!-- ═══════════════════════════════════════════════════════════
     MODALS
     ═══════════════════════════════════════════════════════════ -->

<!-- Modal: Add section -->
<div id="flAddSectionModal" class="fl-modal-overlay">
    <div class="fl-modal-box">
        <h3><?php yourls_e('Add a section', 'frontend-links'); ?></h3>
        <form data-fl-submit="add-section">
            <input type="hidden" name="fl_action" value="add_section">
            <input type="hidden" name="nonce" value="<?= $nonce ?>">
            <table class="form-table">
                <tr><th><label><?php yourls_e('Title', 'frontend-links'); ?></label></th><td><input type="text" name="title" size="30" required placeholder="<?= fl_escape(yourls__('Section name', 'frontend-links')) ?>"></td></tr>
                <tr><th><label><?php yourls_e('Order', 'frontend-links'); ?></label></th><td><input type="number" name="sort_order" value="0" min="0" size="5"></td></tr>
                <tr><th><label><?php yourls_e('Active', 'frontend-links'); ?></label></th><td><input type="checkbox" name="is_active" checked></td></tr>
            </table>
            <p>
                <input type="submit" class="button button-primary" value="<?= fl_escape(yourls__('Add', 'frontend-links')) ?>">
                <a href="#" class="button" data-action="close-modals"><?php yourls_e('Cancel', 'frontend-links'); ?></a>
            </p>
        </form>
    </div>
</div>

<!-- Modal: Edit section -->
<div id="flEditSectionModal" class="fl-modal-overlay">
    <div class="fl-modal-box">
        <h3><?php yourls_e('Edit section', 'frontend-links'); ?></h3>
        <form data-fl-submit="edit-section">
            <input type="hidden" name="fl_action" value="edit_section">
            <input type="hidden" name="section_id" id="fl_edit_section_id">
            <input type="hidden" name="nonce" value="<?= $nonce ?>">
            <table class="form-table">
                <tr><th><label><?php yourls_e('Title', 'frontend-links'); ?></label></th><td><input type="text" name="title" id="fl_edit_section_title" size="30" required></td></tr>
                <tr><th><label><?php yourls_e('Order', 'frontend-links'); ?></label></th><td><input type="number" name="sort_order" id="fl_edit_section_order" min="0" size="5"></td></tr>
                <tr><th><label><?php yourls_e('Active', 'frontend-links'); ?></label></th><td><input type="checkbox" name="is_active" id="fl_edit_section_active"></td></tr>
            </table>
            <p>
                <input type="submit" class="button button-primary" value="<?= fl_escape(yourls__('Save', 'frontend-links')) ?>">
                <a href="#" class="button" data-action="close-modals"><?php yourls_e('Cancel', 'frontend-links'); ?></a>
            </p>
        </form>
    </div>
</div>

<!-- Modal: Add link -->
<div id="flAddLinkModal" class="fl-modal-overlay">
    <div class="fl-modal-box">
        <h3><?php yourls_e('Add a link', 'frontend-links'); ?></h3>
        <form data-fl-submit="add-link">
            <input type="hidden" name="fl_action" value="add_link">
            <input type="hidden" name="nonce" value="<?= $nonce ?>">
            <table class="form-table">
                <tr><th><label><?php yourls_e('Label', 'frontend-links'); ?></label></th><td><input type="text" name="label" size="30" required placeholder="<?= fl_escape(yourls__('My link', 'frontend-links')) ?>"></td></tr>
                <tr><th><label><?php yourls_e('URL', 'frontend-links'); ?></label></th><td>
                    <input type="text" name="url" size="50" required placeholder="https://...">
                    <br><span style="color:#666;font-size:11px;"><?php yourls_e('Without a protocol, the YOURLS domain will be added.', 'frontend-links'); ?></span>
                </td></tr>
                <tr><th><label><?php yourls_e('Section', 'frontend-links'); ?></label></th><td>
                    <select name="section_id" id="fl_add_link_section">
                        <?php foreach ($sections as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= fl_escape($s['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td></tr>
                <tr><th><label><?php yourls_e('Icon', 'frontend-links'); ?></label></th><td>
                    <select name="icon">
                        <?php foreach ($availableIcons as $key => $label): ?>
                        <option value="<?= $key ?>"><?= fl_escape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td></tr>
                <tr><th><label><?php yourls_e('Order', 'frontend-links'); ?></label></th><td><input type="number" name="sort_order" value="0" min="0" size="5"></td></tr>
                <tr><th><label><?php yourls_e('Active', 'frontend-links'); ?></label></th><td><input type="checkbox" name="is_active" checked></td></tr>
            </table>
            <p>
                <input type="submit" class="button button-primary" value="<?= fl_escape(yourls__('Add', 'frontend-links')) ?>">
                <a href="#" class="button" data-action="close-modals"><?php yourls_e('Cancel', 'frontend-links'); ?></a>
            </p>
        </form>
    </div>
</div>

<!-- Modal: Edit link -->
<div id="flEditLinkModal" class="fl-modal-overlay">
    <div class="fl-modal-box">
        <h3><?php yourls_e('Edit link', 'frontend-links'); ?></h3>
        <form data-fl-submit="edit-link">
            <input type="hidden" name="fl_action" value="edit_link">
            <input type="hidden" name="link_id" id="fl_edit_link_id">
            <input type="hidden" name="nonce" value="<?= $nonce ?>">
            <table class="form-table">
                <tr><th><label><?php yourls_e('Label', 'frontend-links'); ?></label></th><td><input type="text" name="label" id="fl_edit_link_label" size="30" required></td></tr>
                <tr><th><label><?php yourls_e('URL', 'frontend-links'); ?></label></th><td>
                    <input type="text" name="url" id="fl_edit_link_url" size="50" required>
                    <br><span style="color:#666;font-size:11px;"><?php yourls_e('Without a protocol, the YOURLS domain will be added.', 'frontend-links'); ?></span>
                </td></tr>
                <tr><th><label><?php yourls_e('Section', 'frontend-links'); ?></label></th><td>
                    <select name="section_id" id="fl_edit_link_section">
                        <?php foreach ($sections as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= fl_escape($s['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td></tr>
                <tr><th><label><?php yourls_e('Icon', 'frontend-links'); ?></label></th><td>
                    <select name="icon" id="fl_edit_link_icon">
                        <?php foreach ($availableIcons as $key => $label): ?>
                        <option value="<?= $key ?>"><?= fl_escape($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </td></tr>
                <tr><th><label><?php yourls_e('Order', 'frontend-links'); ?></label></th><td><input type="number" name="sort_order" id="fl_edit_link_order" min="0" size="5"></td></tr>
                <tr><th><label><?php yourls_e('Active', 'frontend-links'); ?></label></th><td><input type="checkbox" name="is_active" id="fl_edit_link_active"></td></tr>
            </table>
            <p>
                <input type="submit" class="button button-primary" value="<?= fl_escape(yourls__('Save', 'frontend-links')) ?>">
                <a href="#" class="button" data-action="close-modals"><?php yourls_e('Cancel', 'frontend-links'); ?></a>
            </p>
        </form>
    </div>
</div>

<!-- Modal: Add custom icon -->
<div id="flAddIconModal" class="fl-modal-overlay">
    <div class="fl-modal-box">
        <h3><?php yourls_e('Add a custom icon', 'frontend-links'); ?></h3>
        <form data-fl-submit="add-icon" enctype="multipart/form-data">
            <input type="hidden" name="fl_action" value="add_custom_icon">
            <input type="hidden" name="nonce" value="<?= $nonce ?>">
            <table class="form-table">
                <tr><th><label><?php yourls_e('Name', 'frontend-links'); ?></label></th><td>
                    <input type="text" name="icon_name" size="25" required placeholder="my_icon" pattern="[a-z0-9_-]+">
                    <br><span style="color:#666;font-size:11px;"><?php yourls_e('Lowercase letters, numbers, _ and -. This name will be used to select the icon.', 'frontend-links'); ?></span>
                </td></tr>
                <tr><th><label><?php yourls_e('Type', 'frontend-links'); ?></label></th><td>
                    <label style="margin-right:16px;"><input type="radio" name="icon_type" value="svg" checked> <?php yourls_e('SVG code', 'frontend-links'); ?></label>
                    <label><input type="radio" name="icon_type" value="image"> <?php yourls_e('Image', 'frontend-links'); ?></label>
                </td></tr>
                <tr id="flIconSvgRow"><th><label><?php yourls_e('SVG code', 'frontend-links'); ?></label></th><td>
                    <textarea name="icon_svg" rows="5" cols="50" placeholder='<svg xmlns="..." ...>...</svg>' style="font-family:monospace;font-size:11px;"></textarea>
                    <br><span style="color:#666;font-size:11px;"><?= sprintf(yourls__('Use %s for theme adaptation.', 'frontend-links'), '<code>stroke="currentColor"</code>') ?></span>
                </td></tr>
                <tr id="flIconFileRow" style="display:none;"><th><label><?php yourls_e('Image file', 'frontend-links'); ?></label></th><td>
                    <input type="file" name="icon_file" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml">
                    <br><span style="color:#666;font-size:11px;">JPG, PNG, GIF, WebP, SVG &mdash; <?php yourls_e('max 1 MB.', 'frontend-links'); ?></span>
                </td></tr>
            </table>
            <p>
                <input type="submit" class="button button-primary" value="<?= fl_escape(yourls__('Add icon', 'frontend-links')) ?>">
                <a href="#" class="button" data-action="close-modals"><?php yourls_e('Cancel', 'frontend-links'); ?></a>
            </p>
        </form>
    </div>
</div>

<!-- External JS (CSP-compliant, no inline scripts) -->
<script src="<?= fl_escape($pluginAssetsUrl) ?>/js/admin.js"></script>
