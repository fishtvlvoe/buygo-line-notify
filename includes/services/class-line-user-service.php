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
 * v0.2 重構：使用 wp_buygo_line_users 專用表作為單一真實來源
 *
 * 核心方法（新 API，推薦使用）：
 * - getUserByLineUid()   - 根據 LINE UID 查詢 WordPress User ID
 * - getLineUidByUserId() - 根據 WordPress User ID 查詢 LINE UID
 * - isUserLinked()       - 檢查用戶是否已綁定 LINE
 * - linkUser()           - 建立用戶與 LINE 的綁定關係
 * - unlinkUser()         - 解除綁定（硬刪除，與 Nextend 一致）
 * - getBinding()         - 取得完整綁定資料
 * - getBindingByLineUid() - 根據 LINE UID 取得完整綁定資料
 *
 * 舊方法（已 deprecated，保留向後相容）：
 * - bind_line_account()  - 改呼叫 linkUser()
 * - get_user_line_id()   - 改呼叫 getLineUidByUserId()
 * - get_line_user()      - 使用新表查詢
 * - get_user_binding()   - 改呼叫 getBinding()
 * - unbind_line_account() - 改呼叫 unlinkUser()
 * - is_user_bound()      - 改呼叫 isUserLinked()
 * - is_line_uid_bound()  - 使用 getUserByLineUid() 實作
 * - get_user_id_by_line_uid() - 改呼叫 getUserByLineUid()
 * - get_line_uid_by_user_id() - 改呼叫 getLineUidByUserId()
 * - unbind_line_user()   - 改呼叫 unlinkUser()
 */
class LineUserService {

    // ========================================================================
    // 新 API（v0.2，推薦使用）
    // ========================================================================

    /**
     * 根據 LINE UID 查詢 WordPress User ID
     *
     * @since 2.0.0
     * @param string $line_uid LINE 使用者 ID
     * @return int|null WordPress User ID，未找到則返回 null
     */
    public static function getUserByLineUid(string $line_uid): ?int {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_line_users';

        // 優先查詢 wp_buygo_line_users 表
        $user_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT user_id FROM {$table_name} WHERE identifier = %s LIMIT 1",
                $line_uid
            )
        );

        if ($user_id) {
            return (int) $user_id;
        }

        // Fallback: 查詢 NSL (Nextend Social Login) 的 wp_social_users 表
        $nsl_table = $wpdb->prefix . 'social_users';
        $nsl_table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $nsl_table
        ));

        if ($nsl_table_exists) {
            $user_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT ID FROM {$nsl_table} WHERE identifier = %s AND type = 'line' LIMIT 1",
                    $line_uid
                )
            );

            if ($user_id) {
                return (int) $user_id;
            }
        }

        return null;
    }

    /**
     * 根據 WordPress User ID 查詢 LINE UID
     *
     * @since 2.0.0
     * @param int $user_id WordPress 使用者 ID
     * @return string|null LINE UID，未找到則返回 null
     */
    public static function getLineUidByUserId(int $user_id): ?string {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_line_users';

        // 優先查詢 wp_buygo_line_users 表
        $line_uid = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT identifier FROM {$table_name} WHERE user_id = %d LIMIT 1",
                $user_id
            )
        );

        if ($line_uid) {
            return $line_uid;
        }

        // Fallback: 查詢 NSL (Nextend Social Login) 的 wp_social_users 表
        $nsl_table = $wpdb->prefix . 'social_users';
        $nsl_table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $nsl_table
        ));

        if ($nsl_table_exists) {
            $line_uid = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT identifier FROM {$nsl_table} WHERE ID = %d AND type = 'line' LIMIT 1",
                    $user_id
                )
            );

            if ($line_uid) {
                return $line_uid;
            }
        }

        return null;
    }

    /**
     * 檢查用戶是否已綁定 LINE
     *
     * @since 2.0.0
     * @param int $user_id WordPress 使用者 ID
     * @return bool 是否已綁定
     */
    public static function isUserLinked(int $user_id): bool {
        return !is_null(self::getLineUidByUserId($user_id));
    }

    /**
     * 建立用戶與 LINE 的綁定關係
     *
     * @since 2.0.0
     * @param int    $user_id         WordPress 使用者 ID
     * @param string $line_uid        LINE 使用者 ID
     * @param bool   $is_registration 是否為註冊流程（已棄用，不再使用）
     * @return bool 是否成功
     */
    public static function linkUser(int $user_id, string $line_uid, bool $is_registration = false): bool {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_line_users';

        // 檢查 LINE UID 是否已綁定其他用戶
        $existing_user_id = self::getUserByLineUid($line_uid);
        if ($existing_user_id && $existing_user_id !== $user_id) {
            // LINE UID 已綁定其他用戶，拒絕
            return false;
        }

        // 檢查用戶是否已綁定其他 LINE
        $existing_line_uid = self::getLineUidByUserId($user_id);
        if ($existing_line_uid && $existing_line_uid !== $line_uid) {
            // 用戶已綁定其他 LINE，拒絕
            return false;
        }

        // 若已存在相同綁定，更新 updated_at
        if ($existing_user_id === $user_id && $existing_line_uid === $line_uid) {
            $update_data = [
                'updated_at' => current_time('mysql'),
            ];

            $result = $wpdb->update(
                $table_name,
                $update_data,
                [
                    'user_id'  => $user_id,
                    'line_uid' => $line_uid,
                ],
                ['%s'],
                ['%d', '%s']
            );

            return $result !== false;
        }

        // 新增綁定
        $insert_data = [
            'line_uid'   => $line_uid,
            'user_id'    => $user_id,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];

        $formats = ['%s', '%d', '%s', '%s']; // line_uid, user_id, created_at, updated_at

        $result = $wpdb->insert(
            $table_name,
            $insert_data,
            $formats
        );

        return $result !== false;
    }

    /**
     * 解除綁定（硬刪除，同時刪除 buygo_line_users 和 NSL social_users）
     *
     * @since 2.0.0
     * @param int $user_id WordPress 使用者 ID
     * @return bool 是否成功
     */
    public static function unlinkUser(int $user_id): bool {
        global $wpdb;
        $success = false;

        // 刪除 wp_buygo_line_users 表的記錄
        $table_name = $wpdb->prefix . 'buygo_line_users';
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;

        if ($table_exists) {
            $result1 = $wpdb->delete(
                $table_name,
                ['user_id' => $user_id],
                ['%d']
            );
            if ($result1 !== false) {
                $success = true;
                error_log("[LineUserService] Deleted from buygo_line_users for user {$user_id}");
            }
        }

        // 同時刪除 NSL wp_social_users 表的 LINE 記錄
        $nsl_table = $wpdb->prefix . 'social_users';
        $nsl_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $nsl_table)) === $nsl_table;

        if ($nsl_table_exists) {
            // NSL 表的 ID 欄位是 WordPress User ID
            $result2 = $wpdb->delete(
                $nsl_table,
                [
                    'ID' => $user_id,
                    'type' => 'line'
                ],
                ['%d', '%s']
            );
            if ($result2 !== false && $result2 > 0) {
                $success = true;
                error_log("[LineUserService] Deleted from social_users for user {$user_id}");
            }
        }

        return $success;
    }

    /**
     * 取得完整的綁定資料
     *
     * @since 2.0.0
     * @param int $user_id WordPress 使用者 ID
     * @return object|null 綁定資料物件（id, user_id, line_uid, created_at, updated_at），未找到則返回 null
     */
    public static function getBinding(int $user_id): ?object {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_line_users';

        $binding = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT ID as id, type, identifier as line_uid, user_id,
                        COALESCE(created_at, register_date) as created_at,
                        COALESCE(updated_at, link_date) as updated_at
                 FROM {$table_name}
                 WHERE user_id = %d LIMIT 1",
                $user_id
            )
        );

        // Fallback: 查詢 NSL (Nextend Social Login) 的 wp_social_users 表
        // 注意：NSL 表的 ID 欄位是 WordPress User ID，不是自增主鍵
        if (!$binding) {
            $nsl_table = $wpdb->prefix . 'social_users';
            $nsl_table_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $nsl_table
            ));

            if ($nsl_table_exists) {
                $nsl_binding = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT ID as user_id, identifier as line_uid, identifier,
                                register_date as created_at, link_date as updated_at
                         FROM {$nsl_table}
                         WHERE ID = %d AND type = 'line'
                         LIMIT 1",
                        $user_id
                    )
                );

                if ($nsl_binding) {
                    error_log('[LineUserService] Found NSL binding for user ' . $user_id . ': ' . substr($nsl_binding->line_uid, 0, 20) . '...');
                    return $nsl_binding;
                }
            }
        }

        return $binding ?: null;
    }

    /**
     * 根據 LINE UID 取得完整綁定資料
     *
     * @since 2.0.0
     * @param string $line_uid LINE 使用者 ID
     * @return object|null 綁定資料物件（id, user_id, line_uid, created_at, updated_at），未找到則返回 null
     */
    public static function getBindingByLineUid(string $line_uid): ?object {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_line_users';

        $binding = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE line_uid = %s LIMIT 1",
                $line_uid
            )
        );

        return $binding ?: null;
    }

    // ========================================================================
    // 舊 API（已 deprecated，保留向後相容）
    // ========================================================================

    /**
     * 綁定 LINE 帳號到 WordPress 使用者
     *
     * @deprecated 2.0.0 Use linkUser() instead.
     * @param int    $user_id WordPress 使用者 ID
     * @param string $line_uid LINE 使用者 ID
     * @param array  $profile LINE 個人資料 (displayName, pictureUrl)
     * @return bool 綁定是否成功
     */
    public static function bind_line_account(int $user_id, string $line_uid, array $profile): bool {
        // 使用新 API 建立綁定
        $result = self::linkUser($user_id, $line_uid, false);

        if ($result) {
            // 維持向後相容：寫入 user_meta
            update_user_meta($user_id, 'buygo_line_user_id', $line_uid);
            update_user_meta($user_id, 'buygo_line_display_name', $profile['displayName'] ?? '');
            update_user_meta($user_id, 'buygo_line_picture_url', $profile['pictureUrl'] ?? '');
        }

        return $result;
    }

    /**
     * 根據 user_id 取得 LINE UID
     *
     * @deprecated 2.0.0 Use getLineUidByUserId() instead.
     * @param int $user_id WordPress 使用者 ID
     * @return string|null LINE UID，未綁定則返回 null
     */
    public static function get_user_line_id(int $user_id): ?string {
        return self::getLineUidByUserId($user_id);
    }

    /**
     * 根據 line_uid 取得完整綁定資料
     *
     * @deprecated 2.0.0 Use getBindingByLineUid() instead.
     * @param string $line_uid LINE 使用者 ID
     * @return object|null 綁定資料物件，未找到則返回 null
     */
    public static function get_line_user(string $line_uid): ?object {
        return self::getBindingByLineUid($line_uid);
    }

    /**
     * 根據 user_id 取得完整綁定資料
     *
     * @deprecated 2.0.0 Use getBinding() instead.
     * @param int $user_id WordPress 使用者 ID
     * @return object|null 綁定資料物件，未找到則返回 null
     */
    public static function get_user_binding(int $user_id): ?object {
        return self::getBinding($user_id);
    }

    /**
     * 解除綁定
     *
     * @deprecated 2.0.0 Use unlinkUser() instead.
     * @param int $user_id WordPress 使用者 ID
     * @return bool 是否成功解除綁定
     */
    public static function unbind_line_account(int $user_id): bool {
        $result = self::unlinkUser($user_id);

        if ($result) {
            // 維持向後相容：清除 user_meta
            delete_user_meta($user_id, 'buygo_line_user_id');
            delete_user_meta($user_id, 'buygo_line_display_name');
            delete_user_meta($user_id, 'buygo_line_picture_url');
        }

        return $result;
    }

    /**
     * 檢查使用者是否已綁定 LINE
     *
     * @deprecated 2.0.0 Use isUserLinked() instead.
     * @param int $user_id WordPress 使用者 ID
     * @return bool 是否已綁定
     */
    public static function is_user_bound(int $user_id): bool {
        return self::isUserLinked($user_id);
    }

    /**
     * 檢查 LINE UID 是否已被綁定
     *
     * @deprecated 2.0.0 Use getUserByLineUid() instead and check if result is not null.
     * @param string $line_uid LINE 使用者 ID
     * @return bool 是否已被綁定
     */
    public static function is_line_uid_bound(string $line_uid): bool {
        return !is_null(self::getUserByLineUid($line_uid));
    }

    /**
     * 根據 LINE UID 取得 user ID
     *
     * @deprecated 2.0.0 Use getUserByLineUid() instead.
     * @param string $line_uid LINE 使用者 ID
     * @return int|false User ID，未找到返回 false
     */
    public static function get_user_id_by_line_uid(string $line_uid) {
        $user_id = self::getUserByLineUid($line_uid);
        return $user_id ?: false;
    }

    /**
     * 根據 user ID 取得 LINE UID
     *
     * @deprecated 2.0.0 Use getLineUidByUserId() instead.
     * @param int $user_id WordPress 使用者 ID
     * @return string|false LINE UID，未找到返回 false
     */
    public static function get_line_uid_by_user_id(int $user_id) {
        $line_uid = self::getLineUidByUserId($user_id);
        return $line_uid ?: false;
    }

    /**
     * 解除綁定
     *
     * @deprecated 2.0.0 Use unlinkUser() instead.
     * @param int $user_id WordPress 使用者 ID
     * @return bool 是否成功
     */
    public static function unbind_line_user(int $user_id): bool {
        return self::unlinkUser($user_id);
    }
}
