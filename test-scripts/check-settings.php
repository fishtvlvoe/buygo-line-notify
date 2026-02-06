<?php
/**
 * 檢查目前的 Profile Sync 設定
 *
 * 使用方式：php check-settings.php
 */

require_once '/Users/fishtv/Local Sites/buygo/app/public/wp-load.php';

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo " Profile Sync 設定檢查\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// 讀取設定
$sync_on_login = \BuygoLineNotify\Services\SettingsService::get('sync_on_login', false);
$conflict_strategy = \BuygoLineNotify\Services\SettingsService::get('conflict_strategy', 'line_priority');

echo "目前設定：\n\n";
echo "  登入時更新 Profile: " . ($sync_on_login ? '✓ 啟用' : '✗ 停用') . "\n";
echo "  衝突處理策略: ";

switch ($conflict_strategy) {
    case 'line_priority':
        echo "LINE 優先 (覆蓋 WordPress 資料)\n";
        break;
    case 'wordpress_priority':
        echo "WordPress 優先 (保留現有資料)\n";
        break;
    case 'manual':
        echo "手動處理 (記錄衝突但不更新)\n";
        break;
    default:
        echo "{$conflict_strategy} (未知策略)\n";
}

echo "\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "\n";
echo "策略說明：\n\n";
echo "1. LINE 優先 (line_priority)\n";
echo "   - 註冊時：強制同步 LINE profile\n";
echo "   - 登入時：覆蓋 WordPress 現有資料\n";
echo "   - 綁定時：覆蓋 WordPress 現有資料\n\n";

echo "2. WordPress 優先 (wordpress_priority)\n";
echo "   - 註冊時：強制同步 LINE profile\n";
echo "   - 登入時：保留 WordPress 資料（空白欄位除外）\n";
echo "   - 綁定時：保留 WordPress 資料（空白欄位除外）\n\n";

echo "3. 手動處理 (manual)\n";
echo "   - 註冊時：強制同步 LINE profile\n";
echo "   - 登入時：不更新，但記錄衝突到 wp_options\n";
echo "   - 綁定時：不更新，但記錄衝突到 wp_options\n\n";

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
