<?php
/**
 * Frontend Links - AJAX Endpoint
 * ================================
 *
 * Standalone file that handles all admin CRUD operations via AJAX.
 * Called by assets/js/admin.js through fetch() POST requests.
 *
 * Security:
 *   - Loads YOURLS independently (not inside admin template)
 *   - Verifies user authentication (yourls_is_valid_user)
 *   - Verifies nonce (protects against CSRF)
 *   - Only accepts POST requests
 *
 * Supported actions (via POST fl_action):
 *   Links:    add_link, edit_link, delete_link
 *   Sections: add_section, edit_section, delete_section
 *   Icons:    add_custom_icon, delete_custom_icon
 *   Profile:  update_profile, delete_avatar, restore_avatar
 *   Options:  update_display_mode, update_shorturl_option, update_theme
 *
 * Response format: JSON { success: bool, message: string, data?: object }
 *
 * @see assets/js/admin.js   JavaScript that calls this endpoint
 * @see templates/admin.php  Admin UI that displays the data
 *
 * @package FrontendLinks
 */

// Load YOURLS (3 levels: plugins/frontend-links/ → plugins/ → user/ → YOURLS root)
require_once dirname(dirname(dirname(__DIR__))) . '/includes/load-yourls.php';

header('Content-Type: application/json');

// Verify authentication (returns true or an error message, without die)
$auth = yourls_is_valid_user();
if ($auth !== true) {
    echo json_encode(['success' => false, 'message' => yourls__('Unauthorized. Please log in again.', 'frontend-links')]);
    die();
}

// Verify nonce manually (without yourls_verify_nonce which die() with HTML)
$nonce = $_REQUEST['nonce'] ?? '';
$expected = yourls_create_nonce('frontend_links');
if ($nonce !== $expected) {
    echo json_encode(['success' => false, 'message' => yourls__('Session expired. Please reload the page.', 'frontend-links')]);
    die();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['fl_action'])) {
    echo json_encode(['success' => false, 'message' => yourls__('Invalid request.', 'frontend-links')]);
    die();
}

$action = $_POST['fl_action'];
$resp = ['success' => false, 'message' => yourls__('Unknown action.', 'frontend-links')];

switch ($action) {
    case 'add_link':
        $id = fl_create_link([
            'section_id' => (int)$_POST['section_id'],
            'label'      => trim($_POST['label']),
            'url'        => fl_normalize_url(trim($_POST['url'])),
            'icon'       => $_POST['icon'],
            'sort_order' => (int)$_POST['sort_order'],
            'is_active'  => isset($_POST['is_active']) ? 1 : 0
        ]);
        if ($id) {
            $link = fl_get_link($id);
            $link['icon_html'] = fl_get_icon($link['icon'], 16);
            $link['url'] = fl_strip_base_path($link['url']);
            $resp = ['success' => true, 'message' => yourls__('Link added.', 'frontend-links'), 'data' => $link];
        } else {
            $resp['message'] = yourls__('Error adding item.', 'frontend-links');
        }
        break;

    case 'edit_link':
        $ok = fl_update_link((int)$_POST['link_id'], [
            'section_id' => (int)$_POST['section_id'],
            'label'      => trim($_POST['label']),
            'url'        => fl_normalize_url(trim($_POST['url'])),
            'icon'       => $_POST['icon'],
            'sort_order' => (int)$_POST['sort_order'],
            'is_active'  => isset($_POST['is_active']) ? 1 : 0
        ]);
        if ($ok) {
            $link = fl_get_link((int)$_POST['link_id']);
            $link['icon_html'] = fl_get_icon($link['icon'], 16);
            $link['url'] = fl_strip_base_path($link['url']);
            $resp = ['success' => true, 'message' => yourls__('Link updated.', 'frontend-links'), 'data' => $link];
        } else {
            $resp['message'] = yourls__('Error updating item.', 'frontend-links');
        }
        break;

    case 'delete_link':
        $ok = fl_delete_link((int)$_POST['link_id']);
        $resp = $ok
            ? ['success' => true, 'message' => yourls__('Link deleted.', 'frontend-links'), 'data' => ['id' => (int)$_POST['link_id'], 'section_id' => (int)$_POST['section_id']]]
            : ['success' => false, 'message' => yourls__('Error deleting item.', 'frontend-links')];
        break;

    case 'add_section':
        $id = fl_create_section([
            'section_key' => strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $_POST['title'])),
            'title'       => trim($_POST['title']),
            'sort_order'  => (int)$_POST['sort_order'],
            'is_active'   => isset($_POST['is_active']) ? 1 : 0
        ]);
        if ($id) {
            $section = fl_get_section($id);
            $resp = ['success' => true, 'message' => yourls__('Section added.', 'frontend-links'), 'data' => $section];
        } else {
            $resp['message'] = yourls__('Error adding item.', 'frontend-links');
        }
        break;

    case 'edit_section':
        $ok = fl_update_section((int)$_POST['section_id'], [
            'title'      => trim($_POST['title']),
            'sort_order' => (int)$_POST['sort_order'],
            'is_active'  => isset($_POST['is_active']) ? 1 : 0
        ]);
        if ($ok) {
            $section = fl_get_section((int)$_POST['section_id']);
            $resp = ['success' => true, 'message' => yourls__('Section updated.', 'frontend-links'), 'data' => $section];
        } else {
            $resp['message'] = yourls__('Error updating item.', 'frontend-links');
        }
        break;

    case 'delete_section':
        $ok = fl_delete_section((int)$_POST['section_id']);
        $resp = $ok
            ? ['success' => true, 'message' => yourls__('Section deleted.', 'frontend-links'), 'data' => ['id' => (int)$_POST['section_id']]]
            : ['success' => false, 'message' => yourls__('Error deleting item.', 'frontend-links')];
        break;

    case 'delete_custom_icon':
        $ok = fl_delete_custom_icon((int)$_POST['icon_id']);
        $resp = $ok
            ? ['success' => true, 'message' => yourls__('Icon deleted.', 'frontend-links'), 'data' => ['id' => (int)$_POST['icon_id']]]
            : ['success' => false, 'message' => yourls__('Error deleting item.', 'frontend-links')];
        break;

    case 'update_display_mode':
        $mode = in_array($_POST['display_mode'], ['manual', 'auto']) ? $_POST['display_mode'] : 'manual';
        yourls_update_option('fl_display_mode', $mode);
        if ($mode === 'auto') {
            $fileResult = fl_create_homepage_file();
            if ($fileResult['success']) {
                $resp = ['success' => true, 'message' => yourls__('Automatic mode enabled.', 'frontend-links') . ' ' . $fileResult['message']];
            } else {
                yourls_update_option('fl_display_mode', 'manual');
                $resp = ['success' => false, 'message' => $fileResult['message']];
            }
        } else {
            $fileResult = fl_delete_homepage_file();
            $resp = ['success' => true, 'message' => yourls__('Manual mode enabled.', 'frontend-links') . ' ' . $fileResult['message']];
        }
        break;

    case 'update_shorturl_option':
        $includePath = isset($_POST['shorturl_include_path']) ? '1' : '0';
        yourls_update_option('fl_shorturl_include_path', $includePath);
        $resp = ['success' => true, 'message' => yourls__('Option updated.', 'frontend-links')];
        break;

    case 'update_theme':
        $theme = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($_POST['theme'] ?? '')));
        $themes = fl_get_available_themes();
        if ($theme !== '' && isset($themes[$theme])) {
            yourls_update_option('fl_active_theme', $theme);
            $resp = ['success' => true, 'message' => sprintf(yourls__('Theme changed to "%s".', 'frontend-links'), $themes[$theme]['name'] ?? $theme)];
        } else {
            $resp = ['success' => false, 'message' => yourls__('Invalid theme.', 'frontend-links')];
        }
        break;

    case 'update_feature_toggles':
        $disableRedirect = isset($_POST['disable_redirect_page']) ? '1' : '0';
        $disable404 = isset($_POST['disable_404_page']) ? '1' : '0';
        yourls_update_option('fl_disable_redirect_page', $disableRedirect);
        yourls_update_option('fl_disable_404_page', $disable404);
        $resp = ['success' => true, 'message' => yourls__('Options updated.', 'frontend-links')];
        break;

    // ─── Profile & Avatar ────────────────────────────────────
    case 'update_profile':
        $avatarUrl = '';
        if (!empty($_FILES['avatar_file']['name'])) {
            $uploadUrl = fl_upload_avatar($_FILES['avatar_file']);
            if ($uploadUrl) {
                fl_update_setting('profile_avatar', $uploadUrl);
                $avatarUrl = $uploadUrl;
            } else {
                $resp = ['success' => false, 'message' => yourls__('Upload error. Formats: JPG, PNG, GIF, WebP, SVG. Max 2 MB.', 'frontend-links')];
                break;
            }
        } elseif (isset($_POST['profile_avatar'])) {
            $avatarUrl = trim($_POST['profile_avatar']);
            fl_update_setting('profile_avatar', $avatarUrl);
        }
        fl_update_setting('profile_name', trim($_POST['profile_name']));
        fl_update_setting('profile_bio', trim($_POST['profile_bio']));
        fl_update_setting('meta_title', trim($_POST['meta_title']));
        fl_update_setting('meta_description', trim($_POST['meta_description']));
        $resp = [
            'success' => true,
            'message' => yourls__('Profile updated.', 'frontend-links'),
            'data'    => [
                'avatar'   => $avatarUrl ?: fl_get_setting('profile_avatar'),
                'name'     => trim($_POST['profile_name']),
                'bio'      => trim($_POST['profile_bio']),
            ]
        ];
        break;

    case 'delete_avatar':
        fl_delete_current_avatar();
        fl_update_setting('profile_avatar', '');
        $previousFile = fl_find_avatar_file('fl_avatars_previous');
        $resp = [
            'success' => true,
            'message' => yourls__('Avatar deleted.', 'frontend-links'),
            'data'    => [
                'previous_url' => $previousFile ? FL_UPLOADS_URL . '/' . basename($previousFile) : ''
            ]
        ];
        break;

    case 'restore_avatar':
        $restoredUrl = fl_restore_previous_avatar();
        if ($restoredUrl) {
            fl_update_setting('profile_avatar', $restoredUrl);
            $resp = ['success' => true, 'message' => yourls__('Previous avatar restored.', 'frontend-links'), 'data' => ['avatar' => $restoredUrl]];
        } else {
            $resp = ['success' => false, 'message' => yourls__('No previous avatar.', 'frontend-links')];
        }
        break;

    // ─── Custom icons ──────────────────────────────────────
    case 'add_custom_icon':
        $iconName = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($_POST['icon_name'] ?? '')));
        $iconType = $_POST['icon_type'] ?? 'svg';

        if (empty($iconName)) {
            $resp = ['success' => false, 'message' => yourls__('Name required (lowercase letters, numbers, _ and -).', 'frontend-links')];
            break;
        }

        if ($iconType === 'svg') {
            $svgContent = trim($_POST['icon_svg'] ?? '');
            if (empty($svgContent) || stripos($svgContent, '<svg') === false) {
                $resp = ['success' => false, 'message' => yourls__('Invalid or empty SVG code.', 'frontend-links')];
                break;
            }
            // Sanitize SVG to prevent stored XSS
            $svgContent = fl_sanitize_svg($svgContent);
            $result = fl_create_custom_icon(['name' => $iconName, 'type' => 'svg', 'content' => $svgContent]);
        } else {
            if (empty($_FILES['icon_file']['name'])) {
                $resp = ['success' => false, 'message' => yourls__('No file selected.', 'frontend-links')];
                break;
            }
            $filename = fl_upload_icon_image($_FILES['icon_file'], $iconName);
            if (!$filename) {
                $resp = ['success' => false, 'message' => yourls__('Upload error. Formats: JPG, PNG, GIF, WebP, SVG. Max 1 MB.', 'frontend-links')];
                break;
            }
            $result = fl_create_custom_icon(['name' => $iconName, 'type' => 'image', 'content' => $filename]);
        }

        if ($result) {
            $resp = [
                'success'  => true,
                'message'  => sprintf(yourls__('Icon "%s" added.', 'frontend-links'), $iconName),
                'data'     => [
                    'id'        => $result,
                    'name'      => $iconName,
                    'type'      => $iconType,
                    'icon_html' => fl_get_icon($iconName, 20)
                ]
            ];
        } else {
            $resp = ['success' => false, 'message' => yourls__('Error: name already in use?', 'frontend-links')];
        }
        break;
}

echo json_encode($resp);
die();
