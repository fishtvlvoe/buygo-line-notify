<?php
/**
 * 測試外掛狀態 - 可以從命令列執行
 */

// 設定 WordPress 路徑
define('WP_USE_THEMES', false);
$wp_load = '/Users/fishtv/Local Sites/buygo/app/public/wp-load.php';

if (!file_exists($wp_load)) {
    die("ERROR: Cannot find wp-load.php at $wp_load\n");
}

require_once $wp_load;

echo "=== BuyGo LINE Notify 外掛狀態檢測 ===\n\n";

// 1. 檢查外掛是否啟用
$active_plugins = get_option('active_plugins');
$is_active = in_array('buygo-line-notify/buygo-line-notify.php', $active_plugins);
echo "1. 外掛已啟用: " . ($is_active ? '✓ YES' : '✗ NO') . "\n";

// 2. 檢查常數
echo "\n2. 常數定義:\n";
echo "   BuygoLineNotify_PLUGIN_DIR: " . (defined('BuygoLineNotify_PLUGIN_DIR') ? '✓ YES' : '✗ NO') . "\n";
echo "   BuygoLineNotify_PLUGIN_VERSION: " . (defined('BuygoLineNotify_PLUGIN_VERSION') ? '✓ YES' : '✗ NO') . "\n";

// 3. 檢查類別
echo "\n3. 類別載入:\n";
echo "   BuygoLineNotify\\Plugin: " . (class_exists('BuygoLineNotify\\Plugin') ? '✓ YES' : '✗ NO') . "\n";
echo "   BuygoLineNotify\\Admin\\SettingsPage: " . (class_exists('BuygoLineNotify\\Admin\\SettingsPage') ? '✓ YES' : '✗ NO') . "\n";
echo "   BuyGoPlus\\Plugin: " . (class_exists('BuyGoPlus\\Plugin') ? '✓ YES (父外掛存在)' : '✗ NO (獨立選單)') . "\n";

// 4. 檢查資料表
global $wpdb;
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}buygo_line_bindings'");
echo "\n4. 資料表:\n";
echo "   wp_buygo_line_bindings: " . ($table_exists ? '✓ YES' : '✗ NO') . "\n";

// 5. 檢查檔案
echo "\n5. 關鍵檔案:\n";
$files = [
    'includes/admin/class-settings-page.php',
    'includes/admin/views/settings-page.php',
    'includes/services/class-settings-service.php',
];
foreach ($files as $file) {
    $path = BuygoLineNotify_PLUGIN_DIR . $file;
    echo "   $file: " . (file_exists($path) ? '✓ YES' : '✗ NO') . "\n";
}

// 6. 測試選單註冊（模擬）
echo "\n6. 選單邏輯測試:\n";
if (class_exists('BuyGoPlus\\Plugin')) {
    echo "   → 會使用 add_submenu_page 掛載到 'buygo-plus-one' 下\n";
    echo "   → 選單 slug: 'buygo-line-notify-settings'\n";
    echo "   → 正確 URL: /wp-admin/admin.php?page=buygo-line-notify-settings\n";
} else {
    echo "   → 會使用 add_menu_page 建立獨立選單\n";
    echo "   → 選單 slug: 'buygo-line-notify'\n";
    echo "   → 正確 URL: /wp-admin/admin.php?page=buygo-line-notify\n";
}

echo "\n=== 檢測完成 ===\n";
