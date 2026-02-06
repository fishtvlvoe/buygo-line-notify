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

        // Hook 4: 攔截 Email 驗證邏輯，允許重複 Email 自動綁定到現有帳號
        add_filter('nsl_validate_extra_input_email_errors', [__CLASS__, 'handle_duplicate_email'], 10, 3);

        // Hook 5: 在註冊新用戶前，檢查是否應該連結到現有帳號
        add_action('nsl_line_before_register', [__CLASS__, 'link_to_existing_account_if_needed'], 5, 1);
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
     * 處理 Email 重複的情況（攔截驗證邏輯）
     *
     * Hook: nsl_validate_extra_input_email_errors
     *
     * 當用戶填寫已存在的 Email 時：
     * 1. 檢查 auto_link 是否啟用
     * 2. 檢查現有帳號是否已綁定其他 LINE
     * 3. 若條件符合，移除錯誤，允許繼續（稍後在 before_register 時連結）
     *
     * @param bool $hasError 是否有錯誤
     * @param NextendSocialProvider $provider 登入提供者
     * @param WP_Error $errors 錯誤物件
     * @return bool 修改後的錯誤狀態
     */
    public static function handle_duplicate_email(bool $hasError, $provider, $errors): bool
    {
        // 只處理 LINE provider
        if (!method_exists($provider, 'getId') || $provider->getId() !== 'line') {
            return $hasError;
        }

        // 檢查是否有 email_exists 錯誤
        if (!$errors->get_error_code('email_exists')) {
            return $hasError;
        }

        // 檢查 auto_link 設定
        $auto_link = $provider->settings->get('auto_link');
        if ($auto_link === 'disabled') {
            error_log('[NSL Integration] auto_link disabled, keeping email_exists error');
            return $hasError;
        }

        // 取得用戶填寫的 Email
        $email = $_POST['user_email'] ?? '';
        if (empty($email)) {
            return $hasError;
        }

        // 檢查現有帳號
        $existing_user = get_user_by('email', $email);
        if (!$existing_user) {
            return $hasError;
        }

        global $wpdb;

        // 檢查現有帳號是否已綁定 LINE
        $existing_line_binding = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}social_users WHERE ID = %d AND type = 'line'",
            $existing_user->ID
        ));

        if ($existing_line_binding > 0) {
            // 已綁定其他 LINE，保持錯誤
            error_log(sprintf(
                '[NSL Integration] User %d (%s) already has LINE binding, cannot auto-link',
                $existing_user->ID,
                $email
            ));

            // 修改錯誤訊息為更友善的提示
            $errors->remove('email_exists');
            $errors->add('email_exists', __('<strong>錯誤</strong>: 此 Email 已綁定其他 LINE 帳號。請使用該 LINE 帳號登入，或使用其他 Email。', 'buygo-line-notify'), array('form-field' => 'email'));

            return true; // 保持錯誤狀態
        }

        // 條件符合，移除錯誤，允許繼續
        error_log(sprintf(
            '[NSL Integration] Allowing duplicate email %s to auto-link to user %d',
            $email,
            $existing_user->ID
        ));

        $errors->remove('email_exists');

        // 儲存目標 user_id 到 session，供 before_register 使用
        if (!session_id()) {
            session_start();
        }
        $_SESSION['nsl_auto_link_target_user_id'] = $existing_user->ID;
        $_SESSION['nsl_auto_link_target_email'] = $email;

        return false; // 移除錯誤
    }

    /**
     * 在註冊新用戶前，檢查是否應該連結到現有帳號
     *
     * Hook: nsl_line_before_register
     *
     * 若 handle_duplicate_email() 已設定目標 user_id，則：
     * 1. 取消新用戶註冊流程
     * 2. 改為將 LINE identifier 連結到現有帳號
     * 3. 觸發自動登入
     *
     * @param array $userData NSL 準備註冊的用戶資料
     */
    public static function link_to_existing_account_if_needed(array &$userData): void
    {
        if (!session_id()) {
            session_start();
        }

        // 檢查是否有目標 user_id
        $target_user_id = $_SESSION['nsl_auto_link_target_user_id'] ?? null;
        $target_email = $_SESSION['nsl_auto_link_target_email'] ?? null;

        if (!$target_user_id || !$target_email) {
            return;
        }

        // 清除 session
        unset($_SESSION['nsl_auto_link_target_user_id']);
        unset($_SESSION['nsl_auto_link_target_email']);

        error_log(sprintf(
            '[NSL Integration] Linking LINE to existing user %d (%s)',
            $target_user_id,
            $target_email
        ));

        // 取得 LINE identifier（從 NSL 的 persistent data）
        $provider = \NextendSocialLogin::$enabledProviders['line'] ?? null;
        if (!$provider) {
            error_log('[NSL Integration] LINE provider not found');
            return;
        }

        $line_identifier = $provider->getAuthUserData('id');
        if (empty($line_identifier)) {
            error_log('[NSL Integration] LINE identifier not found');
            return;
        }

        global $wpdb;

        // 檢查此 LINE identifier 是否已被其他帳號綁定
        $existing_binding = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->prefix}social_users WHERE identifier = %s AND type = 'line'",
            $line_identifier
        ));

        if ($existing_binding) {
            error_log(sprintf(
                '[NSL Integration] LINE identifier %s already bound to user %d, cannot link to %d',
                substr($line_identifier, 0, 20) . '...',
                $existing_binding,
                $target_user_id
            ));
            return;
        }

        // 建立綁定記錄
        $result = $wpdb->insert(
            $wpdb->prefix . 'social_users',
            [
                'type' => 'line',
                'identifier' => $line_identifier,
                'ID' => $target_user_id,
                'register_date' => current_time('mysql'),
            ],
            ['%s', '%s', '%d', '%s']
        );

        if (!$result) {
            error_log(sprintf(
                '[NSL Integration] Failed to link LINE to user %d: %s',
                $target_user_id,
                $wpdb->last_error
            ));
            return;
        }

        error_log(sprintf(
            '[NSL Integration] Successfully linked LINE %s to user %d',
            substr($line_identifier, 0, 20) . '...',
            $target_user_id
        ));

        // 同步到 wp_buygo_line_users
        $wpdb->insert(
            $wpdb->prefix . 'buygo_line_users',
            [
                'type' => 'line',
                'identifier' => $line_identifier,
                'user_id' => $target_user_id,
                'register_date' => current_time('mysql'),
                'link_date' => current_time('mysql'),
            ],
            ['%s', '%s', '%d', '%s', '%s']
        );

        // 修改 userData 以觸發 NSL 的登入流程
        $userData['user_id'] = $target_user_id;

        // 觸發自動登入（透過 NSL 的機制）
        wp_set_current_user($target_user_id);
        wp_set_auth_cookie($target_user_id, true);

        // 刪除 NSL 的臨時資料
        $provider->deleteLoginPersistentData();
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
