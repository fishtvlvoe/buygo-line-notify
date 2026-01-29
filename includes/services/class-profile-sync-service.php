<?php

namespace BuygoLineNotify\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ProfileSyncService
 *
 * 處理 LINE profile 同步到 WordPress 用戶的核心邏輯
 * 支援註冊/登入/綁定三種場景，包含衝突處理策略和同步日誌記錄
 */
class ProfileSyncService
{
    /**
     * 同步 LINE profile 到 WordPress 用戶
     *
     * @param int $user_id WordPress 用戶 ID
     * @param array $line_profile LINE profile 資料 (displayName, email, pictureUrl)
     * @param string $action 觸發動作 ('register', 'login', 'link')
     * @return bool|\WP_Error 成功返回 true，失敗返回 WP_Error
     */
    public static function syncProfile(int $user_id, array $line_profile, string $action)
    {
        // 取得用戶現有資料
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return new \WP_Error('user_not_found', '用戶不存在');
        }

        // 取得衝突處理策略
        $conflict_strategy = SettingsService::get_conflict_strategy();

        // 記錄變更前的值
        $old_values = [
            'display_name' => $user->display_name,
            'user_email' => $user->user_email,
            'avatar_url' => get_user_meta($user_id, 'buygo_line_avatar_url', true),
        ];

        // 準備更新資料
        $update_data = ['ID' => $user_id];
        $changed_fields = [];

        // 處理 display_name
        if (isset($line_profile['displayName']) && !empty($line_profile['displayName'])) {
            $new_display_name = sanitize_text_field($line_profile['displayName']);
            if (self::shouldUpdateField('display_name', $user->display_name, $new_display_name, $conflict_strategy, $action)) {
                $update_data['display_name'] = $new_display_name;
                $changed_fields[] = 'display_name';
            }
        }

        // 處理 user_email
        if (isset($line_profile['email']) && !empty($line_profile['email'])) {
            $new_email = sanitize_email($line_profile['email']);

            // 檢查 email 是否已被其他用戶使用
            $email_exists = email_exists($new_email);
            if ($email_exists && $email_exists !== $user_id) {
                // Email 已被其他用戶使用，跳過
                error_log("ProfileSyncService: Email {$new_email} already exists for another user");
            } else {
                if (self::shouldUpdateField('user_email', $user->user_email, $new_email, $conflict_strategy, $action)) {
                    $update_data['user_email'] = $new_email;
                    $changed_fields[] = 'user_email';
                }
            }
        }

        // 執行用戶資料更新
        if (count($update_data) > 1) { // 除了 ID 之外還有其他欄位
            $result = wp_update_user($update_data);
            if (is_wp_error($result)) {
                return $result;
            }
        }

        // 處理頭像
        if (isset($line_profile['pictureUrl']) && !empty($line_profile['pictureUrl'])) {
            $new_avatar_url = esc_url_raw($line_profile['pictureUrl']);
            $current_avatar = get_user_meta($user_id, 'buygo_line_avatar_url', true);

            if (self::shouldUpdateField('avatar_url', $current_avatar, $new_avatar_url, $conflict_strategy, $action)) {
                update_user_meta($user_id, 'buygo_line_avatar_url', $new_avatar_url);
                update_user_meta($user_id, 'buygo_line_avatar_updated', current_time('mysql'));
                $changed_fields[] = 'avatar_url';
            }
        }

        // 記錄同步日誌
        if (!empty($changed_fields)) {
            self::logSync($user_id, $action, $changed_fields, $old_values, $line_profile);
        }

        return true;
    }

    /**
     * 判斷是否應該更新欄位
     *
     * @param string $field 欄位名稱
     * @param mixed $current_value 當前值
     * @param mixed $new_value 新值
     * @param string $strategy 衝突策略 ('line_priority', 'wordpress_priority', 'manual')
     * @param string $action 觸發動作 ('register', 'login', 'link')
     * @return bool 是否應該更新
     */
    private static function shouldUpdateField(string $field, $current_value, $new_value, string $strategy, string $action): bool
    {
        // register 動作：強制同步（無視衝突策略），只要有新值就更新
        if ($action === 'register') {
            return !empty($new_value);
        }

        // login 動作：檢查是否啟用登入時同步
        if ($action === 'login') {
            if (!SettingsService::get_sync_on_login()) {
                return false;
            }
        }

        // 若當前值為空，始終更新
        if (empty($current_value)) {
            return true;
        }

        // 若當前值與新值相同，跳過
        if ($current_value === $new_value) {
            return false;
        }

        // 依據衝突策略決定
        switch ($strategy) {
            case 'line_priority':
                // 有新值就覆蓋
                return !empty($new_value);

            case 'wordpress_priority':
                // 保留現有值
                return false;

            case 'manual':
                // 記錄衝突，不自動更新
                self::logConflict(0, $field, $current_value, $new_value); // user_id 在 logConflict 中從上下文取得
                return false;

            default:
                return false;
        }
    }

    /**
     * 記錄同步日誌
     *
     * @param int $user_id 用戶 ID
     * @param string $action 觸發動作
     * @param array $changed_fields 變更的欄位列表
     * @param array $old_values 舊值
     * @param array $line_profile LINE profile 資料
     * @return void
     */
    private static function logSync(int $user_id, string $action, array $changed_fields, array $old_values, array $line_profile): void
    {
        $log_key = "buygo_line_sync_log_{$user_id}";
        $logs = get_option($log_key, []);

        // 準備新值
        $new_values = [];
        if (in_array('display_name', $changed_fields)) {
            $new_values['display_name'] = $line_profile['displayName'] ?? '';
        }
        if (in_array('user_email', $changed_fields)) {
            $new_values['user_email'] = $line_profile['email'] ?? '';
        }
        if (in_array('avatar_url', $changed_fields)) {
            $new_values['avatar_url'] = $line_profile['pictureUrl'] ?? '';
        }

        // 新增日誌記錄
        $logs[] = [
            'timestamp' => current_time('mysql'),
            'action' => $action,
            'changed_fields' => $changed_fields,
            'old_values' => $old_values,
            'new_values' => $new_values,
        ];

        // 保留最近 10 筆
        $logs = array_slice($logs, -10);

        // 儲存到 wp_options (autoload=false)
        update_option($log_key, $logs, false);
    }

    /**
     * 記錄衝突日誌
     *
     * @param int $user_id 用戶 ID
     * @param string $field 欄位名稱
     * @param mixed $current_value 當前值
     * @param mixed $new_value 新值
     * @return void
     */
    private static function logConflict(int $user_id, string $field, $current_value, $new_value): void
    {
        // 在實際呼叫時，user_id 需要從上下文取得
        // 這裡設計為靜態方法，暫時使用參數傳遞
        if ($user_id === 0) {
            // 無法取得 user_id，記錄到通用日誌
            error_log("ProfileSyncService: Conflict detected - field: {$field}, current: {$current_value}, new: {$new_value}");
            return;
        }

        $conflict_key = "buygo_line_conflict_log_{$user_id}";
        $conflicts = get_option($conflict_key, []);

        // 新增衝突記錄
        $conflicts[] = [
            'timestamp' => current_time('mysql'),
            'field' => $field,
            'current_value' => $current_value,
            'new_value' => $new_value,
        ];

        // 保留最近 10 筆
        $conflicts = array_slice($conflicts, -10);

        // 儲存到 wp_options (autoload=false)
        update_option($conflict_key, $conflicts, false);
    }

    /**
     * 取得同步日誌
     *
     * @param int $user_id 用戶 ID
     * @return array 同步日誌陣列
     */
    public static function getSyncLog(int $user_id): array
    {
        $log_key = "buygo_line_sync_log_{$user_id}";
        return get_option($log_key, []);
    }

    /**
     * 清除同步日誌
     *
     * @param int $user_id 用戶 ID
     * @return bool 是否成功清除
     */
    public static function clearSyncLog(int $user_id): bool
    {
        $log_key = "buygo_line_sync_log_{$user_id}";
        return delete_option($log_key);
    }

    /**
     * 取得衝突日誌
     *
     * @param int $user_id 用戶 ID
     * @return array 衝突日誌陣列
     */
    public static function getConflictLog(int $user_id): array
    {
        $conflict_key = "buygo_line_conflict_log_{$user_id}";
        return get_option($conflict_key, []);
    }

    /**
     * 清除衝突日誌
     *
     * @param int $user_id 用戶 ID
     * @return bool 是否成功清除
     */
    public static function clearConflictLog(int $user_id): bool
    {
        $conflict_key = "buygo_line_conflict_log_{$user_id}";
        return delete_option($conflict_key);
    }
}
