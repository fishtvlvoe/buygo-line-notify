<?php
/**
 * 測試圖片上傳 Webhook 完整流程
 */
require_once __DIR__ . '/../../../wp-load.php';

header('Content-Type: text/plain; charset=utf-8');

echo "=== 測試圖片上傳 Webhook 完整流程 ===\n\n";

// 測試資料
$line_uid = 'U823e48d89eb99bdefb49053680948d09';
$message_id = '598824224453241143';
$channel_access_token = 'test_token';
$reply_token = 'test_reply_token';

echo "1. 測試資料\n";
echo "   LINE UID: $line_uid\n";
echo "   Message ID: $message_id\n\n";

// 2. 檢查 Hook 是否註冊
echo "2. 檢查 Hook 註冊\n";
global $wp_filter;
if (isset($wp_filter['buygo_line_notify/webhook_message_image'])) {
    echo "   ✓ buygo_line_notify/webhook_message_image hook 已註冊\n";
    $callbacks = $wp_filter['buygo_line_notify/webhook_message_image']->callbacks;
    foreach ($callbacks as $priority => $functions) {
        foreach ($functions as $idx => $function) {
            if (is_array($function['function'])) {
                $class = is_object($function['function'][0]) ? get_class($function['function'][0]) : $function['function'][0];
                $method = $function['function'][1];
                echo "   - Priority $priority: $class::$method\n";
            }
        }
    }
} else {
    echo "   ✗ Hook 未註冊！\n";
}
echo "\n";

// 3. 檢查必要類別
echo "3. 檢查必要類別\n";
$classes = [
    'BuygoLineNotify\BuygoLineNotify' => '✓',
    'BuyGoPlus\Services\LineWebhookHandler' => '✓'
];
foreach ($classes as $class => $expected) {
    $exists = class_exists($class);
    echo "   $class: " . ($exists ? '✓' : '✗') . "\n";
}
echo "\n";

// 4. 觸發 Webhook
echo "4. 觸發 Webhook\n";
try {
    ob_start();
    do_action(
        'buygo_line_notify/webhook_message_image',
        $line_uid,
        $message_id,
        $channel_access_token,
        $reply_token
    );
    $output = ob_get_clean();
    
    if ($output) {
        echo "   輸出: $output\n";
    }
    echo "   ✓ Webhook 已觸發\n\n";
} catch (Exception $e) {
    echo "   ✗ 錯誤: " . $e->getMessage() . "\n\n";
}

// 5. 檢查錯誤日誌
echo "5. 檢查最近的錯誤日誌\n";
$debug_log = WP_CONTENT_DIR . '/debug.log';
if (file_exists($debug_log)) {
    $lines = file($debug_log);
    $recent_lines = array_slice($lines, -20);
    $relevant = array_filter($recent_lines, function($line) {
        return stripos($line, 'buygo') !== false || 
               stripos($line, 'line') !== false ||
               stripos($line, 'fatal') !== false ||
               stripos($line, 'error') !== false;
    });
    
    if (count($relevant) > 0) {
        echo "   最近相關日誌:\n";
        foreach ($relevant as $line) {
            echo "   " . trim($line) . "\n";
        }
    } else {
        echo "   ✓ 沒有相關錯誤\n";
    }
} else {
    echo "   ℹ debug.log 不存在\n";
}

echo "\n完成！\n";
