<?php

namespace BuygoLineNotify\Admin;

/**
 * 後台設定頁面管理
 *
 * 根據父外掛（buygo-plus-one-dev）是否存在，動態決定選單位置：
 * - 父外掛存在：掛載為其子選單
 * - 父外掛不存在：建立獨立一級選單
 */
final class SettingsPage
{
    /**
     * 註冊 WordPress hooks
     */
    public static function register_hooks(): void
    {
        // 優先級 30，確保在 buygo-plus-one (優先級 20) 之後執行
        \add_action('admin_menu', [self::class, 'add_admin_menu'], 30);

        // AJAX handler for creating register flow page
        \add_action('wp_ajax_buygo_line_create_register_page', [self::class, 'ajax_create_register_page']);
    }

    /**
     * 根據父外掛是否存在，動態掛載選單
     *
     * 在 admin_menu hook 執行時，所有外掛已載入完成，
     * 可以安全地使用 class_exists() 檢查父外掛。
     */
    public static function add_admin_menu(): void
    {
        \error_log('BuygoLineNotify: add_admin_menu called');

        // 偵測 buygo-plus-one-dev 是否存在
        $parent_exists = \class_exists('BuyGoPlus\Plugin');
        \error_log('BuygoLineNotify: BuyGoPlus\Plugin exists = ' . ($parent_exists ? 'YES' : 'NO'));

        if ($parent_exists) {
            // 父外掛存在：掛載為子選單
            \error_log('BuygoLineNotify: Adding submenu under buygo-plus-one');
            $hook = \add_submenu_page(
                'buygo-plus-one',              // 父選單 slug
                'LINE 串接通知',                // 頁面標題
                'LINE 通知',                   // 選單標題
                'manage_options',              // 權限
                'buygo-line-notify-settings',  // 選單 slug
                [self::class, 'render_settings_page']
            );
            \error_log('BuygoLineNotify: Submenu added, hook suffix = ' . ($hook ?: 'FALSE'));
        } else {
            // 父外掛不存在：建立獨立一級選單
            \add_menu_page(
                'LINE 通知',                   // 頁面標題
                'LINE 通知',                   // 選單標題
                'manage_options',              // 權限
                'buygo-line-notify',           // 選單 slug
                [self::class, 'render_settings_page'],
                'dashicons-format-chat',       // icon
                50                             // position
            );

            // 子選單：設定（與一級選單使用相同 slug，會取代預設的重複項目）
            \add_submenu_page(
                'buygo-line-notify',
                'LINE 設定',
                '設定',
                'manage_options',
                'buygo-line-notify-settings',
                [self::class, 'render_settings_page']
            );
        }
    }

    /**
     * 渲染設定頁面
     */
    public static function render_settings_page(): void
    {
        \error_log('BuygoLineNotify: render_settings_page called');
        \error_log('BuygoLineNotify: current_user_can(manage_options) = ' . (\current_user_can('manage_options') ? 'YES' : 'NO'));
        if (!\current_user_can('manage_options')) {
            \wp_die(\__('您沒有權限訪問此頁面。', 'buygo-line-notify'));
        }

        // 處理表單提交
        $message = '';
        if (isset($_POST['buygo_line_settings_submit'])) {
            $message = self::handle_form_submission();
        }

        // 載入設定值
        $settings = \BuygoLineNotify\Services\SettingsService::get_all();

        // 產生 Webhook URL
        $webhook_url = \rest_url('buygo-line-notify/v1/webhook');

        // 載入視圖檔案
        include BuygoLineNotify_PLUGIN_DIR . 'includes/admin/views/settings-page.php';
    }

    /**
     * 處理表單提交
     */
    private static function handle_form_submission(): string
    {
        // Nonce 驗證
        if (!isset($_POST['buygo_line_settings_nonce']) ||
            !\wp_verify_nonce($_POST['buygo_line_settings_nonce'], 'buygo_line_settings_action')) {
            return '<div class="notice notice-error"><p>安全驗證失敗。</p></div>';
        }

        // 權限檢查
        if (!\current_user_can('manage_options')) {
            return '<div class="notice notice-error"><p>您沒有權限修改設定。</p></div>';
        }

        // 儲存設定（SettingsService 會自動加密）
        $fields = [
            'channel_access_token',
            'channel_secret',
            'login_channel_id',
            'login_channel_secret',
            'liff_id',
            'liff_endpoint_url',
            'login_button_position',
            'login_button_text',
        ];

        foreach ($fields as $field) {
            $value = isset($_POST[$field]) ? \sanitize_text_field($_POST[$field]) : '';
            \BuygoLineNotify\Services\SettingsService::set($field, $value);
        }

        // 處理 Register Flow Page（單獨處理，因為是 int 而非 string）
        $register_flow_page = isset($_POST['register_flow_page']) ? \absint($_POST['register_flow_page']) : 0;
        \update_option('buygo_line_register_flow_page', $register_flow_page);

        return '<div class="notice notice-success"><p>設定已儲存。</p></div>';
    }

    /**
     * AJAX: 建立 Register Flow Page
     */
    public static function ajax_create_register_page(): void
    {
        // Nonce 驗證
        if (!\check_ajax_referer('buygo_line_create_register_page', '_ajax_nonce', false)) {
            \wp_send_json_error(['message' => '安全驗證失敗']);
        }

        // 權限檢查
        if (!\current_user_can('publish_pages')) {
            \wp_send_json_error(['message' => '您沒有權限建立頁面']);
        }

        // 建立頁面
        $page_id = \wp_insert_post([
            'post_title'   => 'LINE 註冊',
            'post_content' => '[buygo_line_register_flow]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ]);

        if (\is_wp_error($page_id)) {
            \wp_send_json_error(['message' => $page_id->get_error_message()]);
        }

        // 自動設定為 Register Flow Page
        \update_option('buygo_line_register_flow_page', $page_id);

        \wp_send_json_success([
            'page_id'    => $page_id,
            'page_title' => 'LINE 註冊',
            'edit_url'   => \get_edit_post_link($page_id, 'raw'),
            'view_url'   => \get_permalink($page_id),
        ]);
    }
}
