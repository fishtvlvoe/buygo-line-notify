<?php
/**
 * 測試 MessagingService
 *
 * 使用方式:
 * 1. 確認已經有用戶綁定 LINE（使用 test-binding-status.php 確認）
 * 2. 修改下方的 $test_user_id
 * 3. 訪問: https://test.buygo.me/wp-content/plugins/buygo-line-notify/test-messaging-service.php
 */

// 載入 WordPress
require_once '/Users/fishtv/Local Sites/buygo/app/public/wp-load.php';

// 檢查是否登入
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('請先以管理員身分登入 WordPress', '權限不足', ['response' => 403]);
}

use BuygoLineNotify\Services\MessagingService;

// ========== 設定測試用戶 ==========
$test_user_id = 1; // 修改為已綁定 LINE 的用戶 ID

echo '<h1>MessagingService 測試</h1>';
echo '<style>
    body { font-family: system-ui, -apple-system, sans-serif; padding: 20px; max-width: 1200px; margin: 0 auto; }
    .success { color: #06C755; font-weight: bold; }
    .error { color: #dc3545; font-weight: bold; }
    .info { background: #f0f8ff; padding: 16px; border-left: 4px solid #2196F3; margin: 20px 0; border-radius: 4px; }
    .section { background: white; padding: 24px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    pre { background: #f5f5f5; padding: 16px; border-radius: 4px; overflow-x: auto; }
    .button { display: inline-block; padding: 12px 24px; background: #06C755; color: white; text-decoration: none; border-radius: 4px; margin: 8px 8px 8px 0; cursor: pointer; border: none; font-size: 14px; }
    .button:hover { background: #05b04a; }
</style>';

echo '<div class="info">';
echo '<strong>📋 測試用戶:</strong><br>';
echo 'User ID: <code>' . $test_user_id . '</code>';
echo '</div>';

// 檢查用戶是否已綁定 LINE
echo '<div class="section">';
echo '<h2>1️⃣ 檢查用戶綁定狀態</h2>';
$is_linked = MessagingService::isUserLinked($test_user_id);
if ($is_linked) {
    echo '<p class="success">✓ 用戶已綁定 LINE</p>';
} else {
    echo '<p class="error">✗ 用戶未綁定 LINE - 請先使用 test-binding-status.php 綁定</p>';
    exit;
}
echo '</div>';

// 測試發送文字訊息
echo '<div class="section">';
echo '<h2>2️⃣ 測試發送文字訊息</h2>';

if (isset($_GET['test']) && $_GET['test'] === 'text') {
    $result = MessagingService::pushText($test_user_id, '🎉 測試訊息：這是來自 buygo-line-notify 的文字訊息！');

    if (is_wp_error($result)) {
        echo '<p class="error">❌ 發送失敗: ' . $result->get_error_message() . '</p>';
        echo '<pre>' . print_r($result->get_error_data(), true) . '</pre>';
    } else {
        echo '<p class="success">✓ 文字訊息發送成功！請檢查您的 LINE 是否收到訊息。</p>';
    }
} else {
    echo '<p>點擊下方按鈕發送測試文字訊息：</p>';
    echo '<a href="?test=text" class="button">發送文字訊息</a>';
}
echo '</div>';

// 測試發送圖片訊息
echo '<div class="section">';
echo '<h2>3️⃣ 測試發送圖片訊息</h2>';

if (isset($_GET['test']) && $_GET['test'] === 'image') {
    // 使用公開的測試圖片
    $image_url = 'https://via.placeholder.com/500x500.png?text=Test+Image';
    $result = MessagingService::pushImage($test_user_id, $image_url);

    if (is_wp_error($result)) {
        echo '<p class="error">❌ 發送失敗: ' . $result->get_error_message() . '</p>';
        echo '<pre>' . print_r($result->get_error_data(), true) . '</pre>';
    } else {
        echo '<p class="success">✓ 圖片訊息發送成功！請檢查您的 LINE 是否收到圖片。</p>';
    }
} else {
    echo '<p>點擊下方按鈕發送測試圖片訊息：</p>';
    echo '<a href="?test=image" class="button">發送圖片訊息</a>';
}
echo '</div>';

// 測試發送 Flex Message
echo '<div class="section">';
echo '<h2>4️⃣ 測試發送 Flex Message</h2>';

if (isset($_GET['test']) && $_GET['test'] === 'flex') {
    // 簡單的 Flex Message 範例
    $flex_contents = [
        'type' => 'bubble',
        'body' => [
            'type' => 'box',
            'layout' => 'vertical',
            'contents' => [
                [
                    'type' => 'text',
                    'text' => '測試 Flex Message',
                    'weight' => 'bold',
                    'size' => 'xl',
                ],
                [
                    'type' => 'text',
                    'text' => '這是來自 buygo-line-notify 的 Flex Message 測試',
                    'size' => 'sm',
                    'color' => '#999999',
                    'margin' => 'md',
                ],
            ],
        ],
        'altText' => '測試 Flex Message',
    ];

    $result = MessagingService::pushFlex($test_user_id, $flex_contents);

    if (is_wp_error($result)) {
        echo '<p class="error">❌ 發送失敗: ' . $result->get_error_message() . '</p>';
        echo '<pre>' . print_r($result->get_error_data(), true) . '</pre>';
    } else {
        echo '<p class="success">✓ Flex Message 發送成功！請檢查您的 LINE 是否收到訊息。</p>';
    }
} else {
    echo '<p>點擊下方按鈕發送測試 Flex Message：</p>';
    echo '<a href="?test=flex" class="button">發送 Flex Message</a>';
}
echo '</div>';

echo '<hr style="margin: 40px 0; border: none; border-top: 2px solid #e0e0e0;">';
echo '<p style="text-align: center; color: #666;"><strong>✅ 測試完成!</strong></p>';
