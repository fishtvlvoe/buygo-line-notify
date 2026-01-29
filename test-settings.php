<?php
/**
 * Test script for SettingsService encryption and backward compatibility
 *
 * Run with: wp eval-file test-settings.php
 */

use BuygoLineNotify\Services\SettingsService;

echo "=== SettingsService Test ===\n\n";

// Test 1: 加密和儲存
echo "Test 1: 加密和儲存\n";
echo "-------------------\n";
SettingsService::set('channel_access_token', 'test-token-123');
SettingsService::set('liff_id', 'liff-123'); // 非加密欄位
echo "✓ Saved channel_access_token (encrypted) and liff_id (plain)\n\n";

// Test 2: 讀取（應該自動解密）
echo "Test 2: 讀取並自動解密\n";
echo "-------------------\n";
$token = SettingsService::get('channel_access_token');
$liff_id = SettingsService::get('liff_id');
echo "Token: {$token}\n";
echo "LIFF ID: {$liff_id}\n";

if ($token === 'test-token-123') {
    echo "✓ Token decrypted correctly\n";
} else {
    echo "✗ Token decryption failed\n";
}

if ($liff_id === 'liff-123') {
    echo "✓ LIFF ID retrieved correctly\n";
} else {
    echo "✗ LIFF ID retrieval failed\n";
}
echo "\n";

// Test 3: 檢查資料庫中的值是否加密
echo "Test 3: 驗證資料庫中的加密\n";
echo "-------------------\n";
global $wpdb;
$encrypted_in_db = $wpdb->get_var("SELECT option_value FROM {$wpdb->options} WHERE option_name = 'buygo_line_channel_access_token'");
echo "Encrypted in DB: {$encrypted_in_db}\n";

if ($encrypted_in_db !== 'test-token-123' && !empty($encrypted_in_db)) {
    echo "✓ Token is encrypted in database\n";
} else {
    echo "✗ Token is NOT encrypted in database\n";
}
echo "\n";

// Test 4: 測試向後相容（模擬舊外掛的設定）
echo "Test 4: 向後相容測試\n";
echo "-------------------\n";

// 使用 SettingsService::encrypt() 來加密舊設定
$old_encrypted_token = SettingsService::encrypt('old-token-456');
update_option('buygo_core_settings', [
    'channel_access_token' => $old_encrypted_token,
    'liff_id' => 'old-liff-456'
]);
echo "✓ Created old-style settings in buygo_core_settings\n";

// 刪除新外掛設定，強制讀取舊設定
delete_option('buygo_line_channel_access_token');
delete_option('buygo_line_liff_id');
echo "✓ Deleted new-style settings\n";

$old_token = SettingsService::get('channel_access_token');
$old_liff = SettingsService::get('liff_id');
echo "Old token: {$old_token}\n";
echo "Old LIFF ID: {$old_liff}\n";

if ($old_token === 'old-token-456') {
    echo "✓ Old encrypted token read correctly\n";
} else {
    echo "✗ Old token read failed\n";
}

if ($old_liff === 'old-liff-456') {
    echo "✓ Old plain LIFF ID read correctly\n";
} else {
    echo "✗ Old LIFF ID read failed\n";
}
echo "\n";

// Test 5: get_all() 方法
echo "Test 5: get_all() 方法\n";
echo "-------------------\n";
$all_settings = SettingsService::get_all();
echo "All settings:\n";
print_r($all_settings);
echo "✓ get_all() executed successfully\n\n";

// Test 6: delete() 方法
echo "Test 6: delete() 方法\n";
echo "-------------------\n";
SettingsService::set('channel_secret', 'secret-to-delete');
echo "✓ Created channel_secret\n";

$before_delete = SettingsService::get('channel_secret');
echo "Before delete: {$before_delete}\n";

SettingsService::delete('channel_secret');
echo "✓ Deleted channel_secret\n";

$after_delete = SettingsService::get('channel_secret');
echo "After delete: " . ($after_delete ? $after_delete : '(empty)') . "\n";

if (empty($after_delete)) {
    echo "✓ Delete method works correctly\n";
} else {
    echo "✗ Delete method failed\n";
}
echo "\n";

// Cleanup
echo "Cleanup\n";
echo "-------------------\n";
delete_option('buygo_line_channel_access_token');
delete_option('buygo_line_liff_id');
delete_option('buygo_core_settings');
echo "✓ Test data cleaned up\n\n";

echo "=== All Tests Complete ===\n";
