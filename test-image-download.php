<?php
/**
 * 測試圖片下載功能
 */
require_once __DIR__ . '/../../../wp-load.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== 測試圖片下載功能 ===\n\n";

// 從最新的 webhook 事件取得 message_id
$message_id = '598838522879418450';  // 從截圖中取得
$user_id = 26;

echo "1. 測試參數\n";
echo "   Message ID: $message_id\n";
echo "   User ID: $user_id\n\n";

echo "2. 檢查 Channel Access Token\n";
$access_token = \BuygoLineNotify\Services\SettingsService::get('channel_access_token');
if (empty($access_token)) {
    echo "   ✗ Channel Access Token 未設定\n";
    exit;
} else {
    echo "   ✓ Channel Access Token 已設定 (長度: " . strlen($access_token) . ")\n\n";
}

echo "3. 嘗試下載圖片\n";
try {
    $imageService = \BuygoLineNotify\BuygoLineNotify::image_uploader();
    $result = $imageService->downloadToMediaLibrary($message_id, $user_id);
    
    if (is_wp_error($result)) {
        echo "   ✗ 下載失敗\n";
        echo "   錯誤代碼: " . $result->get_error_code() . "\n";
        echo "   錯誤訊息: " . $result->get_error_message() . "\n";
        $data = $result->get_error_data();
        if ($data) {
            echo "   詳細資料: " . print_r($data, true) . "\n";
        }
    } else {
        echo "   ✓ 下載成功\n";
        echo "   Attachment ID: $result\n";
        $url = wp_get_attachment_url($result);
        echo "   圖片 URL: $url\n";
    }
} catch (Exception $e) {
    echo "   ✗ 發生例外\n";
    echo "   " . $e->getMessage() . "\n";
}

echo "\n完成！\n";
