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

        // 自動同步其他外掛的 LINE 綁定資料
        $this->runAutoMigration();

        \add_action('init', [$this, 'onInit']);
    }

    /**
     * 自動執行資料遷移（從其他 LINE 外掛同步綁定資料）
     *
     * @return void
     */
    private function runAutoMigration(): void
    {
        // 檢查是否需要同步
        if (!\BuygoLineNotify\Services\MigrationService::needsMigration()) {
            return;
        }

        // 執行同步（靜默模式，不輸出任何內容）
        try {
            \BuygoLineNotify\Services\MigrationService::runMigration();
        } catch (\Exception $e) {
            // 記錄錯誤但不中斷外掛載入
            error_log('BuyGo LINE Notify: 自動同步失敗 - ' . $e->getMessage());
        }
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

        // 註冊帳號頁面整合（我的帳號 - LINE 綁定狀態顯示）
        \BuygoLineNotify\Services\AccountIntegrationService::register_hooks();

        // 註冊 FluentCart 客戶檔案整合
        \BuygoLineNotify\Integrations\FluentCartCustomerProfileIntegration::register_hooks();

        // 註冊 NSL (Nextend Social Login) 整合
        // 提供過渡期間的自動重新授權和資料清理功能
        \BuygoLineNotify\Integrations\NSLIntegration::init();

        // 初始化後台管理功能
        $this->initAdminFeatures();

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

        // 註冊 REST API（FluentCart Integration endpoints）
        \add_action('rest_api_init', function () {
            $fluentcart_api = new \BuygoLineNotify\Api\FluentCartIntegrationAPI();
            $fluentcart_api->register_routes();

            // Debug API
            $debug_api = new \BuygoLineNotify\Api\DebugAPI();
            $debug_api->register_routes();
        });

        // 註冊 Cron handler（非 FastCGI 環境的背景處理）
        \add_action('buygo_process_line_webhook', function ($events) {
            $handler = new \BuygoLineNotify\Services\WebhookHandler();
            $handler->process_events($events);
        }, 10, 1);

        if (\is_admin()) {
            SettingsPage::register_hooks();
            \BuygoLineNotify\Admin\DebugPage::register_hooks();
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

        // 註冊 [buygo_line_login] shortcode
        if (!\class_exists('BuygoLineNotify\Shortcodes\LoginButtonShortcode')) {
            include_once BuygoLineNotify_PLUGIN_DIR . 'includes/shortcodes/class-login-button-shortcode.php';
        }
        \BuygoLineNotify\Shortcodes\LoginButtonShortcode::register();

        // 註冊 [buygo_line_binding] shortcode
        if (!\class_exists('BuygoLineNotify\Shortcodes\LineBindingShortcode')) {
            include_once BuygoLineNotify_PLUGIN_DIR . 'includes/shortcodes/class-line-binding-shortcode.php';
        }
        \BuygoLineNotify\Shortcodes\LineBindingShortcode::register();
    }

    private function loadDependencies(): void
    {
        // 載入服務類別
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/services/class-logger.php';
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/services/class-settings-service.php';
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/services/class-image-uploader.php';
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/services/class-line-messaging-service.php';
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/services/class-line-user-service.php';
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/services/class-migration-service.php';

        // MessagingService (Facade API) 依賴 LineUserService，必須在其之後載入
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/services/class-messaging-service.php';

        // ImageService 依賴 SettingsService，必須在其之後載入
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/services/class-image-service.php';

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
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/services/class-account-integration-service.php';

        // 載入 Exception 類別
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/exceptions/class-nsl-continue-page-render-exception.php';

        // 載入 Handler 類別
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/handlers/class-login-handler.php';

        // 載入 API 類別
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/api/class-webhook-api.php';
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/api/class-login-api.php';
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/api/class-fluentcart-integration-api.php';
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/api/class-debug-api.php';

        // 載入 Integration 類別
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/integrations/class-fluentcart-customer-profile-integration.php';
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/integrations/class-nsl-integration.php';

        // 載入 Facade（供其他外掛使用）
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/class-buygo-line-notify.php';

        // 載入其他類別
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/cron/class-retry-dispatcher.php';

        if (\is_admin()) {
            include_once BuygoLineNotify_PLUGIN_DIR . 'includes/admin/class-settings-page.php';
            include_once BuygoLineNotify_PLUGIN_DIR . 'includes/admin/class-user-list-column.php';
            include_once BuygoLineNotify_PLUGIN_DIR . 'includes/admin/class-debug-page.php';
        }
    }

    /**
     * 初始化後台管理功能
     */
    private function initAdminFeatures(): void
    {
        if (!\is_admin()) {
            return;
        }

        // 初始化用戶列表 LINE 綁定欄位
        \BuygoLineNotify\Admin\UserListColumn::init();
    }
}

