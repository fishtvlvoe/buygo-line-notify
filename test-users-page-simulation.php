<?php
/**
 * 模擬 users.php 頁面載入
 * 測試 UserListColumn 是否正常工作
 */

// 設定為後台環境
define('WP_ADMIN', true);
require_once '/Users/fishtv/Local Sites/buygo/app/public/wp-load.php';

// 確保所有外掛已載入
do_action('plugins_loaded');
do_action('init');

echo "=== 模擬 users.php 頁面載入 ===\n\n";

// 檢查 UserListColumn 類別
$class_exists = class_exists('BuygoLineNotify\Admin\UserListColumn');
echo "1. UserListColumn 類別: " . ($class_exists ? "✓ 存在" : "✗ 不存在") . "\n\n";

if (!$class_exists) {
    echo "   錯誤: UserListColumn 類別未載入\n";
    echo "   可能原因:\n";
    echo "   - 外掛未啟用\n";
    echo "   - 類別檔案路徑錯誤\n";
    echo "   - PHP 語法錯誤\n";
    exit(1);
}

// 測試欄位新增
$columns = [];
$columns = apply_filters('manage_users_columns', $columns);
echo "2. 欄位新增測試:\n";
echo "   欄位數量: " . count($columns) . "\n";
echo "   包含 line_binding: " . (isset($columns['line_binding']) ? "✓ 是" : "✗ 否") . "\n\n";

// 測試欄位渲染
echo "3. 欄位渲染測試:\n";
try {
    $output = apply_filters('manage_users_custom_column', '', 'line_binding', 1);
    echo "   User ID 1 輸出長度: " . strlen($output) . " bytes\n";
    echo "   輸出預覽: " . substr(strip_tags($output), 0, 80) . "\n\n";
} catch (Exception $e) {
    echo "   錯誤: " . $e->getMessage() . "\n\n";
}

// 檢查資料表
global $wpdb;
$table_name = $wpdb->prefix . 'buygo_line_users';
$count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
echo "4. 資料表狀態:\n";
echo "   表名: {$table_name}\n";
echo "   資料筆數: {$count}\n\n";

// 列出所有資料
if ($count > 0) {
    $records = $wpdb->get_results("SELECT * FROM {$table_name}");
    echo "5. 資料內容:\n";
    foreach ($records as $record) {
        echo "   - User ID: {$record->user_id}\n";
        echo "     LINE UID: {$record->line_uid}\n";
        echo "     LINE Name: {$record->line_name}\n";
        echo "     Link Date: {$record->link_date}\n\n";
    }
}

echo "=== 測試完成 ===\n";
