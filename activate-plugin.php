<?php
/**
 * 重新啟用 buygo-line-notify 外掛
 */
require_once __DIR__ . '/../../../wp-load.php';

if (!current_user_can('activate_plugins')) {
    die('需要管理員權限');
}

$plugin = 'buygo-line-notify/buygo-line-notify.php';

echo "重新啟用外掛: $plugin\n\n";

// 檢查外掛是否已啟用
if (is_plugin_active($plugin)) {
    echo "✓ 外掛已經啟用\n";
} else {
    // 啟用外掛
    $result = activate_plugin($plugin);
    
    if (is_wp_error($result)) {
        echo "✗ 啟用失敗: " . $result->get_error_message() . "\n";
    } else {
        echo "✓ 外掛啟用成功\n";
    }
}

// 驗證
if (is_plugin_active($plugin)) {
    echo "\n驗證: 外掛現在已啟用 ✓\n";
} else {
    echo "\n警告: 外掛仍然未啟用\n";
}
