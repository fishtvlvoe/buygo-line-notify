<?php
/**
 * 修正 Token：從 buygo_line_notify_channel_access_token 複製到 buygo_line_channel_access_token
 */
require_once __DIR__ . '/../../../wp-load.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔧 修正 Channel Access Token</h1>";

// 1. 讀取正確的 token
$correct_token = get_option('buygo_line_notify_channel_access_token', '');

echo "<h2>步驟 1：讀取正確的 Token</h2>";
if (empty($correct_token)) {
    echo "<p style='color:red;'>❌ buygo_line_notify_channel_access_token 為空</p>";
    exit;
}
echo "<pre style='background:#f0f0f0; padding:10px;'>";
echo "Token 前 50 字元: " . substr($correct_token, 0, 50) . "...\n";
echo "Token 長度: " . strlen($correct_token) . " 字元";
echo "</pre>";

// 2. 加密（使用 SettingsService）
require_once __DIR__ . '/includes/services/class-settings-service.php';

$encrypted_token = \BuygoLineNotify\Services\SettingsService::encrypt($correct_token);

echo "<h2>步驟 2：加密 Token</h2>";
echo "<pre style='background:#f0f0f0; padding:10px;'>";
echo "加密後前 50 字元: " . substr($encrypted_token, 0, 50) . "...\n";
echo "加密後長度: " . strlen($encrypted_token) . " 字元";
echo "</pre>";

// 3. 更新到正確的 option
$result = update_option('buygo_line_channel_access_token', $encrypted_token);

echo "<h2>步驟 3：更新資料庫</h2>";
if ($result) {
    echo "<p style='color:green;'>✅ 成功更新 buygo_line_channel_access_token</p>";
} else {
    echo "<p style='color:orange;'>⚠️ 值未變更（可能已經是正確的）</p>";
}

// 4. 驗證讀取
$retrieved_token = \BuygoLineNotify\Services\SettingsService::get('channel_access_token');

echo "<h2>步驟 4：驗證讀取</h2>";
echo "<pre style='background:#f0f0f0; padding:10px;'>";
echo "讀取到的 Token 前 50 字元: " . substr($retrieved_token, 0, 50) . "...\n";
echo "讀取到的 Token 長度: " . strlen($retrieved_token) . " 字元\n";
echo "\n比對結果: " . ($retrieved_token === $correct_token ? "✅ 一致" : "❌ 不一致");
echo "</pre>";

// 5. 測試 LINE API
echo "<h2>步驟 5：測試 LINE API</h2>";
$response = wp_remote_get('https://api.line.me/v2/bot/info', [
    'headers' => [
        'Authorization' => 'Bearer ' . $retrieved_token,
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
    echo "<p style='color:green; font-size:20px;'>🎉 Token 已修正並驗證成功！</p>";
} else {
    echo "<p style='color:red;'>❌ Token 驗證失敗（HTTP " . $status_code . "）</p>";
}
