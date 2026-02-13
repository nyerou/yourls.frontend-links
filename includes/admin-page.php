<?php
/**
 * Frontend Links - Admin page
 * Native YOURLS design - AJAX for CRUD operations
 */

if (!defined('YOURLS_ABSPATH')) die();

$fl_error = '';
$fl_success = '';
$adminUrl = $_SERVER['REQUEST_URI'];
$faUrl = yourls_plugin_url(FL_PLUGIN_DIR) . '/assets/css/all.min.css';
echo '<link rel="stylesheet" href="' . fl_escape($faUrl) . '">';

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

    <form method="POST" action="<?= fl_escape($adminUrl) ?>" onsubmit="return confirm('<?= fl_escape(yourls__('Create the Frontend Links plugin tables?', 'frontend-links')) ?>')">
        <input type="hidden" name="fl_action" value="install_tables">
        <input type="hidden" name="nonce" value="<?= $installNonce ?>">
        <p><input type="submit" class="button button-primary" value="<?= fl_escape(yourls__('Install plugin', 'frontend-links')) ?>"></p>
    </form>
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

<!-- ═══════════════════════════════════════════════════════════
     ADMIN STYLES
     ═══════════════════════════════════════════════════════════ -->
<style>
.fl-toast{position:fixed;top:32px;right:20px;padding:10px 18px;border-radius:4px;z-index:100001;font-size:13px;max-width:400px;opacity:0;transform:translateY(-10px);transition:opacity .3s,transform .3s;pointer-events:none}
.fl-toast.show{opacity:1;transform:translateY(0);pointer-events:auto}
.fl-toast.success{background:#dff0d8;color:#3c763d;border:1px solid #d6e9c6}
.fl-toast.error{background:#f2dede;color:#a94442;border:1px solid #ebccd1}
.fl-panel-toggle{cursor:pointer;user-select:none;display:flex;align-items:center;gap:8px}
.fl-panel-toggle:hover{opacity:.8}
.fl-panel-toggle .fl-arrow{transition:transform .2s;font-size:10px;color:#888}
.fl-panel-toggle .fl-arrow.open{transform:rotate(90deg)}
.fl-modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:99999;align-items:center;justify-content:center}
.fl-modal-box{background:#fff;border-radius:6px;padding:20px 24px;max-width:500px;width:90%;box-shadow:0 8px 30px rgba(0,0,0,.3);max-height:80vh;overflow-y:auto}
.fl-modal-box h3{margin:0 0 16px}
.fl-actions a{white-space:nowrap}
tr.fl-fade-out{opacity:0;transition:opacity .3s}
</style>

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
        <tr data-id="<?= $section['id'] ?>">
            <td><strong class="fl-section-title"><?= fl_escape($section['title']) ?></strong></td>
            <td class="fl-section-count"><?= count($linksBySection[$section['id']] ?? []) ?></td>
            <td class="fl-section-order"><?= $section['sort_order'] ?></td>
            <td class="fl-section-active"><?= $section['is_active'] ? yourls__('Yes', 'frontend-links') : '<em>' . yourls__('No', 'frontend-links') . '</em>' ?></td>
            <td class="fl-actions">
                <a href="#" onclick="flEditSection(<?= $section['id'] ?>, '<?= fl_escape(addslashes($section['title'])) ?>', <?= $section['sort_order'] ?>, <?= $section['is_active'] ?>); return false;"><?php yourls_e('Edit', 'frontend-links'); ?></a>
                &nbsp;|&nbsp;
                <a href="#" onclick="flDeleteSection(<?= $section['id'] ?>); return false;" style="color:#a00;"><?php yourls_e('Delete', 'frontend-links'); ?></a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p id="fl-no-sections"><em><?php yourls_e('No sections.', 'frontend-links'); ?></em></p>
<?php endif; ?>

<p><a href="#" class="button" onclick="flOpenModal('flAddSectionModal'); return false;"><?php yourls_e('+ Add a section', 'frontend-links'); ?></a></p>

<hr>

<h2><?php yourls_e('Links', 'frontend-links'); ?></h2>

<?php if (!empty($sections)): ?>
<p style="margin-bottom:12px;"><a href="#" class="button" onclick="flOpenModal('flAddLinkModal'); return false;"><?php yourls_e('+ Add a link', 'frontend-links'); ?></a></p>
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
            <?php foreach ($sectionLinks as $link): ?>
            <tr data-id="<?= $link['id'] ?>" data-link='<?= fl_escape(json_encode($link)) ?>'>
                <td style="text-align:center;"><?= fl_get_icon($link['icon'], 16) ?></td>
                <td><strong class="fl-link-label"><?= fl_escape($link['label']) ?></strong></td>
                <td class="fl-link-url"><a href="<?= fl_escape(fl_strip_base_path($link['url'])) ?>" target="_blank" style="font-size:12px;"><?= fl_escape(fl_strip_base_path($link['url'])) ?></a></td>
                <td class="fl-link-order"><?= $link['sort_order'] ?></td>
                <td class="fl-link-active"><?= $link['is_active'] ? yourls__('Yes', 'frontend-links') : '<em>' . yourls__('No', 'frontend-links') . '</em>' ?></td>
                <td class="fl-actions">
                    <a href="#" onclick='flEditLink(<?= json_encode($link) ?>); return false;'><?php yourls_e('Edit', 'frontend-links'); ?></a>
                    &nbsp;|&nbsp;
                    <a href="#" onclick="flDeleteLink(<?= $link['id'] ?>, <?= $link['section_id'] ?>); return false;" style="color:#a00;"><?php yourls_e('Delete', 'frontend-links'); ?></a>
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
<h2 class="fl-panel-toggle" onclick="flTogglePanel('fl-panel-profile', this)">
    <span class="fl-arrow">&#9654;</span> <?php yourls_e('Profile', 'frontend-links'); ?>
</h2>
<div id="fl-panel-profile" style="display:none;">
    <form id="fl-form-profile" onsubmit="return flSubmitProfile(this)" enctype="multipart/form-data">
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
                        <button type="button" id="fl-btn-delete-avatar" class="button" onclick="flDeleteAvatar()" style="<?= $currentAvatar ? '' : 'display:none;' ?>"><?php yourls_e('Delete avatar', 'frontend-links'); ?></button>
                        <button type="button" id="fl-btn-restore-avatar" class="button" onclick="flRestoreAvatar()" style="<?= $previousAvatarUrl ? '' : 'display:none;' ?>"><?php yourls_e('Restore previous', 'frontend-links'); ?></button>
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
<h2 class="fl-panel-toggle" onclick="flTogglePanel('fl-panel-icons', this)">
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
                    <a href="#" onclick="flDeleteIcon(<?= $ci['id'] ?>); return false;" style="color:#a00;"><?php yourls_e('Delete', 'frontend-links'); ?></a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <p style="margin-top:12px;">
        <a href="#" class="button" onclick="flOpenModal('flAddIconModal'); return false;"><?php yourls_e('+ Add an icon', 'frontend-links'); ?></a>
    </p>
</div>

<hr>

<!-- ═══════════════════════════════════════════════════════════
     OPTIONS (collapsible)
     ═══════════════════════════════════════════════════════════ -->
<h2 class="fl-panel-toggle" onclick="flTogglePanel('fl-panel-options', this)">
    <span class="fl-arrow">&#9654;</span> <?php yourls_e('Options', 'frontend-links'); ?>
</h2>
<div id="fl-panel-options" style="display:none;">

    <h3><?php yourls_e('Display mode', 'frontend-links'); ?></h3>
    <form id="fl-form-display-mode" onsubmit="return flSubmitAjax(this)">
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
    <form id="fl-form-shorturl" onsubmit="return flSubmitAjax(this)">
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
</div>

<hr>

<!-- ═══════════════════════════════════════════════════════════
     INFORMATION (collapsible)
     ═══════════════════════════════════════════════════════════ -->
<h2 class="fl-panel-toggle" onclick="flTogglePanel('fl-panel-info', this)">
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
        <form onsubmit="return flSubmitAddSection(this)">
            <input type="hidden" name="fl_action" value="add_section">
            <input type="hidden" name="nonce" value="<?= $nonce ?>">
            <table class="form-table">
                <tr><th><label><?php yourls_e('Title', 'frontend-links'); ?></label></th><td><input type="text" name="title" size="30" required placeholder="<?= fl_escape(yourls__('Section name', 'frontend-links')) ?>"></td></tr>
                <tr><th><label><?php yourls_e('Order', 'frontend-links'); ?></label></th><td><input type="number" name="sort_order" value="0" min="0" size="5"></td></tr>
                <tr><th><label><?php yourls_e('Active', 'frontend-links'); ?></label></th><td><input type="checkbox" name="is_active" checked></td></tr>
            </table>
            <p>
                <input type="submit" class="button button-primary" value="<?= fl_escape(yourls__('Add', 'frontend-links')) ?>">
                <a href="#" class="button" onclick="flCloseModals(); return false;"><?php yourls_e('Cancel', 'frontend-links'); ?></a>
            </p>
        </form>
    </div>
</div>

<!-- Modal: Edit section -->
<div id="flEditSectionModal" class="fl-modal-overlay">
    <div class="fl-modal-box">
        <h3><?php yourls_e('Edit section', 'frontend-links'); ?></h3>
        <form onsubmit="return flSubmitEditSection(this)">
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
                <a href="#" class="button" onclick="flCloseModals(); return false;"><?php yourls_e('Cancel', 'frontend-links'); ?></a>
            </p>
        </form>
    </div>
</div>

<!-- Modal: Add link -->
<div id="flAddLinkModal" class="fl-modal-overlay">
    <div class="fl-modal-box">
        <h3><?php yourls_e('Add a link', 'frontend-links'); ?></h3>
        <form onsubmit="return flSubmitAddLink(this)">
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
                <a href="#" class="button" onclick="flCloseModals(); return false;"><?php yourls_e('Cancel', 'frontend-links'); ?></a>
            </p>
        </form>
    </div>
</div>

<!-- Modal: Edit link -->
<div id="flEditLinkModal" class="fl-modal-overlay">
    <div class="fl-modal-box">
        <h3><?php yourls_e('Edit link', 'frontend-links'); ?></h3>
        <form onsubmit="return flSubmitEditLink(this)">
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
                <a href="#" class="button" onclick="flCloseModals(); return false;"><?php yourls_e('Cancel', 'frontend-links'); ?></a>
            </p>
        </form>
    </div>
</div>

<!-- Modal: Add custom icon -->
<div id="flAddIconModal" class="fl-modal-overlay">
    <div class="fl-modal-box">
        <h3><?php yourls_e('Add a custom icon', 'frontend-links'); ?></h3>
        <form onsubmit="return flSubmitAddIcon(this)" enctype="multipart/form-data">
            <input type="hidden" name="fl_action" value="add_custom_icon">
            <input type="hidden" name="nonce" value="<?= $nonce ?>">
            <table class="form-table">
                <tr><th><label><?php yourls_e('Name', 'frontend-links'); ?></label></th><td>
                    <input type="text" name="icon_name" size="25" required placeholder="my_icon" pattern="[a-z0-9_-]+">
                    <br><span style="color:#666;font-size:11px;"><?php yourls_e('Lowercase letters, numbers, _ and -. This name will be used to select the icon.', 'frontend-links'); ?></span>
                </td></tr>
                <tr><th><label><?php yourls_e('Type', 'frontend-links'); ?></label></th><td>
                    <label style="margin-right:16px;"><input type="radio" name="icon_type" value="svg" checked onchange="flToggleIconType()"> <?php yourls_e('SVG code', 'frontend-links'); ?></label>
                    <label><input type="radio" name="icon_type" value="image" onchange="flToggleIconType()"> <?php yourls_e('Image', 'frontend-links'); ?></label>
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
                <a href="#" class="button" onclick="flCloseModals(); return false;"><?php yourls_e('Cancel', 'frontend-links'); ?></a>
            </p>
        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     JAVASCRIPT
     ═══════════════════════════════════════════════════════════ -->
<script>
var FL = {
    ajaxUrl: <?= json_encode(FL_AJAX_URL) ?>,
    nonce: <?= json_encode($nonce) ?>,
    i18n: {
        confirmDeleteLink: <?= json_encode(yourls__('Delete this link?', 'frontend-links')) ?>,
        confirmDeleteSection: <?= json_encode(yourls__('Delete this section and all its links?', 'frontend-links')) ?>,
        confirmDeleteIcon: <?= json_encode(yourls__('Delete this icon?', 'frontend-links')) ?>,
        confirmDeleteAvatar: <?= json_encode(yourls__('Delete the current avatar?', 'frontend-links')) ?>,
        confirmRestoreAvatar: <?= json_encode(yourls__('Restore the previous avatar?', 'frontend-links')) ?>,
        connectionError: <?= json_encode(yourls__('Connection error.', 'frontend-links')) ?>,
        noLinksInSection: <?= json_encode(yourls__('No links in this section.', 'frontend-links')) ?>,
        noCustomIcons: <?= json_encode(yourls__('No custom icons.', 'frontend-links')) ?>,
        noSections: <?= json_encode(yourls__('No sections.', 'frontend-links')) ?>,
        yes: <?= json_encode(yourls__('Yes', 'frontend-links')) ?>,
        no: <?= json_encode(yourls__('No', 'frontend-links')) ?>,
        edit: <?= json_encode(yourls__('Edit', 'frontend-links')) ?>,
        delete_: <?= json_encode(yourls__('Delete', 'frontend-links')) ?>,
        thIcon: <?= json_encode(yourls__('Icon', 'frontend-links')) ?>,
        thLabel: <?= json_encode(yourls__('Label', 'frontend-links')) ?>,
        thUrl: <?= json_encode(yourls__('URL', 'frontend-links')) ?>,
        thOrder: <?= json_encode(yourls__('Order', 'frontend-links')) ?>,
        thActive: <?= json_encode(yourls__('Active', 'frontend-links')) ?>,
        thActions: <?= json_encode(yourls__('Actions', 'frontend-links')) ?>,
        thTitle: <?= json_encode(yourls__('Title', 'frontend-links')) ?>,
        thLinks: <?= json_encode(yourls__('Links', 'frontend-links')) ?>,
        thPreview: <?= json_encode(yourls__('Preview', 'frontend-links')) ?>,
        thName: <?= json_encode(yourls__('Name', 'frontend-links')) ?>,
        thType: <?= json_encode(yourls__('Type', 'frontend-links')) ?>,
        image: <?= json_encode(yourls__('Image', 'frontend-links')) ?>
    }
};

// ─── Toast ──────────────────────────────────────────────────
function flToast(msg, isError) {
    var el = document.getElementById('fl-toast');
    el.textContent = msg;
    el.className = 'fl-toast ' + (isError ? 'error' : 'success') + ' show';
    clearTimeout(el._t);
    el._t = setTimeout(function() { el.classList.remove('show'); }, 3500);
}

// ─── Escape HTML ────────────────────────────────────────────
function flEsc(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
}

// ─── Panels toggle ──────────────────────────────────────────
function flTogglePanel(id, header) {
    var panel = document.getElementById(id);
    var arrow = header.querySelector('.fl-arrow');
    if (panel.style.display === 'none') {
        panel.style.display = '';
        arrow.classList.add('open');
    } else {
        panel.style.display = 'none';
        arrow.classList.remove('open');
    }
}

// ─── Modals ─────────────────────────────────────────────────
function flOpenModal(id) {
    document.getElementById(id).style.display = 'flex';
}

function flCloseModals() {
    document.querySelectorAll('.fl-modal-overlay').forEach(function(m) {
        m.style.display = 'none';
    });
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') flCloseModals();
});

document.querySelectorAll('.fl-modal-overlay').forEach(function(m) {
    m.addEventListener('click', function(e) {
        if (e.target === this) flCloseModals();
    });
});

// ─── Icon type toggle ───────────────────────────────────────
function flToggleIconType() {
    var isSvg = document.querySelector('input[name="icon_type"]:checked').value === 'svg';
    document.getElementById('flIconSvgRow').style.display = isSvg ? '' : 'none';
    document.getElementById('flIconFileRow').style.display = isSvg ? 'none' : '';
}

// ─── AJAX helper ────────────────────────────────────────────
function flAjax(formData, callback) {
    fetch(FL.ajaxUrl, {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        flToast(data.message, !data.success);
        if (callback) callback(data);
    })
    .catch(function() {
        flToast(FL.i18n.connectionError, true);
    });
}

function flFormData(form) {
    return new FormData(form);
}

// Generic AJAX submit for option forms
function flSubmitAjax(form) {
    flAjax(flFormData(form), null);
    return false;
}

// ─── LINKS: Add ─────────────────────────────────────────────
function flSubmitAddLink(form) {
    flAjax(flFormData(form), function(resp) {
        if (!resp.success) return;
        var link = resp.data;
        var sectionDiv = document.getElementById('fl-link-section-' + link.section_id);
        if (!sectionDiv) {
            // Section div missing from DOM (e.g. added in same session), reload
            flCloseModals();
            location.reload();
            return;
        }

        var empty = sectionDiv.querySelector('.fl-empty-msg');
        if (empty) {
            empty.remove();
            var table = document.createElement('table');
            table.className = 'tblUrl fl-links-table';
            table.innerHTML = '<thead><tr><th>' + FL.i18n.thIcon + '</th><th>' + FL.i18n.thLabel + '</th><th>' + FL.i18n.thUrl + '</th><th>' + FL.i18n.thOrder + '</th><th>' + FL.i18n.thActive + '</th><th>' + FL.i18n.thActions + '</th></tr></thead><tbody></tbody>';
            sectionDiv.appendChild(table);
        }

        var tbody = sectionDiv.querySelector('.fl-links-table tbody');
        if (tbody) {
            tbody.insertAdjacentHTML('beforeend', flBuildLinkRow(link));
        }

        flUpdateSectionCount(link.section_id, 1);
        flCloseModals();
        form.reset();
    });
    return false;
}

// ─── LINKS: Edit ────────────────────────────────────────────
function flEditLink(link) {
    document.getElementById('fl_edit_link_id').value = link.id;
    document.getElementById('fl_edit_link_label').value = link.label;
    document.getElementById('fl_edit_link_url').value = link.url;
    document.getElementById('fl_edit_link_section').value = link.section_id;
    document.getElementById('fl_edit_link_icon').value = link.icon;
    document.getElementById('fl_edit_link_order').value = link.sort_order;
    document.getElementById('fl_edit_link_active').checked = link.is_active == 1;
    flOpenModal('flEditLinkModal');
}

function flSubmitEditLink(form) {
    var oldSectionId = null;
    var linkId = document.getElementById('fl_edit_link_id').value;
    var oldRow = document.querySelector('tr[data-id="' + linkId + '"]');
    if (oldRow) {
        var oldSection = oldRow.closest('.fl-link-section');
        if (oldSection) oldSectionId = oldSection.dataset.sectionId;
    }

    flAjax(flFormData(form), function(resp) {
        if (!resp.success) return;
        var link = resp.data;

        if (oldRow) {
            oldRow.remove();
            if (oldSectionId && oldSectionId != link.section_id) {
                flUpdateSectionCount(oldSectionId, -1);
                flCheckEmptySection(oldSectionId);
            }
        }

        var sectionDiv = document.getElementById('fl-link-section-' + link.section_id);
        if (sectionDiv) {
            var empty = sectionDiv.querySelector('.fl-empty-msg');
            if (empty) {
                empty.remove();
                var table = document.createElement('table');
                table.className = 'tblUrl fl-links-table';
                table.innerHTML = '<thead><tr><th>' + FL.i18n.thIcon + '</th><th>' + FL.i18n.thLabel + '</th><th>' + FL.i18n.thUrl + '</th><th>' + FL.i18n.thOrder + '</th><th>' + FL.i18n.thActive + '</th><th>' + FL.i18n.thActions + '</th></tr></thead><tbody></tbody>';
                sectionDiv.appendChild(table);
            }
            var tbody = sectionDiv.querySelector('.fl-links-table tbody');
            var existingRow = tbody ? tbody.querySelector('tr[data-id="' + link.id + '"]') : null;
            if (existingRow) {
                existingRow.outerHTML = flBuildLinkRow(link);
            } else if (tbody) {
                tbody.insertAdjacentHTML('beforeend', flBuildLinkRow(link));
                if (oldSectionId != link.section_id) {
                    flUpdateSectionCount(link.section_id, 1);
                }
            }
        }

        flCloseModals();
    });
    return false;
}

// ─── LINKS: Delete ──────────────────────────────────────────
function flDeleteLink(id, sectionId) {
    if (!confirm(FL.i18n.confirmDeleteLink)) return;
    var fd = new FormData();
    fd.append('fl_action', 'delete_link');
    fd.append('nonce', FL.nonce);
    fd.append('link_id', id);
    fd.append('section_id', sectionId);

    flAjax(fd, function(resp) {
        if (!resp.success) return;
        var row = document.querySelector('tr[data-id="' + id + '"]');
        if (row) {
            row.classList.add('fl-fade-out');
            setTimeout(function() {
                row.remove();
                flUpdateSectionCount(sectionId, -1);
                flCheckEmptySection(sectionId);
            }, 300);
        }
    });
}

// ─── LINKS: Helpers ─────────────────────────────────────────
function flBuildLinkRow(link) {
    var linkJson = JSON.stringify(link).replace(/'/g, '&#39;').replace(/"/g, '&quot;');
    return '<tr data-id="' + link.id + '">' +
        '<td style="text-align:center;">' + link.icon_html + '</td>' +
        '<td><strong class="fl-link-label">' + flEsc(link.label) + '</strong></td>' +
        '<td class="fl-link-url"><a href="' + flEsc(link.url) + '" target="_blank" style="font-size:12px;">' + flEsc(link.url) + '</a></td>' +
        '<td class="fl-link-order">' + link.sort_order + '</td>' +
        '<td class="fl-link-active">' + (link.is_active == 1 ? FL.i18n.yes : '<em>' + FL.i18n.no + '</em>') + '</td>' +
        '<td class="fl-actions">' +
            '<a href="#" onclick="flEditLink(' + linkJson.replace(/&quot;/g, '&apos;').replace(/&apos;/g, "'") + '); return false;">' + FL.i18n.edit + '</a>' +
            '&nbsp;|&nbsp;' +
            '<a href="#" onclick="flDeleteLink(' + link.id + ', ' + link.section_id + '); return false;" style="color:#a00;">' + FL.i18n.delete_ + '</a>' +
        '</td></tr>';
}

function flUpdateSectionCount(sectionId, delta) {
    var row = document.querySelector('#fl-sections-table tr[data-id="' + sectionId + '"]');
    if (!row) return;
    var cell = row.querySelector('.fl-section-count');
    if (cell) cell.textContent = Math.max(0, parseInt(cell.textContent || '0') + delta);
}

function flCheckEmptySection(sectionId) {
    var sectionDiv = document.getElementById('fl-link-section-' + sectionId);
    if (!sectionDiv) return;
    var tbody = sectionDiv.querySelector('.fl-links-table tbody');
    if (tbody && tbody.children.length === 0) {
        var table = sectionDiv.querySelector('.fl-links-table');
        if (table) table.remove();
        var p = document.createElement('p');
        p.className = 'fl-empty-msg';
        p.innerHTML = '<em>' + FL.i18n.noLinksInSection + '</em>';
        sectionDiv.appendChild(p);
    }
}

// ─── SECTIONS: Add ──────────────────────────────────────────
function flSubmitAddSection(form) {
    flAjax(flFormData(form), function(resp) {
        if (!resp.success) return;
        var s = resp.data;

        var tbody = document.querySelector('#fl-sections-table tbody');
        var noSections = document.getElementById('fl-no-sections');
        if (!tbody) {
            if (noSections) noSections.remove();
            var wrapper = document.querySelector('h2');
            var table = document.createElement('table');
            table.className = 'tblUrl';
            table.id = 'fl-sections-table';
            table.innerHTML = '<thead><tr><th>' + FL.i18n.thTitle + '</th><th>' + FL.i18n.thLinks + '</th><th>' + FL.i18n.thOrder + '</th><th>' + FL.i18n.thActive + '</th><th>' + FL.i18n.thActions + '</th></tr></thead><tbody></tbody>';
            wrapper.insertAdjacentElement('afterend', table);
            tbody = table.querySelector('tbody');
        }
        tbody.insertAdjacentHTML('beforeend', flBuildSectionRow(s));

        var selects = document.querySelectorAll('select[name="section_id"]');
        selects.forEach(function(sel) {
            var opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.title;
            sel.appendChild(opt);
        });

        var newDiv = document.createElement('div');
        newDiv.id = 'fl-link-section-' + s.id;
        newDiv.className = 'fl-link-section';
        newDiv.dataset.sectionId = s.id;
        newDiv.innerHTML = '<h3 class="fl-section-heading">' + flEsc(s.title) + '</h3><p class="fl-empty-msg"><em>' + FL.i18n.noLinksInSection + '</em></p>';
        var lastSection = document.querySelector('.fl-link-section:last-of-type');
        if (lastSection) {
            lastSection.insertAdjacentElement('afterend', newDiv);
        } else {
            // First section: insert after the "Links" heading + add button
            var linksHeading = document.querySelectorAll('h2')[1];
            if (linksHeading) {
                var addBtn = linksHeading.nextElementSibling;
                if (addBtn) {
                    addBtn.insertAdjacentElement('afterend', newDiv);
                } else {
                    linksHeading.insertAdjacentElement('afterend', newDiv);
                }
            }
        }

        flCloseModals();
        form.reset();
    });
    return false;
}

// ─── SECTIONS: Edit ─────────────────────────────────────────
function flEditSection(id, title, order, active) {
    document.getElementById('fl_edit_section_id').value = id;
    document.getElementById('fl_edit_section_title').value = title;
    document.getElementById('fl_edit_section_order').value = order;
    document.getElementById('fl_edit_section_active').checked = active == 1;
    flOpenModal('flEditSectionModal');
}

function flSubmitEditSection(form) {
    flAjax(flFormData(form), function(resp) {
        if (!resp.success) return;
        var s = resp.data;

        var row = document.querySelector('#fl-sections-table tr[data-id="' + s.id + '"]');
        if (row) {
            row.querySelector('.fl-section-title').textContent = s.title;
            row.querySelector('.fl-section-order').textContent = s.sort_order;
            row.querySelector('.fl-section-active').innerHTML = s.is_active == 1 ? FL.i18n.yes : '<em>' + FL.i18n.no + '</em>';
            var editLink = row.querySelector('.fl-actions a');
            if (editLink) editLink.setAttribute('onclick', "flEditSection(" + s.id + ", '" + s.title.replace(/'/g, "\\'") + "', " + s.sort_order + ", " + s.is_active + "); return false;");
        }

        var heading = document.querySelector('#fl-link-section-' + s.id + ' .fl-section-heading');
        if (heading) heading.textContent = s.title;

        document.querySelectorAll('select[name="section_id"] option[value="' + s.id + '"]').forEach(function(opt) {
            opt.textContent = s.title;
        });

        flCloseModals();
    });
    return false;
}

// ─── SECTIONS: Delete ───────────────────────────────────────
function flDeleteSection(id) {
    if (!confirm(FL.i18n.confirmDeleteSection)) return;
    var fd = new FormData();
    fd.append('fl_action', 'delete_section');
    fd.append('nonce', FL.nonce);
    fd.append('section_id', id);

    flAjax(fd, function(resp) {
        if (!resp.success) return;

        var row = document.querySelector('#fl-sections-table tr[data-id="' + id + '"]');
        if (row) {
            row.classList.add('fl-fade-out');
            setTimeout(function() { row.remove(); }, 300);
        }

        var sectionDiv = document.getElementById('fl-link-section-' + id);
        if (sectionDiv) sectionDiv.remove();

        document.querySelectorAll('select[name="section_id"] option[value="' + id + '"]').forEach(function(opt) {
            opt.remove();
        });
    });
}

function flBuildSectionRow(s) {
    return '<tr data-id="' + s.id + '">' +
        '<td><strong class="fl-section-title">' + flEsc(s.title) + '</strong></td>' +
        '<td class="fl-section-count">0</td>' +
        '<td class="fl-section-order">' + s.sort_order + '</td>' +
        '<td class="fl-section-active">' + (s.is_active == 1 ? FL.i18n.yes : '<em>' + FL.i18n.no + '</em>') + '</td>' +
        '<td class="fl-actions">' +
            '<a href="#" onclick="flEditSection(' + s.id + ', \'' + s.title.replace(/'/g, "\\'") + '\', ' + s.sort_order + ', ' + s.is_active + '); return false;">' + FL.i18n.edit + '</a>' +
            '&nbsp;|&nbsp;' +
            '<a href="#" onclick="flDeleteSection(' + s.id + '); return false;" style="color:#a00;">' + FL.i18n.delete_ + '</a>' +
        '</td></tr>';
}

// ─── ICONS: Delete ──────────────────────────────────────────
function flDeleteIcon(id) {
    if (!confirm(FL.i18n.confirmDeleteIcon)) return;
    var fd = new FormData();
    fd.append('fl_action', 'delete_custom_icon');
    fd.append('nonce', FL.nonce);
    fd.append('icon_id', id);

    flAjax(fd, function(resp) {
        if (!resp.success) return;
        var row = document.querySelector('#fl-custom-icons-table tr[data-id="' + id + '"]');
        if (row) {
            row.classList.add('fl-fade-out');
            setTimeout(function() {
                row.remove();
                var tbody = document.querySelector('#fl-custom-icons-table tbody');
                if (tbody && tbody.children.length === 0) {
                    var table = document.getElementById('fl-custom-icons-table');
                    if (table) table.remove();
                    var p = document.createElement('p');
                    p.id = 'fl-no-custom-icons';
                    p.innerHTML = '<em>' + FL.i18n.noCustomIcons + '</em>';
                    var addBtn = document.querySelector('#fl-panel-icons > p:last-child');
                    if (addBtn) addBtn.insertAdjacentElement('beforebegin', p);
                }
            }, 300);
        }
    });
}

// ─── PROFILE: Submit ────────────────────────────────────────
function flSubmitProfile(form) {
    var fd = new FormData(form);
    flAjax(fd, function(resp) {
        if (!resp.success) return;
        var data = resp.data;
        if (data.avatar) {
            var img = document.getElementById('fl-avatar-current-img');
            img.src = data.avatar;
            document.getElementById('fl-avatar-current').style.display = '';
            document.getElementById('fl-btn-delete-avatar').style.display = '';
            var urlInput = form.querySelector('input[name="profile_avatar"]');
            if (urlInput) urlInput.value = data.avatar;
        }
        var fileInput = form.querySelector('input[name="avatar_file"]');
        if (fileInput) fileInput.value = '';
    });
    return false;
}

// ─── AVATAR: Delete ─────────────────────────────────────────
function flDeleteAvatar() {
    if (!confirm(FL.i18n.confirmDeleteAvatar)) return;
    var fd = new FormData();
    fd.append('fl_action', 'delete_avatar');
    fd.append('nonce', FL.nonce);

    flAjax(fd, function(resp) {
        if (!resp.success) return;
        document.getElementById('fl-avatar-current').style.display = 'none';
        document.getElementById('fl-avatar-current-img').src = '';
        document.getElementById('fl-btn-delete-avatar').style.display = 'none';
        var urlInput = document.querySelector('#fl-form-profile input[name="profile_avatar"]');
        if (urlInput) urlInput.value = '';
        if (resp.data && resp.data.previous_url) {
            document.getElementById('fl-avatar-previous-img').src = resp.data.previous_url;
            document.getElementById('fl-avatar-previous').style.display = '';
            document.getElementById('fl-btn-restore-avatar').style.display = '';
        } else {
            document.getElementById('fl-avatar-previous').style.display = 'none';
            document.getElementById('fl-btn-restore-avatar').style.display = 'none';
        }
    });
}

// ─── AVATAR: Restore ────────────────────────────────────────
function flRestoreAvatar() {
    if (!confirm(FL.i18n.confirmRestoreAvatar)) return;
    var fd = new FormData();
    fd.append('fl_action', 'restore_avatar');
    fd.append('nonce', FL.nonce);

    flAjax(fd, function(resp) {
        if (!resp.success) return;
        document.getElementById('fl-avatar-current-img').src = resp.data.avatar;
        document.getElementById('fl-avatar-current').style.display = '';
        document.getElementById('fl-btn-delete-avatar').style.display = '';
        var urlInput = document.querySelector('#fl-form-profile input[name="profile_avatar"]');
        if (urlInput) urlInput.value = resp.data.avatar;
        document.getElementById('fl-avatar-previous').style.display = 'none';
        document.getElementById('fl-btn-restore-avatar').style.display = 'none';
    });
}

// ─── ICONS: Add custom ──────────────────────────────────────
function flSubmitAddIcon(form) {
    var fd = new FormData(form);
    flAjax(fd, function(resp) {
        if (!resp.success) return;
        var icon = resp.data;

        var noIcons = document.getElementById('fl-no-custom-icons');
        if (noIcons) noIcons.remove();

        var tbody = document.querySelector('#fl-custom-icons-table tbody');
        if (!tbody) {
            var table = document.createElement('table');
            table.className = 'tblUrl';
            table.id = 'fl-custom-icons-table';
            table.innerHTML = '<thead><tr><th>' + FL.i18n.thPreview + '</th><th>' + FL.i18n.thName + '</th><th>' + FL.i18n.thType + '</th><th>' + FL.i18n.thActions + '</th></tr></thead><tbody></tbody>';
            var addBtn = document.querySelector('#fl-panel-icons > p:last-child');
            if (addBtn) addBtn.insertAdjacentElement('beforebegin', table);
            tbody = table.querySelector('tbody');
        }

        tbody.insertAdjacentHTML('beforeend',
            '<tr data-id="' + icon.id + '">' +
            '<td style="text-align:center;">' + icon.icon_html + '</td>' +
            '<td><strong>' + flEsc(icon.name) + '</strong></td>' +
            '<td>' + (icon.type === 'svg' ? 'SVG' : FL.i18n.image) + '</td>' +
            '<td class="fl-actions"><a href="#" onclick="flDeleteIcon(' + icon.id + '); return false;" style="color:#a00;">' + FL.i18n.delete_ + '</a></td>' +
            '</tr>'
        );

        var selects = document.querySelectorAll('select[name="icon"]');
        selects.forEach(function(sel) {
            var opt = document.createElement('option');
            opt.value = icon.name;
            opt.textContent = icon.name + ' \u2726';
            sel.appendChild(opt);
        });

        flCloseModals();
        form.reset();
    });
    return false;
}
</script>
