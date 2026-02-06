<?php
/**
 * 偵錯頁面 - 檢查設定頁面載入問題
 */

// 載入 WordPress
require_once '/Users/fishtv/Local Sites/buygo/app/public/wp-load.php';

// 開啟錯誤顯示
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>BuyGo LINE Notify 偵錯資訊</h1>";

// 1. 檢查外掛是否已啟用
echo "<h2>1. 外掛狀態</h2>";
$active_plugins = get_option('active_plugins');
$is_active = in_array('buygo-line-notify/buygo-line-notify.php', $active_plugins);
echo "外掛已啟用: " . ($is_active ? '✓ 是' : '✗ 否') . "<br>";

// 2. 檢查常數是否定義
echo "<h2>2. 常數定義</h2>";
echo "BuygoLineNotify_PLUGIN_DIR: " . (defined('BuygoLineNotify_PLUGIN_DIR') ? '✓ ' . BuygoLineNotify_PLUGIN_DIR : '✗ 未定義') . "<br>";
echo "BuygoLineNotify_PLUGIN_VERSION: " . (defined('BuygoLineNotify_PLUGIN_VERSION') ? '✓ ' . BuygoLineNotify_PLUGIN_VERSION : '✗ 未定義') . "<br>";

// 3. 檢查類別是否存在
echo "<h2>3. 類別載入</h2>";
echo "Plugin 類別: " . (class_exists('BuygoLineNotify\Plugin') ? '✓' : '✗') . "<br>";
echo "SettingsPage 類別: " . (class_exists('BuygoLineNotify\Admin\SettingsPage') ? '✓' : '✗') . "<br>";
echo "SettingsService 類別: " . (class_exists('BuygoLineNotify\Services\SettingsService') ? '✓' : '✗') . "<br>";
echo "Database 類別: " . (class_exists('BuygoLineNotify\Database') ? '✓' : '✗') . "<br>";
echo "LineUserService 類別: " . (class_exists('BuygoLineNotify\Services\LineUserService') ? '✓' : '✗') . "<br>";

// 4. 檢查檔案是否存在
echo "<h2>4. 檔案存在</h2>";
$files = [
    'includes/admin/class-settings-page.php',
    'includes/admin/views/settings-page.php',
    'includes/services/class-settings-service.php',
    'includes/class-database.php',
    'includes/services/class-line-user-service.php',
];
foreach ($files as $file) {
    $path = BuygoLineNotify_PLUGIN_DIR . $file;
    echo "$file: " . (file_exists($path) ? '✓' : '✗') . "<br>";
}

// 5. 檢查使用者權限
echo "<h2>5. 使用者權限</h2>";
echo "當前使用者 ID: " . get_current_user_id() . "<br>";
echo "manage_options 權限: " . (current_user_can('manage_options') ? '✓ 是' : '✗ 否') . "<br>";

// 6. 測試 SettingsService
echo "<h2>6. SettingsService 測試</h2>";
try {
    if (class_exists('BuygoLineNotify\Services\SettingsService')) {
        $settings = \BuygoLineNotify\Services\SettingsService::get_all();
        echo "get_all() 執行成功: ✓<br>";
        echo "設定欄位數量: " . count($settings) . "<br>";
    } else {
        echo "SettingsService 類別不存在<br>";
    }
} catch (Exception $e) {
    echo "錯誤: " . $e->getMessage() . "<br>";
}

// 7. 測試視圖檔案載入
echo "<h2>7. 視圖檔案載入測試</h2>";
$view_file = BuygoLineNotify_PLUGIN_DIR . 'includes/admin/views/settings-page.php';
if (file_exists($view_file)) {
    echo "視圖檔案存在: ✓<br>";
    try {
        ob_start();
        $settings = ['channel_access_token' => 'test'];
        $webhook_url = 'https://test.buygo.me/webhook';
        $message = '';
        include $view_file;
        $output = ob_get_clean();
        echo "視圖檔案載入成功: ✓<br>";
        echo "輸出長度: " . strlen($output) . " bytes<br>";
    } catch (Exception $e) {
        ob_end_clean();
        echo "視圖檔案載入失敗: " . $e->getMessage() . "<br>";
    }
} else {
    echo "視圖檔案不存在: ✗<br>";
}

echo "<hr>";
echo "<p><a href='https://test.buygo.me/wp-admin/admin.php?page=buygo-line-notify-settings'>前往設定頁面</a></p>";
