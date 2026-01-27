<?php
/**
 * Plugin Name: Buygo Line Notify
 * Description: Buygo Line Notify plugin scaffold.
 * Version: 0.1.0
 * Author: acme
 * License: GPLv2 or later
 * Text Domain: buygo-line-notify
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BuygoLineNotify_PLUGIN_VERSION', '0.1.0');

define('BuygoLineNotify_PLUGIN_DIR', plugin_dir_path(__FILE__));

define('BuygoLineNotify_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/includes/class-plugin.php';
require_once __DIR__ . '/includes/class-database.php';

// 外掛啟動時初始化資料庫
register_activation_hook(__FILE__, function() {
    \BuygoLineNotify\Database::init();
});

\BuygoLineNotify\Plugin::instance()->init();

