<?php

namespace BuygoLineNotify;

use BuygoLineNotify\Admin\SettingsPage;
use BuygoLineNotify\Cron\RetryDispatcher;

/**
 * Plugin bootstrap.
 */
final class Plugin
{
    /**
     * @var self|null
     */
    private static ?self $_instance = null;

    public static function instance(): self
    {
        if (self::$_instance === null) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    private function __construct()
    {
    }

    public function init(): void
    {
        // 初始化資料庫（處理外掛更新時的資料表升級）
        \BuygoLineNotify\Database::init();

        $this->loadDependencies();

        \add_action('init', [$this, 'onInit']);
    }

    public function onInit(): void
    {
        RetryDispatcher::register_hooks();

        if (\is_admin()) {
            SettingsPage::register_hooks();
        }
    }

    private function loadDependencies(): void
    {
        // 載入服務類別
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/services/class-logger.php';
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/services/class-settings-service.php';
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/services/class-image-uploader.php';
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/services/class-line-messaging-service.php';
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/services/class-line-user-service.php';

        // 載入 Facade（供其他外掛使用）
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/class-buygo-line-notify.php';

        // 載入其他類別
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/cron/class-retry-dispatcher.php';

        if (\is_admin()) {
            include_once BuygoLineNotify_PLUGIN_DIR . 'includes/admin/class-settings-page.php';
        }
    }
}

