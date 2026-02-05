<?php
/**
 * Plugin Name: Buygo Line Notify
 * Description: Buygo Line Notify plugin scaffold.
 * Version: 0.1.3
 * Author: acme
 * License: GPLv2 or later
 * Text Domain: buygo-line-notify
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BuygoLineNotify_PLUGIN_VERSION', '0.1.3');

define('BuygoLineNotify_PLUGIN_DIR', plugin_dir_path(__FILE__));

define('BuygoLineNotify_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/includes/class-plugin.php';
require_once __DIR__ . '/includes/class-database.php';
require_once __DIR__ . '/includes/class-auto-updater.php';

// 外掛啟動時初始化資料庫
register_activation_hook(__FILE__, function() {
    \BuygoLineNotify\Database::init();
});

// 在所有外掛載入完成後初始化（確保 buygo-plus-one 已載入）
add_action('plugins_loaded', function() {
    error_log('BuygoLineNotify: plugins_loaded hook fired, initializing plugin');
    \BuygoLineNotify\Plugin::instance()->init();
    error_log('BuygoLineNotify: Plugin initialized');
}, 20);

// 初始化自動更新器
if (is_admin()) {
    new \BuygoLineNotify\Auto_Updater(BuygoLineNotify_PLUGIN_VERSION);
}

