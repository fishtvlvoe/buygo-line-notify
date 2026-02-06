<?php
/**
 * 測試 UserListColumn 修復後的功能
 */

require_once __DIR__ . '/../../../wp-load.php';

echo "=== 測試 UserListColumn 修復 ===\n\n";

// 測試 LineUserService::getBinding() 方法
echo "1. 測試 LineUserService::getBinding() 方法:\n";

$test_user_id = 1; // 測試 admin 用戶
$binding = \BuygoLineNotify\Services\LineUserService::getBinding($test_user_id);

if ($binding) {
    echo "   ✓ 成功取得綁定資料\n";
    echo "   - user_id: {$binding->user_id}\n";
    echo "   - identifier: {$binding->identifier}\n";
    echo "   - link_date: {$binding->link_date}\n";
    echo "   - 物件存取 link_date: " . (isset($binding->link_date) ? '✓' : '✗') . "\n";
} else {
    echo "   - 用戶 {$test_user_id} 未綁定 LINE\n";
}

echo "\n2. 測試所有用戶的 LINE 綁定狀態:\n";

$users = get_users(['number' => 5]);
foreach ($users as $user) {
    $is_linked = \BuygoLineNotify\Services\LineUserService::isUserLinked($user->ID);
    
    if ($is_linked) {
        $binding = \BuygoLineNotify\Services\LineUserService::getBinding($user->ID);
        $display_name = get_user_meta($user->ID, 'buygo_line_display_name', true);
        echo "   ✓ {$user->user_login} (ID: {$user->ID}): 已綁定\n";
        echo "     - LINE UID: {$binding->identifier}\n";
        echo "     - 顯示名稱: {$display_name}\n";
        echo "     - 綁定日期: {$binding->link_date}\n";
    } else {
        echo "   - {$user->user_login} (ID: {$user->ID}): 未綁定\n";
    }
}

echo "\n3. 測試 render_line_column 方法(模擬):\n";

// 測試第一個用戶
if (!empty($users)) {
    $user = $users[0];
    echo "   測試用戶: {$user->user_login} (ID: {$user->ID})\n";
    
    try {
        // 模擬 render_line_column 的邏輯
        $is_linked = \BuygoLineNotify\Services\LineUserService::isUserLinked($user->ID);
        
        if ($is_linked) {
            $line_data = \BuygoLineNotify\Services\LineUserService::getBinding($user->ID);
            $display_name = get_user_meta($user->ID, 'buygo_line_display_name', true);
            
            echo "   ✓ 成功取得資料\n";
            echo "     - getBinding() 返回: " . (is_object($line_data) ? 'object ✓' : 'not object ✗') . "\n";
            echo "     - link_date 欄位: " . (isset($line_data->link_date) ? '✓ ' . $line_data->link_date : '✗ 不存在') . "\n";
            echo "     - 顯示名稱: " . ($display_name ?: 'LINE 用戶') . "\n";
        } else {
            echo "   - 用戶未綁定 LINE\n";
        }
    } catch (\Exception $e) {
        echo "   ✗ 錯誤: {$e->getMessage()}\n";
    }
}

echo "\n=== 測試完成 ===\n";
