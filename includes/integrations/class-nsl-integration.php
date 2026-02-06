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

        // Hook 1: MVP 階段 - 使用 NSL 按鈕,隱藏 buygo-line-notify 按鈕
        // 因為我們使用 NSL 的 OAuth 流程和 Callback URL
        // 直接在此處移除 hooks (在 LoginButtonService::register_hooks() 之後執行)
        self::hide_buygo_line_notify_buttons();

        // Hook 2: 在 NSL 登入成功後確保資料同步
        // (保留此 Hook 以處理後台或 API 登入的情況)
        add_action('nsl_login', [__CLASS__, 'ensure_sync_after_login'], 10, 2);

        // Hook 3: 刪除用戶時清理所有 LINE 綁定資料
        add_action('delete_user', [__CLASS__, 'cleanup_on_user_delete'], 10, 1);
        add_action('deleted_user', [__CLASS__, 'cleanup_after_user_deleted'], 10, 1);

        // Hook 4: 攔截用戶匹配流程,在 auto_link 前移除舊的 LINE 綁定
        // 時機: NSL 檢測到 Email 已存在後,在執行 auto_link 之前
        // 參考: wp-content/plugins/nextend-facebook-connect/includes/user.php line 123
        add_filter('nsl_match_social_account_to_user_id', [__CLASS__, 'remove_old_line_binding_before_match'], 10, 3);

        // Hook 5: 允許 auto_link
        add_filter('nsl_line_auto_link_allowed', '__return_true', 999);
    }

    /**
     * 隱藏 buygo-line-notify 的登入按鈕
     *
     * MVP 階段使用 NSL 的 OAuth 流程,因此移除 buygo-line-notify 的登入按鈕 hooks
     * 避免 Callback URL 衝突
     */
    public static function hide_buygo_line_notify_buttons(): void
    {
        // 取得 LoginButtonService 使用的 priority
        $position = \BuygoLineNotify\Services\SettingsService::get_login_button_position();
        $priority = ($position === 'after') ? 20 : 5;

        // 移除 LoginButtonService 註冊的所有 hooks (必須指定正確的 priority)
        remove_action('fluent_community/before_auth_form_header', ['BuygoLineNotify\\Services\\LoginButtonService', 'render_fluent_community_button'], $priority);
        remove_action('lrm/login_form/before', ['BuygoLineNotify\\Services\\LoginButtonService', 'render_lrm_button'], $priority);
        remove_action('login_form', ['BuygoLineNotify\\Services\\LoginButtonService', 'render_wp_login_button'], $priority);
        remove_action('register_form', ['BuygoLineNotify\\Services\\LoginButtonService', 'render_wp_login_button'], $priority);

        error_log('[NSL Integration] buygo-line-notify 登入按鈕已停用 (priority: ' . $priority . '),使用 NSL 按鈕');
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
     * @param mixed $provider 登入提供者 (可能是 string 或 NextendSocialPROProviderLine 物件)
     */
    public static function ensure_sync_after_login(int $user_id, $provider): void
    {
        // 取得 provider 名稱 (可能是 string 或物件)
        $provider_name = is_string($provider) ? $provider : (method_exists($provider, 'getId') ? $provider->getId() : '');

        // 只處理 LINE 登入
        if ($provider_name !== 'line') {
            error_log('[NSL Integration] Skipping sync for provider: ' . $provider_name);
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
     * 在用戶匹配時移除舊的 LINE 綁定
     *
     * Hook: nsl_match_social_account_to_user_id
     *
     * 核心問題:
     * - NSL 的 linkUserToProviderIdentifier() 會檢查用戶是否已綁定不同的 LINE ID
     * - 如果已綁定,返回 false,導致 auto_link 失敗
     *
     * 解決方案:
     * - 在 NSL 匹配用戶時,如果檢測到用戶已綁定不同的 LINE ID
     * - 刪除舊綁定,允許綁定新的 LINE 帳號
     *
     * @param int|false $user_id 匹配到的用戶 ID (如果 Email 存在)
     * @param object $user_data NSL 用戶資料物件
     * @param object $provider NSL Provider 物件
     * @return int|false 返回用戶 ID (允許繼續) 或 false (阻止)
     */
    public static function remove_old_line_binding_before_match($user_id, $user_data, $provider)
    {
        error_log('[NSL Integration] remove_old_line_binding_before_match() CALLED');
        error_log('[NSL Integration] user_id: ' . var_export($user_id, true));
        error_log('[NSL Integration] provider type: ' . (method_exists($provider, 'getId') ? $provider->getId() : 'unknown'));

        // 只處理 LINE provider
        if (!method_exists($provider, 'getId') || $provider->getId() !== 'line') {
            error_log('[NSL Integration] Not LINE provider, skipping');
            return $user_id;
        }

        // 如果沒有匹配到用戶,不需要處理
        if ($user_id === false) {
            error_log('[NSL Integration] user_id is false, no email match found');
            return $user_id;
        }

        global $wpdb;

        // 檢查現有用戶是否已綁定 LINE
        $old_line_id = $wpdb->get_var($wpdb->prepare(
            "SELECT identifier FROM {$wpdb->prefix}social_users
             WHERE ID = %d AND type = 'line'",
            $user_id
        ));

        error_log('[NSL Integration] Old LINE binding check: ' . var_export($old_line_id, true));

        if (!$old_line_id) {
            error_log('[NSL Integration] No old binding found, allowing auto_link');
            return $user_id; // 沒有舊綁定,直接允許 auto_link
        }

        // 取得當前要綁定的 LINE ID
        $new_line_id = $user_data->getAuthUserData('id');
        error_log('[NSL Integration] New LINE ID from OAuth: ' . var_export($new_line_id, true));

        if (empty($new_line_id)) {
            error_log('[NSL Integration] Unable to get new LINE identifier from provider');
            return $user_id;
        }

        // 如果是相同的 LINE ID,不需要處理
        if ($old_line_id === $new_line_id) {
            error_log(sprintf(
                '[NSL Integration] User %d already linked to the same LINE account',
                $user_id
            ));
            return $user_id;
        }

        error_log(sprintf(
            '[NSL Integration] CONFLICT DETECTED - User %d has old LINE ID, attempting to bind new LINE ID',
            $user_id
        ));
        error_log('[NSL Integration] Old LINE ID: ' . substr($old_line_id, 0, 20) . '...');
        error_log('[NSL Integration] New LINE ID: ' . substr($new_line_id, 0, 20) . '...');

        // 刪除舊的 LINE 綁定
        $deleted = $wpdb->delete(
            $wpdb->prefix . 'social_users',
            [
                'ID' => $user_id,
                'type' => 'line',
            ],
            ['%d', '%s']
        );

        error_log('[NSL Integration] DELETE result: ' . var_export($deleted, true));
        error_log('[NSL Integration] Last SQL error: ' . var_export($wpdb->last_error, true));

        if ($deleted) {
            // 驗證刪除是否成功
            $verify_deleted = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}social_users
                 WHERE ID = %d AND type = 'line'",
                $user_id
            ));

            error_log('[NSL Integration] Verification after delete - remaining records: ' . $verify_deleted);

            error_log(sprintf(
                '[NSL Integration] ✅ Removed old LINE binding for user %d (old: %s, new: %s) to allow auto_link',
                $user_id,
                substr($old_line_id, 0, 20) . '...',
                substr($new_line_id, 0, 20) . '...'
            ));

            // 同時清除 buygo_line_users 中的舊綁定
            $buygo_deleted = $wpdb->delete(
                $wpdb->prefix . 'buygo_line_users',
                ['user_id' => $user_id],
                ['%d']
            );

            error_log('[NSL Integration] buygo_line_users DELETE result: ' . var_export($buygo_deleted, true));
        } else {
            error_log(sprintf(
                '[NSL Integration] ❌ Failed to remove old LINE binding for user %d - wpdb->delete returned false/0',
                $user_id
            ));
        }

        error_log('[NSL Integration] Returning user_id: ' . $user_id . ' to continue auto_link flow');
        return $user_id; // 返回用戶 ID,允許 auto_link 繼續
    }

    /**
     * 處理 Email 重複的情況（已棄用）
     *
     * @deprecated 2.0.0 改用 remove_old_line_binding_before_autolink()
     */
    public static function handle_duplicate_email(bool $hasError, $provider, $errors): bool
    {
        return $hasError;
    }

    /**
     * 在註冊新用戶前連結到現有帳號（已棄用 - NSL 原生 auto_link 已處理）
     *
     * @deprecated 2.0.0 NSL 的 auto_link 功能已透過 nsl_line_auto_link_allowed filter 啟用
     * @param array $userData NSL 準備註冊的用戶資料
     */
    public static function link_to_existing_account_if_needed(array &$userData): void
    {
        // 此方法已棄用,NSL 的 auto_link 機制會自動處理 Email 相同的情況
        // 參考: wp-content/plugins/nextend-facebook-connect/includes/user.php line 714-743
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
