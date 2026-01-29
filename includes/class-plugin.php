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

        // 註冊 LINE 登入按鈕 hooks（前台登入整合）
        \BuygoLineNotify\Services\LoginButtonService::register_hooks();

        // 註冊 LINE Login Handler（標準 WordPress URL 機制）
        \BuygoLineNotify\Handlers\Login_Handler::register_hooks();

        // 註冊 URL Filter Service（login_url / logout_url filters）
        \BuygoLineNotify\Services\UrlFilterService::register_hooks();

        // 初始化 Avatar Service（get_avatar_url filter hook）
        \BuygoLineNotify\Services\AvatarService::init();

        // 註冊 LINE Login shortcodes
        $this->register_shortcodes();

        // 註冊 REST API（Webhook endpoint）
        \add_action('rest_api_init', function () {
            $webhook_api = new \BuygoLineNotify\Api\Webhook_API();
            $webhook_api->register_routes();
        });

        // 註冊 REST API（LINE Login endpoints）
        \add_action('rest_api_init', function () {
            $login_api = new \BuygoLineNotify\Api\Login_API();
            $login_api->register_routes();
        });

        // 註冊 Cron handler（非 FastCGI 環境的背景處理）
        \add_action('buygo_process_line_webhook', function ($events) {
            $handler = new \BuygoLineNotify\Services\WebhookHandler();
            $handler->process_events($events);
        }, 10, 1);

        if (\is_admin()) {
            SettingsPage::register_hooks();
        }
    }

    /**
     * 註冊 shortcodes
     *
     * @return void
     */
    private function register_shortcodes(): void
    {
        // 註冊 [buygo_line_register_flow] shortcode
        \add_shortcode('buygo_line_register_flow', function ($atts) {
            // 載入 shortcode 類別
            if (!\class_exists('BuygoLineNotify\Shortcodes\RegisterFlowShortcode')) {
                include_once BuygoLineNotify_PLUGIN_DIR . 'includes/shortcodes/class-register-flow-shortcode.php';
            }
            $shortcode = new \BuygoLineNotify\Shortcodes\RegisterFlowShortcode();
            return $shortcode->render($atts, null);
        });
    }

    private function loadDependencies(): void
    {
        // 載入服務類別
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/services/class-logger.php';
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/services/class-settings-service.php';
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/services/class-image-uploader.php';
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/services/class-line-messaging-service.php';
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/services/class-line-user-service.php';

        // ProfileSyncService 依賴 SettingsService，必須在其之後載入
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/services/class-profile-sync-service.php';

        // AvatarService 依賴 LineUserService，必須在其之後載入
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/services/class-avatar-service.php';

        // 載入 Webhook 相關服務
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/services/class-webhook-verifier.php';
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/services/class-webhook-handler.php';

        // 載入 LINE Login 相關服務
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/services/class-state-manager.php';
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/services/class-login-service.php';
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/services/class-user-service.php';
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/services/class-login-button-service.php';
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/services/class-url-filter-service.php';

        // 載入 Exception 類別
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/exceptions/class-nsl-continue-page-render-exception.php';

        // 載入 Handler 類別
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/handlers/class-login-handler.php';

        // 載入 API 類別
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/api/class-webhook-api.php';
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/api/class-login-api.php';

        // 載入 Facade（供其他外掛使用）
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/class-buygo-line-notify.php';

        // 載入其他類別
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/cron/class-retry-dispatcher.php';

        if (\is_admin()) {
            include_once BuygoLineNotify_PLUGIN_DIR . 'includes/admin/class-settings-page.php';
        }
    }
}

