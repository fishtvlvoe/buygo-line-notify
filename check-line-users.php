<?php
/**
 * 檢查 LINE 使用者綁定狀況
 */
require_once __DIR__ . '/../../../wp-load.php';

global $wpdb;

echo "=== 檢查 wp_buygo_line_users 資料表 ===\n\n";

// 1. 檢查資料表是否存在
$table_name = $wpdb->prefix . 'buygo_line_users';
$table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;

if (!$table_exists) {
    echo "❌ 資料表不存在: {$table_name}\n";
    exit;
}

echo "✅ 資料表存在: {$table_name}\n\n";

// 2. 檢查資料表內容
$line_users = $wpdb->get_results("SELECT * FROM {$table_name}");

echo "LINE 綁定記錄數: " . count($line_users) . "\n\n";

if (count($line_users) > 0) {
    echo "綁定記錄:\n";
    foreach ($line_users as $line_user) {
        echo "- ID: {$line_user->ID}\n";
        echo "  user_id: {$line_user->user_id}\n";
        echo "  identifier: {$line_user->identifier}\n";
        echo "  type: {$line_user->type}\n";
        echo "  link_date: {$line_user->link_date}\n\n";
    }
}

// 3. 檢查所有 WordPress 使用者
echo "\n=== 所有 WordPress 使用者 ===\n\n";

$all_users = get_users();
echo "總使用者數: " . count($all_users) . "\n\n";

foreach ($all_users as $user) {
    $is_linked = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d",
        $user->ID
    ));
    
    $status = $is_linked > 0 ? "✅ 已綁定 LINE" : "❌ 未綁定 LINE";
    echo "- ID: {$user->ID}, 帳號: {$user->user_login}, 狀態: {$status}\n";
}

// 4. 測試篩選邏輯
echo "\n\n=== 測試篩選邏輯 ===\n\n";

// 測試「已綁定」篩選
$linked_sql = "
    SELECT u.ID, u.user_login 
    FROM {$wpdb->users} u
    WHERE EXISTS (
        SELECT 1 FROM {$table_name}
        WHERE {$table_name}.user_id = u.ID
    )
";
$linked_users = $wpdb->get_results($linked_sql);
echo "已綁定 LINE 的使用者 (EXISTS 查詢):\n";
foreach ($linked_users as $user) {
    echo "- ID: {$user->ID}, 帳號: {$user->user_login}\n";
}

echo "\n";

// 測試「未綁定」篩選
$not_linked_sql = "
    SELECT u.ID, u.user_login 
    FROM {$wpdb->users} u
    WHERE NOT EXISTS (
        SELECT 1 FROM {$table_name}
        WHERE {$table_name}.user_id = u.ID
    )
";
$not_linked_users = $wpdb->get_results($not_linked_sql);
echo "未綁定 LINE 的使用者 (NOT EXISTS 查詢):\n";
foreach ($not_linked_users as $user) {
    echo "- ID: {$user->ID}, 帳號: {$user->user_login}\n";
}

echo "\n完成檢查\n";
