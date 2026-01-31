<?php
/**
 * 測試腳本：比對兩個外掛使用的 Channel Access Token
 */
require_once __DIR__ . '/../../../wp-load.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔍 Token 比對測試</h1>";

// 1. 從 buygo-line-notify 取得 Token
$token_line_notify = get_option('buygo_line_notify_settings', []);
$token_line_notify_value = $token_line_notify['channel_access_token'] ?? '';

echo "<h2>buygo-line-notify Token:</h2>";
echo "<pre style='background:#f0f0f0; padding:10px; word-wrap:break-word;'>";
echo "長度: " . strlen($token_line_notify_value) . " 字元\n";
echo "前 50 字元: " . substr($token_line_notify_value, 0, 50) . "...\n";
echo "後 50 字元: ..." . substr($token_line_notify_value, -50) . "\n";
echo "完整 Token: " . htmlspecialchars($token_line_notify_value);
echo "</pre>";

// 2. 從 buygo-plus-one 取得 Token（如果有的話）
$token_buygo = get_option('buygo_line_settings', []);
$token_buygo_value = $token_buygo['channel_access_token'] ?? '';

echo "<h2>buygo-plus-one Token:</h2>";
if (!empty($token_buygo_value)) {
    echo "<pre style='background:#f0f0f0; padding:10px; word-wrap:break-word;'>";
    echo "長度: " . strlen($token_buygo_value) . " 字元\n";
    echo "前 50 字元: " . substr($token_buygo_value, 0, 50) . "...\n";
    echo "後 50 字元: ..." . substr($token_buygo_value, -50) . "\n";
    echo "完整 Token: " . htmlspecialchars($token_buygo_value);
    echo "</pre>";
} else {
    echo "<p style='color:#999;'>未設定 Token</p>";
}

// 3. 比對結果
echo "<h2>比對結果:</h2>";
if (empty($token_line_notify_value)) {
    echo "<p style='color:red;'>❌ buygo-line-notify Token 未設定</p>";
} elseif (empty($token_buygo_value)) {
    echo "<p style='color:orange;'>⚠️ buygo-plus-one Token 未設定（正常，應該使用 buygo-line-notify 的 Token）</p>";
} elseif ($token_line_notify_value === $token_buygo_value) {
    echo "<p style='color:green;'>✅ 兩個 Token 完全一致</p>";
} else {
    echo "<p style='color:red;'>❌ 兩個 Token 不一致</p>";
    echo "<p>差異:</p>";
    echo "<pre style='background:#fff3cd; padding:10px;'>";
    echo "buygo-line-notify 長度: " . strlen($token_line_notify_value) . "\n";
    echo "buygo-plus-one 長度: " . strlen($token_buygo_value) . "\n";
    echo "</pre>";
}

// 4. 測試 Token 有效性（呼叫 LINE API）
echo "<h2>Token 有效性測試:</h2>";
if (!empty($token_line_notify_value)) {
    $response = wp_remote_get('https://api.line.me/v2/bot/info', [
        'headers' => [
            'Authorization' => 'Bearer ' . $token_line_notify_value,
        ],
        'timeout' => 10,
    ]);
    
    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    echo "<pre style='background:#f0f0f0; padding:10px;'>";
    echo "HTTP 狀態碼: " . $status_code . "\n";
    echo "回應內容: " . htmlspecialchars($body) . "\n";
    echo "</pre>";
    
    if ($status_code === 200) {
        echo "<p style='color:green;'>✅ Token 有效</p>";
    } else {
        echo "<p style='color:red;'>❌ Token 無效或已過期（HTTP " . $status_code . "）</p>";
    }
} else {
    echo "<p style='color:red;'>❌ 無法測試，Token 未設定</p>";
}
