<?php
/**
 * 簡單測試 - 只測試資料庫連線
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 簡單測試 ===\n\n";

// 測試 1: 基本 PHP
echo "1. PHP 版本: " . PHP_VERSION . "\n\n";

// 測試 2: 載入 WordPress
echo "2. 載入 WordPress...\n";
try {
    require_once '/Users/fishtv/Local Sites/buygo/app/public/wp-load.php';
    echo "   ✓ WordPress 載入成功\n\n";
} catch (Exception $e) {
    echo "   ✗ 錯誤: " . $e->getMessage() . "\n\n";
    exit(1);
}

// 測試 3: 資料庫連線
echo "3. 測試資料庫連線...\n";
global $wpdb;
try {
    $result = $wpdb->get_var("SELECT 1");
    echo "   ✓ 資料庫連線成功 (結果: {$result})\n\n";
} catch (Exception $e) {
    echo "   ✗ 錯誤: " . $e->getMessage() . "\n\n";
    exit(1);
}

// 測試 4: 檢查資料表
echo "4. 檢查 wp_buygo_line_users 資料表...\n";
$table_name = $wpdb->prefix . 'buygo_line_users';
$table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
echo "   存在: " . ($table_exists ? "✓ 是" : "✗ 否") . "\n";

if ($table_exists) {
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    echo "   資料筆數: {$count}\n";
}

echo "\n=== 測試完成 ===\n";
