<?php
/**
 * Bootstrap for unit tests (no WordPress required)
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$composer_autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($composer_autoload)) {
    die('Unable to find Composer autoloader: ' . $composer_autoload);
}

require_once $composer_autoload;

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

if (!defined('BuygoLineNotify_PLUGIN_DIR')) {
    define('BuygoLineNotify_PLUGIN_DIR', dirname(__DIR__) . '/');
}

