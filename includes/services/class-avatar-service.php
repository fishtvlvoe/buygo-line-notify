<?php
/**
 * Avatar Service
 *
 * 整合 LINE 頭像到 WordPress get_avatar_url filter hook
 *
 * @package BuygoLineNotify
 */

namespace BuygoLineNotify\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Avatar Service class
 *
 * 實作 WordPress get_avatar_url filter hook，返回 LINE 頭像 URL
 *
 * 功能：
 * - filterAvatarUrl() - get_avatar_url filter hook 實作
 * - getUserIdFromMixed() - 從混合類型參數解析出 user_id
 * - clearAvatarCache() - 清除單一用戶的頭像快取
 * - clearAllAvatarCache() - 清除所有用戶的頭像快取
 *
 * 快取策略：
 * - 頭像 URL 快取在 user_meta（buygo_line_avatar_url）
 * - 更新時間記錄在 user_meta（buygo_line_avatar_updated）
 * - 快取有效期 7 天，過期時返回舊 URL（等下次登入更新）
 *
 * @since 2.0.0
 */
class AvatarService {

    /**
     * 初始化 Avatar Service
     *
     * @since 2.0.0
     * @return void
     */
    public static function init(): void {
        add_filter('get_avatar_url', [__CLASS__, 'filterAvatarUrl'], 10, 3);
    }

    /**
     * Filter hook: 返回 LINE 頭像 URL
     *
     * @since 2.0.0
     * @param string $url 原始頭像 URL
     * @param mixed $id_or_email 用戶 ID、email 或 WP_User/WP_Comment 物件
     * @param array $args 參數陣列
     * @return string 過濾後的頭像 URL
     */
    public static function filterAvatarUrl($url, $id_or_email, $args) {
        // 1. 解析出 user_id
        $user_id = self::getUserIdFromMixed($id_or_email);
        if (!$user_id) {
            return $url; // 無法識別用戶，返回原始 URL
        }

        // 2. 檢查用戶是否綁定 LINE
        if (!LineUserService::isUserLinked($user_id)) {
            return $url; // 未綁定，返回原始 URL
        }

        // 3. 讀取快取的 LINE 頭像 URL
        $line_avatar_url = get_user_meta($user_id, 'buygo_line_avatar_url', true);
        $avatar_updated = get_user_meta($user_id, 'buygo_line_avatar_updated', true);

        // 4. 若沒有快取，返回原始 URL
        if (empty($line_avatar_url)) {
            return $url;
        }

        // 5. 檢查快取是否過期（7 天）
        if (!empty($avatar_updated)) {
            $updated_time = strtotime($avatar_updated);
            $cache_duration = 7 * DAY_IN_SECONDS;

            if ((time() - $updated_time) < $cache_duration) {
                return $line_avatar_url; // 快取有效，直接返回
            }
        }

        // 6. 快取過期，仍返回舊 URL（等下次登入更新）
        // 不在此處主動請求 LINE API（避免阻塞頁面渲染，且需要 access_token）
        return $line_avatar_url;
    }

    /**
     * 從混合類型參數解析出 user_id
     *
     * @since 2.0.0
     * @param mixed $id_or_email
     * @return int|null
     */
    private static function getUserIdFromMixed($id_or_email): ?int {
        // 數字 ID
        if (is_numeric($id_or_email)) {
            return (int) $id_or_email;
        }

        // WP_User 物件
        if ($id_or_email instanceof \WP_User) {
            return $id_or_email->ID;
        }

        // WP_Comment 物件
        if ($id_or_email instanceof \WP_Comment) {
            return $id_or_email->user_id ? (int) $id_or_email->user_id : null;
        }

        // WP_Post 物件
        if ($id_or_email instanceof \WP_Post) {
            return (int) $id_or_email->post_author;
        }

        // Email 字串
        if (is_string($id_or_email) && is_email($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
            return $user ? $user->ID : null;
        }

        return null;
    }

    /**
     * 清除單一用戶的頭像快取
     *
     * @since 2.0.0
     * @param int $user_id
     * @return bool
     */
    public static function clearAvatarCache(int $user_id): bool {
        delete_user_meta($user_id, 'buygo_line_avatar_updated');
        // 注意：不刪除 buygo_line_avatar_url，讓快取過期時仍可顯示舊頭像
        return true;
    }

    /**
     * 清除所有用戶的頭像快取
     *
     * @since 2.0.0
     * @return int 清除的記錄數
     */
    public static function clearAllAvatarCache(): int {
        global $wpdb;

        $result = $wpdb->query(
            "DELETE FROM {$wpdb->usermeta}
             WHERE meta_key = 'buygo_line_avatar_updated'"
        );

        return $result !== false ? $result : 0;
    }
}
