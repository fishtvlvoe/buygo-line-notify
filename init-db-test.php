<?php
/**
 * 測試資料庫初始化腳本
 * 在瀏覽器訪問: https://test.buygo.me/wp-content/plugins/buygo-line-notify/init-db-test.php
 */

// 載入 WordPress (透過符號連結訪問時的正確路徑)
if (file_exists(__DIR__ . '/../../../wp-load.php')) {
    require_once __DIR__ . '/../../../wp-load.php';
} else {
    // 如果透過實際路徑訪問，需要找到 WordPress 根目錄
    $wp_load = '/Users/fishtv/Local Sites/buygo/app/public/wp-load.php';
    if (file_exists($wp_load)) {
        require_once $wp_load;
    } else {
        die('無法找到 wp-load.php');
    }
}

// 載入 Database 類別
require_once __DIR__ . '/includes/class-database.php';

// 初始化資料庫
\BuygoLineNotify\Database::init();

// 檢查結果
global $wpdb;
$table_name = $wpdb->prefix . 'buygo_line_bindings';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;

echo "<h1>資料庫初始化測試</h1>";

if ($table_exists) {
    echo "<p style='color: green;'>✓ 資料表 {$table_name} 已成功建立</p>";

    // 顯示表結構
    echo "<h2>表結構：</h2>";
    $columns = $wpdb->get_results("DESCRIBE {$table_name}");
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>欄位</th><th>類型</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column->Field}</td>";
        echo "<td>{$column->Type}</td>";
        echo "<td>{$column->Null}</td>";
        echo "<td>{$column->Key}</td>";
        echo "<td>{$column->Default}</td>";
        echo "<td>{$column->Extra}</td>";
        echo "</tr>";
    }
    echo "</table>";

    // 顯示索引
    echo "<h2>索引：</h2>";
    $indexes = $wpdb->get_results("SHOW INDEX FROM {$table_name}");
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Key Name</th><th>Column</th><th>Unique</th></tr>";
    foreach ($indexes as $index) {
        echo "<tr>";
        echo "<td>{$index->Key_name}</td>";
        echo "<td>{$index->Column_name}</td>";
        echo "<td>" . ($index->Non_unique ? 'No' : 'Yes') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    // 顯示版本
    $db_version = get_option('buygo_line_notify_db_version');
    echo "<h2>資料庫版本：{$db_version}</h2>";

} else {
    echo "<p style='color: red;'>✗ 資料表 {$table_name} 建立失敗</p>";
}

echo "<p><a href='javascript:history.back()'>返回</a></p>";
