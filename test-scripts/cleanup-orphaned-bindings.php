<?php
/**
 * 清理孤立的 LINE 綁定記錄
 *
 * 清除 wp_buygo_line_users 中 WordPress 用戶已不存在的綁定記錄
 */

require_once '/Users/fishtv/Local Sites/buygo/app/public/wp-load.php';

global $wpdb;

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo " 清理孤立的 LINE 綁定記錄\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$bindings_table = $wpdb->prefix . 'buygo_line_users';

// 查詢所有綁定記錄
$bindings = $wpdb->get_results("SELECT * FROM {$bindings_table}", ARRAY_A);

echo "找到 " . count($bindings) . " 筆綁定記錄\n\n";

$orphaned = [];
$valid = [];

foreach ($bindings as $binding) {
    $user = get_user_by('id', $binding['user_id']);

    if (!$user) {
        $orphaned[] = $binding;
        echo "❌ 孤立記錄: user_id={$binding['user_id']}, LINE UID={$binding['line_user_id']}\n";
    } else {
        $valid[] = $binding;
        echo "✅ 有效記錄: user_id={$binding['user_id']} ({$user->user_login}), LINE UID={$binding['line_user_id']}\n";
    }
}

echo "\n";
echo "統計：\n";
echo "─────────────────────────────────────────────────────\n";
echo "有效綁定: " . count($valid) . " 筆\n";
echo "孤立綁定: " . count($orphaned) . " 筆\n";
echo "\n";

if (empty($orphaned)) {
    echo "✅ 沒有孤立的綁定記錄，資料庫狀態良好！\n";
    exit(0);
}

echo "準備清理 " . count($orphaned) . " 筆孤立記錄...\n";
echo "\n";

$deleted = 0;
foreach ($orphaned as $binding) {
    $result = $wpdb->delete(
        $bindings_table,
        ['user_id' => $binding['user_id']],
        ['%d']
    );

    if ($result) {
        $deleted++;
        echo "🗑️  已刪除: user_id={$binding['user_id']}, LINE UID={$binding['line_user_id']}\n";
    } else {
        echo "❌ 刪除失敗: user_id={$binding['user_id']}\n";
    }
}

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "清理完成！已刪除 {$deleted} 筆孤立綁定記錄\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
