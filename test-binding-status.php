<?php
/**
 * 測試 LINE 綁定狀態檢測
 *
 * 使用方式:
 * 1. 以 WordPress 用戶身分登入
 * 2. 訪問: https://test.buygo.me/wp-content/plugins/buygo-line-notify/test-binding-status.php
 */

// 載入 WordPress (使用絕對路徑)
require_once '/Users/fishtv/Local Sites/buygo/app/public/wp-load.php';

// 檢查是否登入
if (!is_user_logged_in()) {
    wp_die('請先登入 WordPress', '未登入', ['response' => 401]);
}

$user_id = get_current_user_id();
$user = get_user_by('id', $user_id);

echo '<h1>LINE 綁定狀態測試</h1>';
echo '<style>
    body { font-family: system-ui, -apple-system, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; }
    table { border-collapse: collapse; margin: 20px 0; width: 100%; }
    th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
    th { background: #f5f5f5; font-weight: 600; }
    .success { color: #06C755; font-weight: bold; }
    .error { color: #dc3545; font-weight: bold; }
    .info { background: #f0f8ff; padding: 16px; border-left: 4px solid #2196F3; margin: 20px 0; border-radius: 4px; }
    .section { background: white; padding: 24px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    pre { background: #f5f5f5; padding: 16px; border-radius: 4px; overflow-x: auto; }
    h2 { border-bottom: 2px solid #06C755; padding-bottom: 8px; margin-top: 32px; }
</style>';

echo '<div class="info">';
echo '<strong>📋 當前登入用戶:</strong><br>';
echo 'User ID: <code>' . $user_id . '</code><br>';
echo 'Username: <code>' . $user->user_login . '</code><br>';
echo 'Email: <code>' . $user->user_email . '</code>';
echo '</div>';

echo '<div class="section">';
echo '<h2>1️⃣ 檢查 LineUserService::isUserLinked()</h2>';
$is_linked = \BuygoLineNotify\Services\LineUserService::isUserLinked($user_id);
echo '<p>isUserLinked(' . $user_id . ') = <strong>' . ($is_linked ? '<span class="success">TRUE (已綁定 ✓)</span>' : '<span class="error">FALSE (未綁定 ✗)</span>') . '</strong></p>';
echo '</div>';

echo '<div class="section">';
echo '<h2>2️⃣ 檢查 LineUserService::getUser()</h2>';
$line_data = \BuygoLineNotify\Services\LineUserService::getUser($user_id);
if ($line_data) {
    echo '<p class="success">✓ 找到綁定資料</p>';
    echo '<table>';
    echo '<tr><th>欄位</th><th>值</th></tr>';
    foreach ($line_data as $key => $value) {
        echo '<tr><td><code>' . esc_html($key) . '</code></td><td>' . esc_html($value) . '</td></tr>';
    }
    echo '</table>';
} else {
    echo '<p class="error">✗ 無綁定資料</p>';
}
echo '</div>';

echo '<div class="section">';
echo '<h2>3️⃣ 檢查 LINE Profile Meta</h2>';
$display_name = get_user_meta($user_id, 'buygo_line_display_name', true);
$avatar_url = get_user_meta($user_id, 'buygo_line_avatar_url', true);

echo '<table>';
echo '<tr><th>Meta Key</th><th>值</th></tr>';
echo '<tr><td><code>buygo_line_display_name</code></td><td>' . ($display_name ? esc_html($display_name) : '<em>無資料</em>') . '</td></tr>';
echo '<tr><td><code>buygo_line_avatar_url</code></td><td>' . ($avatar_url ? '<a href="' . esc_url($avatar_url) . '" target="_blank">查看</a>' : '<em>無資料</em>') . '</td></tr>';
echo '</table>';

if ($avatar_url) {
    echo '<p><strong>頭像預覽:</strong><br><img src="' . esc_url($avatar_url) . '" style="width: 80px; height: 80px; border-radius: 50%; border: 3px solid #06C755;" alt="LINE Avatar"></p>';
}
echo '</div>';

echo '<div class="section">';
echo '<h2>4️⃣ 模擬 REST API /binding-status 回應</h2>';

if (!$is_linked) {
    $api_response = [
        'success' => true,
        'is_linked' => false,
        'message' => '未綁定 LINE',
    ];
} elseif (!$line_data) {
    $api_response = [
        'success' => true,
        'is_linked' => false,
        'message' => '綁定資料不存在',
    ];
} else {
    $api_response = [
        'success' => true,
        'is_linked' => true,
        'line_uid' => $line_data['line_uid'] ?? '',
        'display_name' => $display_name ?: '未知',
        'avatar_url' => $avatar_url ?: '',
        'linked_at' => $line_data['link_date'] ?? '',
    ];
}

echo '<p><strong>預期回應:</strong></p>';
echo '<pre>';
echo json_encode($api_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
echo '</pre>';
echo '</div>';

echo '<div class="section">';
echo '<h2>5️⃣ 實際呼叫 REST API (使用 JavaScript)</h2>';
echo '<div id="api-test-result" style="background: #fff3cd; padding: 16px; border-radius: 4px; border: 1px solid #ffc107;">⏳ 載入中...</div>';

$nonce = wp_create_nonce('wp_rest');
$api_url = rest_url('buygo-line-notify/v1/fluentcart/binding-status');

echo "<script>
(async function() {
    const resultDiv = document.getElementById('api-test-result');
    try {
        const response = await fetch('{$api_url}', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': '{$nonce}'
            }
        });

        const data = await response.json();

        let html = '<p><strong>HTTP Status:</strong> ' + response.status + ' ' + response.statusText + '</p>';
        html += '<p><strong>實際回應:</strong></p>';
        html += '<pre>' + JSON.stringify(data, null, 2) + '</pre>';

        if (data.is_linked) {
            html += '<p class=\"success\">✓ API 正確回傳「已綁定」狀態</p>';
            resultDiv.style.background = '#d4edda';
            resultDiv.style.borderColor = '#06C755';
        } else {
            html += '<p class=\"error\">✗ API 回傳「未綁定」- 如果你已經用 LINE 登入,這就是 bug!</p>';
            resultDiv.style.background = '#f8d7da';
            resultDiv.style.borderColor = '#dc3545';
        }

        resultDiv.innerHTML = html;
    } catch (err) {
        resultDiv.innerHTML = '<p class=\"error\">❌ 錯誤: ' + err.message + '</p>';
        resultDiv.style.background = '#f8d7da';
        resultDiv.style.borderColor = '#dc3545';
    }
})();
</script>";
echo '</div>';

echo '<div class="section">';
echo '<h2>6️⃣ 資料庫直接查詢</h2>';
global $wpdb;
$table_name = $wpdb->prefix . 'buygo_line_users';
$db_result = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE user_id = %d",
        $user_id
    ),
    ARRAY_A
);

if ($db_result) {
    echo '<p class="success">✓ 資料庫中找到綁定紀錄</p>';
    echo '<table>';
    echo '<tr><th>欄位</th><th>值</th></tr>';
    foreach ($db_result as $key => $value) {
        echo '<tr><td><code>' . esc_html($key) . '</code></td><td>' . esc_html($value) . '</td></tr>';
    }
    echo '</table>';
} else {
    echo '<p class="error">✗ 資料庫中無此用戶的 LINE 綁定紀錄</p>';
}
echo '</div>';

echo '<hr style="margin: 40px 0; border: none; border-top: 2px solid #e0e0e0;">';
echo '<p style="text-align: center; color: #666;"><strong>✅ 測試完成!</strong> 請查看第 5 項的結果</p>';
