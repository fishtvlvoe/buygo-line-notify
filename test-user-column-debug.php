<?php
/**
 * 測試 UserListColumn 功能
 * 診斷 users.php 頁面 503 錯誤
 */

require_once '/Users/fishtv/Local Sites/buygo/app/public/wp-load.php';

echo "=== BuyGo Line Notify - UserListColumn 診斷 ===\n\n";

// 1. 檢查資料表是否存在
global $wpdb;
$table_name = $wpdb->prefix . 'buygo_line_users';
$table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;

echo "1. 資料表狀態:\n";
echo "   Table: {$table_name}\n";
echo "   存在: " . ($table_exists ? "✓ 是" : "✗ 否") . "\n\n";

if ($table_exists) {
    // 檢查資料表結構
    $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
    echo "   欄位:\n";
    foreach ($columns as $col) {
        echo "   - {$col->Field} ({$col->Type})\n";
    }
    echo "\n";
    
    // 檢查資料筆數
    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    echo "   資料筆數: {$count}\n\n";
}

// 2. 檢查 UserListColumn 類別是否存在
$class_exists = class_exists('BuygoLineNotify\Admin\UserListColumn');
echo "2. UserListColumn 類別:\n";
echo "   存在: " . ($class_exists ? "✓ 是" : "✗ 否") . "\n\n";

// 3. 測試 LineUserService
echo "3. LineUserService 測試:\n";
try {
    $service_exists = class_exists('BuygoLineNotify\Services\LineUserService');
    echo "   類別存在: " . ($service_exists ? "✓ 是" : "✗ 否") . "\n";
    
    if ($service_exists) {
        // 測試查詢用戶 1
        $is_linked = \BuygoLineNotify\Services\LineUserService::isUserLinked(1);
        echo "   isUserLinked(1): " . ($is_linked ? "已綁定" : "未綁定") . "\n";
        
        $user_data = \BuygoLineNotify\Services\LineUserService::getUser(1);
        echo "   getUser(1): " . ($user_data ? "有資料" : "無資料") . "\n";
    }
} catch (Exception $e) {
    echo "   錯誤: " . $e->getMessage() . "\n";
    echo "   Trace: " . $e->getTraceAsString() . "\n";
}
echo "\n";

// 4. 模擬 render_line_column 執行
echo "4. 模擬 render_line_column 執行:\n";
try {
    if ($class_exists) {
        $output = \BuygoLineNotify\Admin\UserListColumn::render_line_column('', 'line_binding', 1);
        echo "   輸出長度: " . strlen($output) . " bytes\n";
        echo "   內容預覽: " . substr(strip_tags($output), 0, 50) . "...\n";
    }
} catch (Exception $e) {
    echo "   錯誤: " . $e->getMessage() . "\n";
    echo "   檔案: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
echo "\n";

// 5. 檢查 WordPress debug.log
echo "5. 檢查 WordPress debug.log:\n";
$debug_log = WP_CONTENT_DIR . '/debug.log';
echo "   位置: {$debug_log}\n";
if (file_exists($debug_log)) {
    $lines = file($debug_log);
    $recent_errors = array_slice($lines, -50);
    echo "   最近 50 筆包含 'buygo', 'UserListColumn', 'Fatal', 或 '503' 的錯誤:\n";
    $found = false;
    foreach ($recent_errors as $line) {
        if (stripos($line, 'buygo') !== false || 
            stripos($line, 'userlistcolumn') !== false ||
            stripos($line, 'fatal') !== false ||
            stripos($line, '503') !== false) {
            echo "   > " . trim($line) . "\n";
            $found = true;
        }
    }
    if (!$found) {
        echo "   (未發現相關錯誤)\n";
    }
} else {
    echo "   debug.log 不存在\n";
}

echo "\n=== 診斷完成 ===\n";
