<?php
/**
 * 測試 LineUserService 功能
 * 在瀏覽器訪問: https://test.buygo.me/wp-content/plugins/buygo-line-notify/test-line-user-service.php
 */

// 載入 WordPress
if (file_exists(__DIR__ . '/../../../wp-load.php')) {
    require_once __DIR__ . '/../../../wp-load.php';
} else {
    $wp_load = '/Users/fishtv/Local Sites/buygo/app/public/wp-load.php';
    if (file_exists($wp_load)) {
        require_once $wp_load;
    } else {
        die('無法找到 wp-load.php');
    }
}

// 載入必要類別
require_once __DIR__ . '/includes/services/class-line-user-service.php';

use BuygoLineNotify\Services\LineUserService;

echo "<h1>LineUserService 功能測試</h1>";

// 測試資料
$test_user_id = 1; // 測試用戶 ID（WordPress admin）
$test_line_uid = 'U1234567890abcdef';
$test_profile = [
    'displayName' => '測試使用者',
    'pictureUrl' => 'https://example.com/avatar.jpg'
];

echo "<h2>1. 測試綁定功能 (bind_line_account)</h2>";
$result = LineUserService::bind_line_account($test_user_id, $test_line_uid, $test_profile);
if ($result) {
    echo "<p style='color: green;'>✓ 綁定成功</p>";
} else {
    echo "<p style='color: red;'>✗ 綁定失敗</p>";
}

echo "<h2>2. 測試查詢 LINE UID (get_user_line_id)</h2>";
$line_id = LineUserService::get_user_line_id($test_user_id);
if ($line_id === $test_line_uid) {
    echo "<p style='color: green;'>✓ 查詢成功: {$line_id}</p>";
} else {
    echo "<p style='color: red;'>✗ 查詢失敗，預期: {$test_line_uid}，實際: " . var_export($line_id, true) . "</p>";
}

echo "<h2>3. 測試查詢完整資料 (get_line_user)</h2>";
$user_data = LineUserService::get_line_user($test_line_uid);
if ($user_data) {
    echo "<p style='color: green;'>✓ 查詢成功</p>";
    echo "<pre>" . print_r($user_data, true) . "</pre>";
} else {
    echo "<p style='color: red;'>✗ 查詢失敗</p>";
}

echo "<h2>4. 測試查詢用戶綁定 (get_user_binding)</h2>";
$binding = LineUserService::get_user_binding($test_user_id);
if ($binding) {
    echo "<p style='color: green;'>✓ 查詢成功</p>";
    echo "<pre>" . print_r($binding, true) . "</pre>";
} else {
    echo "<p style='color: red;'>✗ 查詢失敗</p>";
}

echo "<h2>5. 驗證 user_meta 也有寫入</h2>";
$meta_line_id = get_user_meta($test_user_id, 'buygo_line_user_id', true);
$meta_display_name = get_user_meta($test_user_id, 'buygo_line_display_name', true);
$meta_picture_url = get_user_meta($test_user_id, 'buygo_line_picture_url', true);

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Meta Key</th><th>Value</th><th>Status</th></tr>";
echo "<tr><td>buygo_line_user_id</td><td>{$meta_line_id}</td><td>";
echo ($meta_line_id === $test_line_uid) ? "<span style='color: green;'>✓</span>" : "<span style='color: red;'>✗</span>";
echo "</td></tr>";
echo "<tr><td>buygo_line_display_name</td><td>{$meta_display_name}</td><td>";
echo ($meta_display_name === $test_profile['displayName']) ? "<span style='color: green;'>✓</span>" : "<span style='color: red;'>✗</span>";
echo "</td></tr>";
echo "<tr><td>buygo_line_picture_url</td><td>{$meta_picture_url}</td><td>";
echo ($meta_picture_url === $test_profile['pictureUrl']) ? "<span style='color: green;'>✓</span>" : "<span style='color: red;'>✗</span>";
echo "</td></tr>";
echo "</table>";

echo "<h2>6. 測試檢查函數</h2>";
$is_bound = LineUserService::is_user_bound($test_user_id);
$is_line_bound = LineUserService::is_line_uid_bound($test_line_uid);

echo "<p>is_user_bound({$test_user_id}): " . ($is_bound ? '<span style="color: green;">✓ true</span>' : '<span style="color: red;">✗ false</span>') . "</p>";
echo "<p>is_line_uid_bound({$test_line_uid}): " . ($is_line_bound ? '<span style="color: green;">✓ true</span>' : '<span style="color: red;">✗ false</span>') . "</p>";

echo "<h2>7. 測試解除綁定 (unbind_line_account)</h2>";
$unbind_result = LineUserService::unbind_line_account($test_user_id);
if ($unbind_result) {
    echo "<p style='color: green;'>✓ 解除綁定成功</p>";

    // 驗證解除綁定後的狀態
    $line_id_after = LineUserService::get_user_line_id($test_user_id);
    $meta_after = get_user_meta($test_user_id, 'buygo_line_user_id', true);

    echo "<p>解除綁定後 get_user_line_id(): " . var_export($line_id_after, true) . "</p>";
    echo "<p>解除綁定後 user_meta: " . var_export($meta_after, true) . "</p>";

    if (empty($line_id_after) && empty($meta_after)) {
        echo "<p style='color: green;'>✓ 解除綁定驗證成功</p>";
    } else {
        echo "<p style='color: red;'>✗ 解除綁定後資料仍存在</p>";
    }
} else {
    echo "<p style='color: red;'>✗ 解除綁定失敗</p>";
}

// 檢查資料表中的狀態（應該是 inactive）
global $wpdb;
$table_name = $wpdb->prefix . 'buygo_line_bindings';
$db_record = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$table_name} WHERE user_id = %d",
    $test_user_id
));

echo "<h2>8. 驗證資料表記錄狀態</h2>";
if ($db_record) {
    echo "<p>狀態: {$db_record->status}</p>";
    if ($db_record->status === 'inactive') {
        echo "<p style='color: green;'>✓ 狀態正確設為 inactive（軟刪除）</p>";
    } else {
        echo "<p style='color: red;'>✗ 狀態不正確: {$db_record->status}</p>";
    }
} else {
    echo "<p style='color: red;'>✗ 找不到記錄</p>";
}

echo "<p><a href='javascript:history.back()'>返回</a></p>";
