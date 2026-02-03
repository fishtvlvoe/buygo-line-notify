<?php
/**
 * NSL (Nextend Social Login) Integration
 *
 * 提供與 NSL 外掛的整合功能:
 * 1. 自動強制重新授權 (當用戶被刪除後重新註冊)
 * 2. 刪除用戶時清理綁定資料
 * 3. 確保資料同步到 wp_buygo_line_users
 *
 * @package BuygoLineNotify
 * @subpackage Integrations
 */

namespace BuygoLineNotify\Integrations;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * NSL Integration 類別
 *
 * 這是一個過渡性解決方案,允許在不完全移植 NSL 功能的情況下,
 * 提供自動重新授權和資料清理功能
 */
class NSLIntegration
{
    /**
     * 初始化整合
     */
    public static function init(): void
    {
        // 檢查 NSL 是否啟用
        if (!self::is_nsl_active()) {
            return;
        }

        // Hook 1: 隱藏 NSL 的前台登入按鈕 (只使用 buygo-line-notify 的登入介面)
        add_filter('nsl_is_provider_enabled_line', '__return_false', 9999);
        add_action('wp_head', [__CLASS__, 'hide_nsl_buttons_css'], 9999);
        add_action('login_head', [__CLASS__, 'hide_nsl_buttons_css'], 9999);

        // Hook 2: 在 NSL 登入成功後確保資料同步
        // (保留此 Hook 以處理後台或 API 登入的情況)
        add_action('nsl_login', [__CLASS__, 'ensure_sync_after_login'], 10, 2);

        // Hook 3: 刪除用戶時清理所有 LINE 綁定資料
        add_action('delete_user', [__CLASS__, 'cleanup_on_user_delete'], 10, 1);
        add_action('deleted_user', [__CLASS__, 'cleanup_after_user_deleted'], 10, 1);
    }

    /**
     * 隱藏 NSL 前台登入按鈕 (CSS)
     *
     * 透過 CSS 隱藏所有 NSL 相關的 LINE 登入按鈕
     * 只保留 buygo-line-notify 提供的登入介面
     */
    public static function hide_nsl_buttons_css(): void
    {
        echo '<style type="text/css">
            /* 隱藏 NSL LINE 登入按鈕 */
            .nsl-container .nsl-button-line,
            .nsl-container-block .nsl-button-line,
            a[data-plugin="nsl"][data-provider="line"],
            .nsl-button[data-provider="line"] {
                display: none !important;
            }
        </style>';
    }

    /**
     * 檢查 NSL 外掛是否啟用
     *
     * @return bool
     */
    private static function is_nsl_active(): bool
    {
        // 檢查 NSL 核心類別是否存在
        return class_exists('NextendSocialLogin');
    }

    /**
     * 在生成登入 URL 時,檢查是否需要強制重新授權
     *
     * Hook: nsl_login_button_url_line
     *
     * @param string $url NSL 生成的登入 URL
     * @param array $args NSL 傳遞的參數
     * @return string 修改後的 URL
     */
    public static function force_reauth_if_needed(string $url, array $args = []): string
    {
        global $wpdb;

        // 如果用戶已登入,檢查是否有綁定記錄
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();

            // 檢查 wp_buygo_line_users 中是否有綁定
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}buygo_line_users WHERE user_id = %d",
                $user_id
            ));

            // 如果沒有綁定記錄,強制重新授權
            if (!$exists) {
                $url = add_query_arg('prompt', 'consent', $url);

                // 記錄日誌
                error_log(sprintf(
                    '[BuyGo LINE Notify] Force reauth for user %d (no binding found)',
                    $user_id
                ));
            }
        }

        return $url;
    }

    /**
     * NSL 登入成功後,確保資料同步到 wp_buygo_line_users
     *
     * Hook: nsl_login
     *
     * @param int $user_id WordPress User ID
     * @param string $provider 登入提供者 (line, facebook, google, etc.)
     */
    public static function ensure_sync_after_login(int $user_id, string $provider): void
    {
        // 只處理 LINE 登入
        if ($provider !== 'line') {
            return;
        }

        global $wpdb;

        // 檢查 NSL 表中的 LINE UID
        $nsl_data = $wpdb->get_row($wpdb->prepare(
            "SELECT identifier, register_date FROM {$wpdb->prefix}social_users
             WHERE ID = %d AND type = 'line'",
            $user_id
        ));

        if (!$nsl_data) {
            error_log(sprintf(
                '[BuyGo LINE Notify] NSL login but no social_users record for user %d',
                $user_id
            ));
            return;
        }

        // 檢查 wp_buygo_line_users 中是否已存在
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}buygo_line_users
             WHERE user_id = %d OR identifier = %s",
            $user_id,
            $nsl_data->identifier
        ));

        // 如果不存在,插入記錄
        if (!$exists) {
            $result = $wpdb->insert(
                $wpdb->prefix . 'buygo_line_users',
                [
                    'type' => 'line',
                    'identifier' => $nsl_data->identifier,
                    'user_id' => $user_id,
                    'register_date' => $nsl_data->register_date,
                    'link_date' => current_time('mysql'),
                ],
                ['%s', '%s', '%d', '%s', '%s']
            );

            if ($result) {
                error_log(sprintf(
                    '[BuyGo LINE Notify] Auto-synced LINE binding for user %d after NSL login',
                    $user_id
                ));
            } else {
                error_log(sprintf(
                    '[BuyGo LINE Notify] Failed to sync LINE binding for user %d: %s',
                    $user_id,
                    $wpdb->last_error
                ));
            }
        }
    }

    /**
     * 刪除用戶前清理綁定資料 (delete_user hook)
     *
     * Hook: delete_user
     *
     * @param int $user_id 被刪除的用戶 ID
     */
    public static function cleanup_on_user_delete(int $user_id): void
    {
        global $wpdb;

        // 記錄即將清理的資料
        $line_uid = $wpdb->get_var($wpdb->prepare(
            "SELECT identifier FROM {$wpdb->prefix}buygo_line_users WHERE user_id = %d",
            $user_id
        ));

        if ($line_uid) {
            error_log(sprintf(
                '[BuyGo LINE Notify] User %d deleted, will cleanup LINE binding: %s',
                $user_id,
                substr($line_uid, 0, 20) . '...'
            ));
        }
    }

    /**
     * 用戶刪除後清理所有 LINE 綁定資料 (deleted_user hook)
     *
     * Hook: deleted_user
     *
     * 清理範圍:
     * - wp_buygo_line_users (buygo-line-notify)
     * - wp_social_users (NSL)
     * - user_meta 中的 LINE 相關資料
     *
     * @param int $user_id 已刪除的用戶 ID
     */
    public static function cleanup_after_user_deleted(int $user_id): void
    {
        global $wpdb;

        $cleanup_count = 0;

        // 1. 清理 wp_buygo_line_users
        $deleted = $wpdb->delete(
            $wpdb->prefix . 'buygo_line_users',
            ['user_id' => $user_id],
            ['%d']
        );

        if ($deleted) {
            $cleanup_count += $deleted;
        }

        // 2. 清理 wp_social_users (NSL 表)
        $nsl_table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $wpdb->prefix . 'social_users'
        ));

        if ($nsl_table_exists) {
            $deleted = $wpdb->delete(
                $wpdb->prefix . 'social_users',
                [
                    'ID' => $user_id,
                    'type' => 'line',
                ],
                ['%d', '%s']
            );

            if ($deleted) {
                $cleanup_count += $deleted;
            }
        }

        // 3. 清理 wp_buygo_line_bindings (舊表,如果存在)
        $old_table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $wpdb->prefix . 'buygo_line_bindings'
        ));

        if ($old_table_exists) {
            $deleted = $wpdb->delete(
                $wpdb->prefix . 'buygo_line_bindings',
                ['user_id' => $user_id],
                ['%d']
            );

            if ($deleted) {
                $cleanup_count += $deleted;
            }
        }

        // 4. 清理所有 LINE 相關 user_meta
        $line_meta_keys = [
            'line_uid',
            '_mygo_line_uid',
            'buygo_line_user_id',
            'm_line_user_id',
            'line_user_id',
            'nsl_user_avatar_md5',
        ];

        foreach ($line_meta_keys as $meta_key) {
            delete_user_meta($user_id, $meta_key);
        }

        // 記錄清理結果
        error_log(sprintf(
            '[BuyGo LINE Notify] Cleaned up LINE bindings for deleted user %d: %d records removed',
            $user_id,
            $cleanup_count
        ));
    }

    /**
     * 取得整合狀態資訊
     *
     * @return array 整合狀態
     */
    public static function get_status(): array
    {
        global $wpdb;

        $nsl_active = self::is_nsl_active();

        $status = [
            'nsl_active' => $nsl_active,
            'nsl_plugin_exists' => class_exists('NextendSocialLogin'),
            'hooks_registered' => false,
        ];

        if ($nsl_active) {
            // 檢查 Hooks 是否已註冊
            $status['hooks_registered'] = (
                has_filter('nsl_login_button_url_line') &&
                has_action('nsl_login') &&
                has_action('delete_user') &&
                has_action('deleted_user')
            );

            // 統計資料
            $status['total_nsl_users'] = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}social_users WHERE type = 'line'"
            );

            $status['total_synced_users'] = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}buygo_line_users"
            );
        }

        return $status;
    }
}
