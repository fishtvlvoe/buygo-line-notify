<?php
/**
 * LINE User Service
 *
 * 管理 LINE 帳號綁定與查詢，實作混合儲存策略（user_meta + custom table）
 *
 * @package BuygoLineNotify
 */

namespace BuygoLineNotify\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * LINE User Service class
 *
 * 提供 LINE 用戶綁定的寫入和查詢 API
 * 實作混合儲存：
 * - user_meta: 快速查詢（有 WordPress 快取）
 * - bindings 表: 完整歷史和進階查詢
 */
class LineUserService {

    /**
     * 綁定 LINE 帳號到 WordPress 使用者
     *
     * 雙寫策略：custom table（主要）+ user_meta（相容性）
     *
     * @param int    $user_id WordPress 使用者 ID
     * @param string $line_uid LINE 使用者 ID
     * @param array  $profile LINE 個人資料 (displayName, pictureUrl)
     * @return bool 綁定是否成功
     */
    public static function bind_line_account(int $user_id, string $line_uid, array $profile): bool {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_line_bindings';

        // 檢查是否已綁定（同一 user_id 或 line_uid）
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE user_id = %d OR line_uid = %s",
                $user_id,
                $line_uid
            )
        );

        if ($existing) {
            // 更新現有綁定
            $result = $wpdb->update(
                $table_name,
                [
                    'user_id'      => $user_id,
                    'line_uid'     => $line_uid,
                    'display_name' => $profile['displayName'] ?? '',
                    'picture_url'  => $profile['pictureUrl'] ?? '',
                    'status'       => 'active',
                ],
                ['id' => $existing->id],
                ['%d', '%s', '%s', '%s', '%s'],
                ['%d']
            );
        } else {
            // 新增綁定
            $result = $wpdb->insert(
                $table_name,
                [
                    'user_id'      => $user_id,
                    'line_uid'     => $line_uid,
                    'display_name' => $profile['displayName'] ?? '',
                    'picture_url'  => $profile['pictureUrl'] ?? '',
                    'status'       => 'active',
                ],
                ['%d', '%s', '%s', '%s', '%s']
            );
        }

        if ($result === false) {
            return false;
        }

        // 同時寫入 user_meta（向後相容）
        update_user_meta($user_id, 'buygo_line_user_id', $line_uid);
        update_user_meta($user_id, 'buygo_line_display_name', $profile['displayName'] ?? '');
        update_user_meta($user_id, 'buygo_line_picture_url', $profile['pictureUrl'] ?? '');

        return true;
    }

    /**
     * 根據 user_id 取得 LINE UID
     *
     * 優先從 user_meta 讀取（有 WordPress 快取）
     *
     * @param int $user_id WordPress 使用者 ID
     * @return string|null LINE UID，未綁定則返回 null
     */
    public static function get_user_line_id(int $user_id): ?string {
        // 優先從 user_meta（WordPress 快取）
        $line_id = get_user_meta($user_id, 'buygo_line_user_id', true);

        if (!empty($line_id)) {
            return $line_id;
        }

        // 備用：從 custom table 查詢
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_line_bindings';
        $line_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT line_uid FROM {$table_name} WHERE user_id = %d AND status = 'active'",
                $user_id
            )
        );

        return $line_id ?: null;
    }

    /**
     * 根據 line_uid 取得完整綁定資料
     *
     * @param string $line_uid LINE 使用者 ID
     * @return object|null 綁定資料物件，未找到則返回 null
     */
    public static function get_line_user(string $line_uid): ?object {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_line_bindings';

        $user = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE line_uid = %s AND status = 'active'",
                $line_uid
            )
        );

        return $user ?: null;
    }

    /**
     * 根據 user_id 取得完整綁定資料
     *
     * @param int $user_id WordPress 使用者 ID
     * @return object|null 綁定資料物件，未找到則返回 null
     */
    public static function get_user_binding(int $user_id): ?object {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_line_bindings';

        $binding = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE user_id = %d AND status = 'active'",
                $user_id
            )
        );

        return $binding ?: null;
    }

    /**
     * 解除綁定（軟刪除）
     *
     * 將狀態設為 inactive，保留歷史記錄
     *
     * @param int $user_id WordPress 使用者 ID
     * @return bool 是否成功解除綁定
     */
    public static function unbind_line_account(int $user_id): bool {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_line_bindings';

        // 更新資料表狀態為 inactive
        $result = $wpdb->update(
            $table_name,
            ['status' => 'inactive'],
            ['user_id' => $user_id],
            ['%s'],
            ['%d']
        );

        if ($result === false) {
            return false;
        }

        // 同時清除 user_meta
        delete_user_meta($user_id, 'buygo_line_user_id');
        delete_user_meta($user_id, 'buygo_line_display_name');
        delete_user_meta($user_id, 'buygo_line_picture_url');

        return true;
    }

    /**
     * 檢查使用者是否已綁定 LINE
     *
     * @param int $user_id WordPress 使用者 ID
     * @return bool 是否已綁定
     */
    public static function is_user_bound(int $user_id): bool {
        return !empty(self::get_user_line_id($user_id));
    }

    /**
     * 檢查 LINE UID 是否已被綁定
     *
     * @param string $line_uid LINE 使用者 ID
     * @return bool 是否已被綁定
     */
    public static function is_line_uid_bound(string $line_uid): bool {
        return !is_null(self::get_line_user($line_uid));
    }
}
