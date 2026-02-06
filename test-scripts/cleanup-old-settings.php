<?php
/**
 * 清理舊的、不再使用的 LINE 設定
 */
require_once __DIR__ . '/../../../wp-load.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🧹 清理舊設定</h1>";

// 需要清理的 options（確認不再使用）
$cleanup_list = [
    'buygo_line_fc_channel_access_token',
    'buygo_line_fc_channel_secret',
    'buygo_line_fc_default_category',
    'buygo_line_fc_default_tax_rate',
    'buygo_line_fc_enable_tax',
    'buygo_line_fc_payment_deadline',
    'buygo_line_fc_version',
    'buygo_line_fc_auto_create_category',
];

echo "<h2>準備清理的項目</h2>";
echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
echo "<tr><th>Option Name</th><th>當前值</th><th>狀態</th></tr>";

$to_delete = [];
foreach ($cleanup_list as $option) {
    $value = get_option($option, false);
    $has_value = ($value !== false && $value !== '');
    
    echo "<tr>";
    echo "<td><code>{$option}</code></td>";
    
    if ($has_value) {
        $preview = is_string($value) ? substr($value, 0, 50) : print_r($value, true);
        echo "<td><code>" . htmlspecialchars($preview) . "...</code></td>";
        echo "<td style='color:orange;'>⚠️ 有值</td>";
        $to_delete[] = $option;
    } else {
        echo "<td style='color:#999;'>空或不存在</td>";
        echo "<td style='color:green;'>✅ 已清理</td>";
    }
    
    echo "</tr>";
}
echo "</table>";

if (empty($to_delete)) {
    echo "<p style='color:green;'>✅ 所有舊設定已清理完成</p>";
    exit;
}

echo "<h2>執行清理</h2>";
echo "<p>即將刪除 " . count($to_delete) . " 個不再使用的設定項目</p>";

foreach ($to_delete as $option) {
    $result = delete_option($option);
    if ($result) {
        echo "<p style='color:green;'>✅ 已刪除: <code>{$option}</code></p>";
    } else {
        echo "<p style='color:red;'>❌ 刪除失敗: <code>{$option}</code></p>";
    }
}

echo "<hr>";
echo "<h2>驗證清理結果</h2>";

$remaining = [];
foreach ($cleanup_list as $option) {
    $value = get_option($option, false);
    if ($value !== false && $value !== '') {
        $remaining[] = $option;
    }
}

if (empty($remaining)) {
    echo "<p style='color:green; font-size:18px;'>🎉 所有舊設定已成功清理！</p>";
} else {
    echo "<p style='color:red;'>❌ 仍有 " . count($remaining) . " 個項目未清理：</p>";
    echo "<ul>";
    foreach ($remaining as $opt) {
        echo "<li><code>{$opt}</code></li>";
    }
    echo "</ul>";
}

echo "<hr>";
echo "<h2>📋 清理完成</h2>";
echo "<div style='background:#e8f5e9; padding:15px; border-left:4px solid #4caf50;'>";
echo "<h3>✅ 資料庫已整理</h3>";
echo "<p>已移除舊的 FluentCart 整合設定（這些功能已經整合到主要的 LINE 設定中）</p>";
echo "</div>";

echo "<div style='background:#e3f2fd; padding:15px; border-left:4px solid #2196f3; margin-top:10px;'>";
echo "<h3>📌 當前標準設定</h3>";
echo "<ul>";
echo "<li><code>buygo_line_channel_access_token</code> - Messaging API Token（主要，加密）</li>";
echo "<li><code>buygo_line_notify_channel_access_token</code> - 設定頁面使用（同步備份）</li>";
echo "<li><code>buygo_line_channel_secret</code> - Channel Secret（加密）</li>";
echo "<li><code>buygo_line_login_channel_id</code> - LINE Login Channel ID（加密）</li>";
echo "<li><code>buygo_line_login_channel_secret</code> - LINE Login Secret（加密）</li>";
echo "</ul>";
echo "</div>";
