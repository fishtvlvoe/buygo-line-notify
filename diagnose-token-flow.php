<?php
/**
 * 診斷 Token 讀取流程
 */
require_once __DIR__ . '/../../../wp-load.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔍 Token 讀取流程診斷</h1>";

// 1. 直接從資料庫讀取
echo "<h2>步驟 1：資料庫原始值</h2>";
$db_token_encrypted = get_option('buygo_line_channel_access_token', '');
$db_token_plain = get_option('buygo_line_notify_channel_access_token', '');

echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
echo "<tr><th>Option Name</th><th>長度</th><th>前 30 字元</th></tr>";
echo "<tr><td>buygo_line_channel_access_token</td><td>" . strlen($db_token_encrypted) . "</td><td><code>" . substr($db_token_encrypted, 0, 30) . "...</code></td></tr>";
echo "<tr><td>buygo_line_notify_channel_access_token</td><td>" . strlen($db_token_plain) . "</td><td><code>" . substr($db_token_plain, 0, 30) . "...</code></td></tr>";
echo "</table>";

// 2. 透過 SettingsService 讀取
echo "<h2>步驟 2：SettingsService 讀取</h2>";
require_once __DIR__ . '/includes/services/class-settings-service.php';
$token_from_service = \BuygoLineNotify\Services\SettingsService::get('channel_access_token');

echo "<pre style='background:#f0f0f0; padding:10px;'>";
echo "SettingsService::get('channel_access_token')\n";
echo "長度: " . strlen($token_from_service) . " 字元\n";
echo "前 50 字元: " . substr($token_from_service, 0, 50) . "...\n";
echo "是否為空: " . (empty($token_from_service) ? '❌ 是' : '✅ 否') . "\n";
echo "</pre>";

// 3. 透過 BuygoLineNotify Facade 讀取
echo "<h2>步驟 3：BuygoLineNotify Facade</h2>";
require_once __DIR__ . '/includes/class-buygo-line-notify.php';
$imageService = \BuygoLineNotify\BuygoLineNotify::image_uploader();

// 使用反射取得 private 屬性
$reflection = new ReflectionClass($imageService);
$property = $reflection->getProperty('channel_access_token');
$property->setAccessible(true);
$token_in_service = $property->getValue($imageService);

echo "<pre style='background:#f0f0f0; padding:10px;'>";
echo "ImageUploader 實例中的 Token\n";
echo "長度: " . strlen($token_in_service) . " 字元\n";
echo "前 50 字元: " . substr($token_in_service, 0, 50) . "...\n";
echo "是否為空: " . (empty($token_in_service) ? '❌ 是' : '✅ 否') . "\n";
echo "</pre>";

// 4. 測試實際 API 呼叫
echo "<h2>步驟 4：測試 LINE API</h2>";

foreach ([
    'SettingsService' => $token_from_service,
    'ImageUploader instance' => $token_in_service,
    '資料庫明文值' => $db_token_plain,
] as $source => $token) {
    if (empty($token)) {
        echo "<p><strong>{$source}:</strong> <span style='color:red;'>❌ Token 為空，跳過測試</span></p>";
        continue;
    }
    
    $response = wp_remote_get('https://api.line.me/v2/bot/info', [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
        ],
        'timeout' => 10,
    ]);
    
    $status = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    echo "<p><strong>{$source}:</strong> ";
    if ($status === 200) {
        echo "<span style='color:green;'>✅ HTTP 200 - Token 有效</span>";
        $data = json_decode($body, true);
        echo " (Bot: " . ($data['displayName'] ?? 'N/A') . ")";
    } else {
        echo "<span style='color:red;'>❌ HTTP {$status} - Token 無效</span>";
        echo "<br><code>" . htmlspecialchars(substr($body, 0, 200)) . "</code>";
    }
    echo "</p>";
}

echo "<hr>";
echo "<h2>📋 診斷總結</h2>";

if ($status === 200) {
    echo "<div style='background:#e8f5e9; padding:15px; border-left:4px solid #4caf50;'>";
    echo "<p style='color:green; font-size:18px;'>✅ Token 讀取流程正常</p>";
    echo "<p>所有環節都能正確取得和使用 Token</p>";
    echo "</div>";
} else {
    echo "<div style='background:#ffebee; padding:15px; border-left:4px solid #f44336;'>";
    echo "<p style='color:red; font-size:18px;'>❌ Token 讀取流程異常</p>";
    echo "<p>請檢查：</p>";
    echo "<ul>";
    echo "<li>資料庫中的 Token 是否正確</li>";
    echo "<li>SettingsService 解密是否正常</li>";
    echo "<li>BuygoLineNotify Facade 傳遞 Token 是否正確</li>";
    echo "</ul>";
    echo "</div>";
}
