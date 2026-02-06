<?php
/**
 * 檢查 Profile Sync 衝突日誌
 *
 * 使用方式：php check-conflict-log.php
 */

require_once '/Users/fishtv/Local Sites/buygo/app/public/wp-load.php';

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo " Profile Sync 衝突日誌檢查\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// 取得所有衝突日誌
global $wpdb;
$logs = $wpdb->get_results("
    SELECT option_name, option_value
    FROM {$wpdb->options}
    WHERE option_name LIKE 'buygo_line_conflict_log_%'
    ORDER BY option_name
");

if (empty($logs)) {
    echo "✓ 目前沒有任何衝突日誌記錄\n";
    echo "  （這是正常的，只有使用 'manual' 策略時才會記錄衝突）\n\n";
    exit(0);
}

echo "找到 " . count($logs) . " 個用戶的衝突日誌：\n\n";

foreach ($logs as $log) {
    $user_id = str_replace('buygo_line_conflict_log_', '', $log->option_name);
    $conflicts = maybe_unserialize($log->option_value);

    // 取得用戶資訊
    $user = get_user_by('id', $user_id);
    $user_name = $user ? $user->display_name : '未知用戶';

    echo "┌─────────────────────────────────────────────────────────\n";
    echo "│ User ID: {$user_id} ({$user_name})\n";
    echo "├─────────────────────────────────────────────────────────\n";

    if (is_array($conflicts)) {
        echo "│ 衝突記錄數量: " . count($conflicts) . "\n";
        echo "│\n";

        foreach ($conflicts as $index => $conflict) {
            $num = $index + 1;
            echo "│ [{$num}] " . ($conflict['timestamp'] ?? 'N/A') . "\n";
            echo "│     欄位: " . ($conflict['field'] ?? 'N/A') . "\n";
            echo "│     WordPress 值: " . ($conflict['current_value'] ?? '(空)') . "\n";
            echo "│     LINE 值: " . ($conflict['new_value'] ?? '(空)') . "\n";
            echo "│\n";
        }
    } else {
        echo "│ [錯誤] 日誌格式不正確\n";
        echo "│ 原始內容: " . substr($log->option_value, 0, 100) . "...\n";
    }

    echo "└─────────────────────────────────────────────────────────\n\n";
}

// 取得所有同步日誌（非衝突）
$sync_logs = $wpdb->get_results("
    SELECT option_name, option_value
    FROM {$wpdb->options}
    WHERE option_name LIKE 'buygo_line_sync_log_%'
    ORDER BY option_name
");

if (!empty($sync_logs)) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo " Profile Sync 同步日誌\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

    echo "找到 " . count($sync_logs) . " 個用戶的同步日誌：\n\n";

    foreach ($sync_logs as $log) {
        $user_id = str_replace('buygo_line_sync_log_', '', $log->option_name);
        $syncs = maybe_unserialize($log->option_value);

        $user = get_user_by('id', $user_id);
        $user_name = $user ? $user->display_name : '未知用戶';

        echo "┌─────────────────────────────────────────────────────────\n";
        echo "│ User ID: {$user_id} ({$user_name})\n";
        echo "├─────────────────────────────────────────────────────────\n";

        if (is_array($syncs)) {
            echo "│ 同步記錄數量: " . count($syncs) . " (最多保留 10 筆)\n";
            echo "│\n";

            // 只顯示最近 3 筆
            $recent_syncs = array_slice($syncs, -3);
            foreach ($recent_syncs as $index => $sync) {
                echo "│ [" . ($index + 1) . "] " . ($sync['timestamp'] ?? 'N/A') . "\n";
                echo "│     動作: " . ($sync['action'] ?? 'N/A') . "\n";
                echo "│     變更欄位: " . implode(', ', $sync['changed_fields'] ?? []) . "\n";
                echo "│\n";
            }

            if (count($syncs) > 3) {
                echo "│ ... 還有 " . (count($syncs) - 3) . " 筆較早的記錄\n";
            }
        }

        echo "└─────────────────────────────────────────────────────────\n\n";
    }
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "檢查完成\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
