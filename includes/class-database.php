<?php
/**
 * Database management class for LINE bindings table.
 *
 * @package BuygoLineNotify
 */

namespace BuygoLineNotify;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Database class
 *
 * 管理 wp_buygo_line_bindings 資料表的建立與版本控制
 */
class Database {
    /**
     * 資料庫版本
     */
    const DB_VERSION = '1.0.0';

    /**
     * 初始化資料庫
     *
     * 檢查資料庫版本，如果需要則建立或升級資料表
     */
    public static function init(): void {
        $current_version = get_option('buygo_line_notify_db_version', '0.0.0');

        if (version_compare($current_version, self::DB_VERSION, '<')) {
            self::create_tables();
            update_option('buygo_line_notify_db_version', self::DB_VERSION);
        }
    }

    /**
     * 建立資料表
     *
     * 使用 dbDelta() 建立 wp_buygo_line_bindings 資料表
     * 實作混合儲存策略的核心儲存層
     */
    public static function create_tables(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'buygo_line_bindings';

        // 先檢查表是否已存在（避免重複執行 dbDelta）
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            return;
        }

        // dbDelta 語法嚴格要求：
        // - PRIMARY KEY 後必須有兩個空格
        // - 每個欄位獨立一行
        // - 不使用 IF NOT EXISTS（dbDelta 會自動處理）
        $sql = "CREATE TABLE {$table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            line_uid varchar(100) NOT NULL,
            display_name varchar(255),
            picture_url varchar(512),
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY idx_user_id (user_id),
            UNIQUE KEY idx_line_uid (line_uid),
            KEY idx_status (status)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * 刪除資料表（外掛移除時使用）
     */
    public static function drop_tables(): void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'buygo_line_bindings';
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");

        delete_option('buygo_line_notify_db_version');
    }
}
