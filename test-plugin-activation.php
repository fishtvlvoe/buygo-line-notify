<?php
/**
 * 測試外掛啟用流程
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== 測試外掛啟用流程 ===\n\n";

// 1. 檢查主檔案是否可讀取
$plugin_file = __DIR__ . '/buygo-line-notify.php';
echo "1. 檢查主檔案\n";
echo "   路徑: $plugin_file\n";
echo "   可讀取: " . (is_readable($plugin_file) ? '✓' : '✗') . "\n\n";

// 2. 嘗試載入主檔案（不執行 WordPress hooks）
echo "2. 載入主檔案\n";
try {
    // 模擬 WordPress 環境
    if (!defined('ABSPATH')) {
        define('ABSPATH', '/Users/fishtv/Local Sites/buygo/app/public/');
    }
    
    // 載入主檔案
    ob_start();
    include $plugin_file;
    $output = ob_get_clean();
    
    if (!empty($output)) {
        echo "   輸出內容: $output\n";
    }
    
    echo "   ✓ 主檔案載入成功\n\n";
} catch (Exception $e) {
    echo "   ✗ 錯誤: " . $e->getMessage() . "\n\n";
}

// 3. 檢查類別是否存在
echo "3. 檢查類別\n";
$classes = [
    'BuygoLineNotify\Plugin',
    'BuygoLineNotify\Database',
    'BuygoLineNotify\Updater'
];

foreach ($classes as $class) {
    echo "   $class: " . (class_exists($class) ? '✓' : '✗') . "\n";
}
echo "\n";

// 4. 檢查常數
echo "4. 檢查常數\n";
$constants = [
    'BuygoLineNotify_PLUGIN_VERSION',
    'BuygoLineNotify_PLUGIN_DIR',
    'BuygoLineNotify_PLUGIN_URL'
];

foreach ($constants as $constant) {
    if (defined($constant)) {
        echo "   $constant: " . constant($constant) . " ✓\n";
    } else {
        echo "   $constant: ✗ 未定義\n";
    }
}
echo "\n";

echo "完成！\n";
