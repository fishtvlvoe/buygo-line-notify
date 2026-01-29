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

        // AJAX handler for clearing avatar cache
        \add_action('wp_ajax_buygo_line_clear_avatar_cache', [self::class, 'ajax_clear_avatar_cache']);

        // AJAX handlers for developer tools
        \add_action('wp_ajax_buygo_line_get_users', [self::class, 'ajax_get_line_users']);
        \add_action('wp_ajax_buygo_line_delete_user', [self::class, 'ajax_delete_user']);
        \add_action('wp_ajax_buygo_line_delete_all_test_users', [self::class, 'ajax_delete_all_test_users']);
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

        // 儲存 Profile Sync 設定
        \BuygoLineNotify\Services\SettingsService::set('sync_on_login', isset($_POST['buygo_line_sync_on_login']) ? '1' : '');

        // 驗證並儲存衝突策略
        $conflict_strategy = isset($_POST['buygo_line_conflict_strategy']) ? \sanitize_text_field($_POST['buygo_line_conflict_strategy']) : 'line_priority';
        $valid_strategies = ['line_priority', 'wordpress_priority', 'manual'];
        if (!\in_array($conflict_strategy, $valid_strategies, true)) {
            $conflict_strategy = 'line_priority';
        }
        \BuygoLineNotify\Services\SettingsService::set('conflict_strategy', $conflict_strategy);

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

    /**
     * AJAX: 清除所有用戶的頭像快取
     */
    public static function ajax_clear_avatar_cache(): void
    {
        // 驗證 nonce
        \check_ajax_referer('buygo_line_clear_avatar_cache', 'nonce');

        // 驗證權限
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(['message' => '權限不足']);
        }

        // 清除快取
        $count = \BuygoLineNotify\Services\AvatarService::clearAllAvatarCache();

        \wp_send_json_success(['count' => $count]);
    }

    /**
     * AJAX: 取得所有已綁定 LINE 的用戶列表
     */
    public static function ajax_get_line_users(): void
    {
        // 驗證 nonce
        \check_ajax_referer('buygo_line_dev_tools', 'nonce');

        // 驗證權限
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(['message' => '權限不足']);
        }

        global $wpdb;
        $bindings_table = $wpdb->prefix . 'buygo_line_users';

        // 取得所有已綁定 LINE 的用戶
        $results = $wpdb->get_results(
            "SELECT user_id, line_uid FROM {$bindings_table} ORDER BY user_id ASC",
            ARRAY_A
        );

        $users = [];
        foreach ($results as $row) {
            $user = \get_user_by('id', $row['user_id']);
            if ($user) {
                $users[] = [
                    'ID'           => $user->ID,
                    'display_name' => $user->display_name,
                    'user_email'   => $user->user_email,
                    'line_uid'     => $row['line_uid'],
                    'roles'        => $user->roles,
                ];
            }
        }

        \wp_send_json_success(['users' => $users]);
    }

    /**
     * AJAX: 刪除單一用戶（包含 LINE 綁定和 WordPress 帳號）
     */
    public static function ajax_delete_user(): void
    {
        // 驗證 nonce
        \check_ajax_referer('buygo_line_dev_tools', 'nonce');

        // 驗證權限
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(['message' => '權限不足']);
        }

        $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;

        if (!$user_id) {
            \wp_send_json_error(['message' => '無效的用戶 ID']);
        }

        // 檢查用戶是否存在
        $user = \get_user_by('id', $user_id);
        if (!$user) {
            \wp_send_json_error(['message' => '用戶不存在']);
        }

        // 不允許刪除管理員
        if (\in_array('administrator', $user->roles, true)) {
            \wp_send_json_error(['message' => '不允許刪除管理員帳號']);
        }

        // 不允許刪除當前登入的用戶
        if ($user_id === \get_current_user_id()) {
            \wp_send_json_error(['message' => '不允許刪除自己的帳號']);
        }

        global $wpdb;
        $bindings_table = $wpdb->prefix . 'buygo_line_users';

        // 1. 刪除 LINE 綁定資料
        $wpdb->delete($bindings_table, ['user_id' => $user_id]);

        // 2. 刪除 Profile Sync 日誌
        \delete_option("buygo_line_sync_log_{$user_id}");
        \delete_option("buygo_line_conflict_log_{$user_id}");

        // 3. 刪除 WordPress 用戶（會自動刪除所有 user_meta）
        require_once ABSPATH . 'wp-admin/includes/user.php';
        $deleted = \wp_delete_user($user_id);

        if (!$deleted) {
            \wp_send_json_error(['message' => '刪除用戶失敗']);
        }

        \wp_send_json_success(['message' => '用戶已刪除']);
    }

    /**
     * AJAX: 刪除所有測試用戶（排除管理員）
     */
    public static function ajax_delete_all_test_users(): void
    {
        // 驗證 nonce
        \check_ajax_referer('buygo_line_dev_tools', 'nonce');

        // 驗證權限
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(['message' => '權限不足']);
        }

        global $wpdb;
        $bindings_table = $wpdb->prefix . 'buygo_line_users';

        // 取得所有已綁定 LINE 的用戶
        $results = $wpdb->get_results(
            "SELECT user_id FROM {$bindings_table}",
            ARRAY_A
        );

        $deleted_count = 0;
        $current_user_id = \get_current_user_id();

        require_once ABSPATH . 'wp-admin/includes/user.php';

        foreach ($results as $row) {
            $user_id = (int) $row['user_id'];
            $user = \get_user_by('id', $user_id);

            // 如果 WordPress 用戶不存在，直接刪除孤立的綁定記錄
            if (!$user) {
                $wpdb->delete($bindings_table, ['user_id' => $user_id]);
                \delete_option("buygo_line_sync_log_{$user_id}");
                \delete_option("buygo_line_conflict_log_{$user_id}");
                $deleted_count++;
                continue;
            }

            // 跳過管理員和當前用戶
            if (\in_array('administrator', $user->roles, true) || $user_id === $current_user_id) {
                continue;
            }

            // 刪除 LINE 綁定
            $wpdb->delete($bindings_table, ['user_id' => $user_id]);

            // 刪除 Profile Sync 日誌
            \delete_option("buygo_line_sync_log_{$user_id}");
            \delete_option("buygo_line_conflict_log_{$user_id}");

            // 刪除 WordPress 用戶
            if (\wp_delete_user($user_id)) {
                $deleted_count++;
            }
        }

        \wp_send_json_success([
            'count'   => $deleted_count,
            'message' => "已刪除 {$deleted_count} 個測試用戶",
        ]);
    }
}
