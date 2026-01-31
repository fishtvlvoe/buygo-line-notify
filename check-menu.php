<?php
/**
 * 檢查選單是否正確註冊
 */

// 從 WordPress 環境執行
define('WP_USE_THEMES', false);
require_once '/Users/fishtv/Local Sites/buygo/app/public/wp-load.php';

// 必須在 admin 環境中
if (!is_admin()) {
    define('WP_ADMIN', true);
}

// 手動觸發 admin_init 和 admin_menu
do_action('admin_init');
do_action('admin_menu');

echo "=== WordPress 選單檢查 ===\n\n";

// 檢查全域變數
global $menu, $submenu;

echo "1. 主選單 (buygo-plus-one):\n";
if (isset($menu)) {
    foreach ($menu as $item) {
        if (strpos($item[2], 'buygo') !== false) {
            echo "   - {$item[0]} (slug: {$item[2]})\n";
        }
    }
}

echo "\n2. buygo-plus-one 的子選單:\n";
if (isset($submenu['buygo-plus-one'])) {
    foreach ($submenu['buygo-plus-one'] as $item) {
        echo "   - {$item[0]} (slug: {$item[2]})\n";
    }
} else {
    echo "   ✗ 沒有 buygo-plus-one 的子選單\n";
}

echo "\n3. 檢查 buygo-line-notify-settings 是否存在:\n";
$found = false;
if (isset($submenu['buygo-plus-one'])) {
    foreach ($submenu['buygo-plus-one'] as $item) {
        if ($item[2] === 'buygo-line-notify-settings') {
            echo "   ✓ 找到了！\n";
            echo "   標題: {$item[0]}\n";
            echo "   Slug: {$item[2]}\n";
            $found = true;
            break;
        }
    }
}
if (!$found) {
    echo "   ✗ 沒找到 buygo-line-notify-settings\n";
}

echo "\n=== 檢查完成 ===\n";
