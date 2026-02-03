<?php
/**
 * 測試 NSL 整合修復
 *
 * 驗證：
 * 1. buygo-line-notify 登入按鈕是否被成功移除
 * 2. LineUserService 是否能讀取 NSL 的綁定資料
 *
 * 用法：
 * 1. 上傳到 WordPress 根目錄
 * 2. 訪問：https://test.buygo.me/test-nsl-integration-fix.php
 */

// 載入 WordPress
require_once __DIR__ . '/../../../wp-load.php';

header('Content-Type: text/html; charset=utf-8');

echo '<h1>NSL 整合修復驗證</h1>';
echo '<style>
body { font-family: monospace; padding: 20px; }
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.info { color: blue; }
table { border-collapse: collapse; margin: 20px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background: #f0f0f0; }
</style>';

// ========================================================================
// 測試 1: 檢查 NSL 外掛狀態
// ========================================================================
echo '<h2>測試 1: NSL 外掛狀態</h2>';

$nsl_active = class_exists('NextendSocialLogin');
echo '<p>NSL 外掛已啟用: ' . ($nsl_active ? '<span class="success">是</span>' : '<span class="error">否</span>') . '</p>';

if (!$nsl_active) {
    echo '<p class="error">錯誤：NSL 外掛未啟用，無法繼續測試</p>';
    exit;
}

// ========================================================================
// 測試 2: 檢查 buygo-line-notify 登入按鈕 hooks
// ========================================================================
echo '<h2>測試 2: 檢查登入按鈕 hooks</h2>';

global $wp_filter;

$hooks_to_check = [
    'fluent_community/before_auth_form_header',
    'lrm/login_form/before',
    'login_form',
    'register_form',
];

echo '<table>';
echo '<tr><th>Hook</th><th>已註冊的回調</th><th>狀態</th></tr>';

foreach ($hooks_to_check as $hook_name) {
    $callbacks = [];

    if (isset($wp_filter[$hook_name])) {
        foreach ($wp_filter[$hook_name]->callbacks as $priority => $hooks) {
            foreach ($hooks as $hook) {
                if (is_array($hook['function'])) {
                    $class = is_object($hook['function'][0]) ? get_class($hook['function'][0]) : $hook['function'][0];
                    $method = $hook['function'][1];
                    $callbacks[] = "{$class}::{$method} (priority: {$priority})";
                } else {
                    $callbacks[] = $hook['function'] . " (priority: {$priority})";
                }
            }
        }
    }

    $has_buygo_button = false;
    foreach ($callbacks as $callback) {
        if (strpos($callback, 'LoginButtonService') !== false) {
            $has_buygo_button = true;
            break;
        }
    }

    $status = $has_buygo_button
        ? '<span class="error">❌ buygo 按鈕仍存在</span>'
        : '<span class="success">✅ buygo 按鈕已移除</span>';

    echo '<tr>';
    echo '<td>' . esc_html($hook_name) . '</td>';
    echo '<td>' . (empty($callbacks) ? '<em>無</em>' : implode('<br>', array_map('esc_html', $callbacks))) . '</td>';
    echo '<td>' . $status . '</td>';
    echo '</tr>';
}

echo '</table>';

// ========================================================================
// 測試 3: 檢查 LineUserService 讀取 NSL 資料
// ========================================================================
echo '<h2>測試 3: 檢查 LineUserService 讀取 NSL 資料</h2>';

global $wpdb;

// 查詢 NSL 表中的 LINE 綁定
$nsl_users = $wpdb->get_results(
    "SELECT ID, identifier FROM {$wpdb->prefix}social_users WHERE type = 'line' LIMIT 5"
);

if (empty($nsl_users)) {
    echo '<p class="info">沒有找到 NSL LINE 綁定用戶</p>';
} else {
    echo '<p class="info">找到 ' . count($nsl_users) . ' 個 NSL LINE 綁定用戶，測試讀取...</p>';

    echo '<table>';
    echo '<tr><th>User ID</th><th>LINE UID (NSL)</th><th>LineUserService 讀取結果</th><th>狀態</th></tr>';

    foreach ($nsl_users as $nsl_user) {
        $user_id = (int) $nsl_user->ID;
        $expected_line_uid = $nsl_user->identifier;

        // 測試 LineUserService::getLineUidByUserId()
        $result = \BuygoLineNotify\Services\LineUserService::getLineUidByUserId($user_id);

        $status = ($result === $expected_line_uid)
            ? '<span class="success">✅ 正確</span>'
            : '<span class="error">❌ 錯誤</span>';

        echo '<tr>';
        echo '<td>' . $user_id . '</td>';
        echo '<td>' . esc_html(substr($expected_line_uid, 0, 20) . '...') . '</td>';
        echo '<td>' . ($result ? esc_html(substr($result, 0, 20) . '...') : '<em>null</em>') . '</td>';
        echo '<td>' . $status . '</td>';
        echo '</tr>';
    }

    echo '</table>';
}

// ========================================================================
// 測試 4: 檢查當前用戶的綁定狀態
// ========================================================================
echo '<h2>測試 4: 當前用戶綁定狀態</h2>';

if (is_user_logged_in()) {
    $current_user_id = get_current_user_id();
    $line_uid = \BuygoLineNotify\Services\LineUserService::getLineUidByUserId($current_user_id);
    $is_linked = \BuygoLineNotify\Services\LineUserService::isUserLinked($current_user_id);

    echo '<table>';
    echo '<tr><th>項目</th><th>值</th></tr>';
    echo '<tr><td>User ID</td><td>' . $current_user_id . '</td></tr>';
    echo '<tr><td>是否已綁定</td><td>' . ($is_linked ? '<span class="success">是</span>' : '<span class="error">否</span>') . '</td></tr>';
    echo '<tr><td>LINE UID</td><td>' . ($line_uid ? esc_html($line_uid) : '<em>未綁定</em>') . '</td></tr>';
    echo '</table>';

    // 檢查資料來源
    global $wpdb;
    $in_buygo_table = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}buygo_line_users WHERE user_id = %d",
        $current_user_id
    ));
    $in_nsl_table = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}social_users WHERE ID = %d AND type = 'line'",
        $current_user_id
    ));

    echo '<p class="info">資料來源：';
    if ($in_buygo_table > 0) {
        echo 'wp_buygo_line_users ';
    }
    if ($in_nsl_table > 0) {
        echo 'wp_social_users (NSL) ';
    }
    if ($in_buygo_table == 0 && $in_nsl_table == 0) {
        echo '無綁定記錄';
    }
    echo '</p>';
} else {
    echo '<p class="info">未登入，無法測試當前用戶</p>';
}

// ========================================================================
// 總結
// ========================================================================
echo '<h2>驗證總結</h2>';
echo '<ul>';
echo '<li>請訪問登入頁面 <a href="https://one.buygo.me/portal/?from_action=auth" target="_blank">https://one.buygo.me/portal/?from_action=auth</a>，確認只顯示 NSL 的 LINE 登入按鈕</li>';
echo '<li>請訪問會員界面 <a href="https://test.buygo.me/my-account/" target="_blank">https://test.buygo.me/my-account/</a>，確認 LINE 綁定狀態顯示正確</li>';
echo '</ul>';
