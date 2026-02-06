<?php
/**
 * 檢查所有 LINE 相關的設定
 */
require_once __DIR__ . '/../../../wp-load.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔍 LINE 設定檢查</h1>";

// 1. 列出所有可能的 option 名稱
$possible_options = [
    'buygo_line_notify_settings',
    'buygo_line_settings',
    'line_channel_access_token',
    'channel_access_token',
    'buygo_line_channel_access_token',
];

echo "<h2>檢查所有可能的 option:</h2>";
foreach ($possible_options as $option_name) {
    $value = get_option($option_name, null);
    echo "<h3>" . htmlspecialchars($option_name) . "</h3>";
    if ($value === null || $value === false) {
        echo "<p style='color:#999;'>未設定</p>";
    } else {
        echo "<pre style='background:#f0f0f0; padding:10px; max-height:200px; overflow:auto;'>";
        print_r($value);
        echo "</pre>";
    }
}

// 2. 搜尋資料庫中所有包含 "line" 或 "token" 的 option
global $wpdb;
$query = "SELECT option_name, option_value FROM {$wpdb->options} 
          WHERE option_name LIKE '%line%' 
             OR option_name LIKE '%token%' 
          ORDER BY option_name";
$results = $wpdb->get_results($query);

echo "<h2>資料庫中所有 LINE/Token 相關設定:</h2>";
if (empty($results)) {
    echo "<p style='color:#999;'>找不到相關設定</p>";
} else {
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse; width:100%;'>";
    echo "<tr><th>Option Name</th><th>Value (前 100 字元)</th></tr>";
    foreach ($results as $row) {
        $preview = substr($row->option_value, 0, 100);
        if (strlen($row->option_value) > 100) {
            $preview .= '...';
        }
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row->option_name) . "</td>";
        echo "<td><code>" . htmlspecialchars($preview) . "</code></td>";
        echo "</tr>";
    }
    echo "</table>";
}
