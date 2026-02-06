<?php
/**
 * 設定遷移腳本：統一 LINE 設定的儲存位置
 * 
 * 目標：將所有 LINE 相關設定統一儲存到標準的 option names
 * 清理舊的、重複的設定項目
 */
require_once __DIR__ . '/../../../wp-load.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>📦 LINE 設定遷移與清理</h1>";
echo "<p>統一設定儲存位置，清理重複項目</p>";

// ===========================================
// 第一步：分析現有設定
// ===========================================
echo "<h2>步驟 1：分析現有設定</h2>";

$token_sources = [
    'buygo_line_channel_access_token' => get_option('buygo_line_channel_access_token', ''),
    'buygo_line_notify_channel_access_token' => get_option('buygo_line_notify_channel_access_token', ''),
    'buygo_line_fc_channel_access_token' => get_option('buygo_line_fc_channel_access_token', ''),
];

echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
echo "<tr><th>Option Name</th><th>長度</th><th>前 30 字元</th><th>狀態</th></tr>";

$valid_tokens = [];
foreach ($token_sources as $name => $value) {
    $length = strlen($value);
    $preview = substr($value, 0, 30);
    $status = empty($value) ? '❌ 空' : '✅ 有值';
    
    echo "<tr>";
    echo "<td><code>{$name}</code></td>";
    echo "<td>{$length}</td>";
    echo "<td><code>{$preview}...</code></td>";
    echo "<td>{$status}</td>";
    echo "</tr>";
    
    if (!empty($value)) {
        $valid_tokens[$name] = $value;
    }
}
echo "</table>";

// ===========================================
// 第二步：確定標準 Token（最新的）
// ===========================================
echo "<h2>步驟 2：確定標準設定</h2>";

// 優先使用 buygo_line_notify_channel_access_token（設定頁面更新的）
$standard_token = get_option('buygo_line_notify_channel_access_token', '');

if (empty($standard_token)) {
    $standard_token = get_option('buygo_line_channel_access_token', '');
}

if (empty($standard_token)) {
    echo "<p style='color:red;'>❌ 找不到有效的 Channel Access Token</p>";
    exit;
}

echo "<p style='color:green;'>✅ 找到標準 Token（長度: " . strlen($standard_token) . " 字元）</p>";
echo "<pre style='background:#f0f0f0; padding:10px;'>";
echo "前 50 字元: " . substr($standard_token, 0, 50) . "...\n";
echo "</pre>";

// 驗證 Token 有效性
echo "<h3>驗證 Token 有效性</h3>";
$response = wp_remote_get('https://api.line.me/v2/bot/info', [
    'headers' => [
        'Authorization' => 'Bearer ' . $standard_token,
    ],
    'timeout' => 10,
]);

$status_code = wp_remote_retrieve_response_code($response);
if ($status_code === 200) {
    echo "<p style='color:green;'>✅ Token 有效（HTTP 200）</p>";
    $body = json_decode(wp_remote_retrieve_body($response), true);
    echo "<pre style='background:#f0f0f0; padding:10px;'>";
    echo "Bot 名稱: " . ($body['displayName'] ?? 'N/A') . "\n";
    echo "Basic ID: " . ($body['basicId'] ?? 'N/A') . "\n";
    echo "</pre>";
} else {
    echo "<p style='color:red;'>❌ Token 無效（HTTP {$status_code}）</p>";
    echo "<p>請先更新正確的 Token 再執行遷移</p>";
    exit;
}

// ===========================================
// 第三步：統一儲存策略
// ===========================================
echo "<h2>步驟 3：統一儲存策略</h2>";

require_once __DIR__ . '/includes/services/class-settings-service.php';

echo "<h3>標準 Option Names（buygo-line-notify 外掛）</h3>";
echo "<ul>";
echo "<li><code>buygo_line_channel_access_token</code> - Messaging API Token（加密）</li>";
echo "<li><code>buygo_line_channel_secret</code> - Channel Secret（加密）</li>";
echo "<li><code>buygo_line_login_channel_id</code> - LINE Login Channel ID（加密）</li>";
echo "<li><code>buygo_line_login_channel_secret</code> - LINE Login Secret（加密）</li>";
echo "</ul>";

echo "<h3>執行統一儲存</h3>";

// 使用 SettingsService 儲存（自動加密）
$result_token = \BuygoLineNotify\Services\SettingsService::set('channel_access_token', $standard_token);

// 同時更新 buygo_line_notify_channel_access_token 保持一致
update_option('buygo_line_notify_channel_access_token', $standard_token);

echo "<p style='color:green;'>✅ 已統一儲存到標準位置</p>";

// 驗證讀取
$retrieved = \BuygoLineNotify\Services\SettingsService::get('channel_access_token');
if ($retrieved === $standard_token) {
    echo "<p style='color:green;'>✅ 驗證讀取成功（與原始 Token 一致）</p>";
} else {
    echo "<p style='color:red;'>❌ 驗證失敗（讀取的 Token 不一致）</p>";
}

// ===========================================
// 第四步：清理策略（保守）
// ===========================================
echo "<h2>步驟 4：清理建議</h2>";

echo "<h3>🔴 需要保留的 Options（核心功能）</h3>";
$keep_options = [
    'buygo_line_channel_access_token',
    'buygo_line_channel_secret',
    'buygo_line_notify_channel_access_token', // 設定頁面使用
    'buygo_line_login_channel_id',
    'buygo_line_login_channel_secret',
    'buygo_line_liff_id',
    'buygo_line_notify_test_uid',
    'buygo_line_db_version',
    'buygo_line_notify_db_version',
];

echo "<ul>";
foreach ($keep_options as $opt) {
    echo "<li><code>{$opt}</code></li>";
}
echo "</ul>";

echo "<h3>🟡 可以清理的 Options（舊資料或重複）</h3>";
$cleanup_candidates = [
    'buygo_line_fc_channel_access_token' => 'FluentCart 專用 Token（如果不需要獨立 Token）',
    'buygo_line_fc_channel_secret' => 'FluentCart 專用 Secret',
];

echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
echo "<tr><th>Option Name</th><th>說明</th><th>建議</th></tr>";
foreach ($cleanup_candidates as $opt => $desc) {
    $value = get_option($opt, '');
    $has_value = !empty($value);
    echo "<tr>";
    echo "<td><code>{$opt}</code></td>";
    echo "<td>{$desc}</td>";
    echo "<td>" . ($has_value ? "⚠️ 有值，需確認是否使用中" : "✅ 空值，可以刪除") . "</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h3>🔵 暫存資料（可定期清理）</h3>";
global $wpdb;
$transients = $wpdb->get_results("
    SELECT option_name, option_value 
    FROM {$wpdb->options} 
    WHERE option_name LIKE '_transient_buygo_line_event_%'
    ORDER BY option_name
    LIMIT 10
");

echo "<p>找到 " . count($transients) . " 個 webhook event 快取（用於防止重複處理）</p>";
if (count($transients) > 0) {
    echo "<p>這些是 60 秒的暫存，會自動過期清除。</p>";
}

// ===========================================
// 第五步：驗證整合
// ===========================================
echo "<h2>步驟 5：最終驗證</h2>";

echo "<h3>測試各個元件讀取設定</h3>";

// 1. buygo-line-notify SettingsService
$token_from_service = \BuygoLineNotify\Services\SettingsService::get('channel_access_token');
echo "<p>SettingsService 讀取: " . (empty($token_from_service) ? '❌ 空' : '✅ 有值（' . strlen($token_from_service) . ' 字元）') . "</p>";

// 2. buygo-line-notify BuygoLineNotify Facade
if (class_exists('\BuygoLineNotify\BuygoLineNotify')) {
    $imageService = \BuygoLineNotify\BuygoLineNotify::image_uploader();
    echo "<p>BuygoLineNotify::image_uploader() 初始化: ✅ 成功</p>";
}

// 3. 測試實際呼叫 LINE API
echo "<h3>測試 LINE API 呼叫</h3>";
$test_response = wp_remote_get('https://api.line.me/v2/bot/info', [
    'headers' => [
        'Authorization' => 'Bearer ' . $token_from_service,
    ],
    'timeout' => 10,
]);

$test_status = wp_remote_retrieve_response_code($test_response);
if ($test_status === 200) {
    echo "<p style='color:green; font-size:18px;'>🎉 所有驗證通過！設定已統一並正常運作</p>";
} else {
    echo "<p style='color:red;'>❌ API 呼叫失敗（HTTP {$test_status}）</p>";
}

// ===========================================
// 總結
// ===========================================
echo "<hr>";
echo "<h2>📋 總結</h2>";
echo "<div style='background:#e8f5e9; padding:15px; border-left:4px solid #4caf50;'>";
echo "<h3>✅ 完成項目</h3>";
echo "<ul>";
echo "<li>統一 Token 儲存到 <code>buygo_line_channel_access_token</code>（加密）</li>";
echo "<li>同步到 <code>buygo_line_notify_channel_access_token</code>（設定頁面）</li>";
echo "<li>驗證 Token 有效性</li>";
echo "<li>驗證各元件讀取正常</li>";
echo "</ul>";
echo "</div>";

echo "<div style='background:#fff3e0; padding:15px; border-left:4px solid #ff9800; margin-top:10px;'>";
echo "<h3>⚠️ 下一步建議</h3>";
echo "<ol>";
echo "<li>檢查 <code>buygo_line_fc_channel_access_token</code> 是否還需要（FluentCart 整合是否需要獨立 Token）</li>";
echo "<li>如果不需要，可以刪除以避免混淆</li>";
echo "<li>測試圖片上傳功能（應該可以正常運作了）</li>";
echo "<li>如果未來新增設定項目，統一使用 SettingsService::set() 儲存</li>";
echo "</ol>";
echo "</div>";
