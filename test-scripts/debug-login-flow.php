<?php
/**
 * Debug LINE Login Flow
 *
 * 檢查 LINE Login 流程的各個環節
 */

require_once '/Users/fishtv/Local Sites/buygo/app/public/wp-load.php';

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo " LINE Login Flow Debug\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// 1. 檢查 LINE Login 設定
echo "1. LINE Login 設定檢查\n";
echo "─────────────────────────────────────────────────────\n";

$channel_id = \BuygoLineNotify\Services\SettingsService::get('line_login_channel_id');
$channel_secret = \BuygoLineNotify\Services\SettingsService::get('line_login_channel_secret');
$callback_url = \BuygoLineNotify\Services\SettingsService::get('line_login_callback_url');

echo "Channel ID: " . ($channel_id ? substr($channel_id, 0, 10) . '...' : '❌ 未設定') . "\n";
echo "Channel Secret: " . ($channel_secret ? substr($channel_secret, 0, 10) . '...' : '❌ 未設定') . "\n";
echo "Callback URL: " . ($callback_url ?: '❌ 未設定') . "\n";

if (!$channel_id || !$channel_secret) {
    echo "\n⚠️  LINE Login 設定不完整！請到後台設定。\n";
    exit(1);
}

echo "\n";

// 2. 測試 authorize API
echo "2. 測試 /login/authorize API\n";
echo "─────────────────────────────────────────────────────\n";

$api_url = rest_url('buygo-line-notify/v1/login/authorize');
$test_url = add_query_arg('redirect_url', urlencode(site_url()), $api_url);

echo "API URL: {$test_url}\n\n";

// 模擬 API 呼叫
$response = wp_remote_get($test_url, [
    'cookies' => $_COOKIE,
]);

if (is_wp_error($response)) {
    echo "❌ API 呼叫失敗：" . $response->get_error_message() . "\n";
    exit(1);
}

$status_code = wp_remote_retrieve_response_code($response);
$body = wp_remote_retrieve_body($response);
$data = json_decode($body, true);

echo "HTTP Status: {$status_code}\n";
echo "Response:\n";
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

if ($status_code === 200 && isset($data['authorize_url'])) {
    echo "\n✅ API 回應正常\n";
    echo "授權 URL: " . substr($data['authorize_url'], 0, 80) . "...\n";
} else {
    echo "\n❌ API 回應異常\n";
    exit(1);
}

echo "\n";

// 3. 檢查 State 儲存
echo "3. 檢查 State 儲存\n";
echo "─────────────────────────────────────────────────────\n";

global $wpdb;
$transients = $wpdb->get_results(
    "SELECT option_name, option_value
     FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_buygo_line_state_%'
     ORDER BY option_id DESC
     LIMIT 5"
);

if (empty($transients)) {
    echo "❌ 沒有找到 state transients\n";
} else {
    echo "找到 " . count($transients) . " 個 state transients：\n";
    foreach ($transients as $t) {
        $state_key = str_replace('_transient_', '', $t->option_name);
        $state_data = maybe_unserialize($t->option_value);

        echo "\n- {$state_key}\n";
        if (is_array($state_data)) {
            echo "  redirect_url: " . ($state_data['redirect_url'] ?? 'N/A') . "\n";
            echo "  created_at: " . ($state_data['created_at'] ?? 'N/A') . "\n";
        }
    }
    echo "\n✅ State 儲存正常\n";
}

echo "\n";

// 4. 檢查登入按鈕服務
echo "4. 檢查登入按鈕服務\n";
echo "─────────────────────────────────────────────────────\n";

$button_text = \BuygoLineNotify\Services\SettingsService::get_login_button_text();
$button_position = \BuygoLineNotify\Services\SettingsService::get_login_button_position();

echo "按鈕文字: {$button_text}\n";
echo "按鈕位置: {$button_position}\n";

echo "\n";

// 5. 檢查 REST API 路由
echo "5. 檢查 REST API 路由\n";
echo "─────────────────────────────────────────────────────\n";

$routes = rest_get_server()->get_routes();
$line_routes = array_filter(array_keys($routes), function($route) {
    return strpos($route, 'buygo-line-notify') !== false;
});

if (empty($line_routes)) {
    echo "❌ 沒有找到 buygo-line-notify 路由\n";
} else {
    echo "找到以下路由：\n";
    foreach ($line_routes as $route) {
        echo "  - {$route}\n";
    }
    echo "\n✅ REST API 路由已註冊\n";
}

echo "\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "除錯完成\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
