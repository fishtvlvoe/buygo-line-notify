<?php
/**
 * User Service
 *
 * 管理從 LINE 建立用戶和綁定 LINE UID 到現有用戶
 *
 * @package BuygoLineNotify
 */

namespace BuygoLineNotify\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * User Service class
 *
 * 提供用戶建立和 LINE 綁定功能
 * - 從 LINE Profile 建立 WordPress 用戶
 * - 綁定 LINE UID 到現有用戶
 * - 混合儲存：user_meta（快速查詢）+ bindings 表（完整歷史）
 */
class UserService {

    /**
     * LINE User Service instance
     *
     * @var LineUserService
     */
    private $line_user_service;

    /**
     * Constructor
     */
    public function __construct() {
        $this->line_user_service = new LineUserService();
    }

    /**
     * 從 LINE profile 建立 WordPress 用戶
     *
     * @param array $profile LINE profile 資料 (userId, displayName, pictureUrl, email)
     * @return int|\WP_Error 用戶 ID 或錯誤
     */
    public function create_user_from_line(array $profile) {
        // 檢查必要欄位
        if (empty($profile['userId'])) {
            return new \WP_Error('missing_line_uid', 'LINE userId is required');
        }

        $line_uid = $profile['userId'];

        // 檢查 LINE UID 是否已綁定
        $existing_user_id = $this->get_user_by_line_uid($line_uid);
        if ($existing_user_id) {
            return new \WP_Error('line_uid_already_bound', 'This LINE account is already bound to a user', [
                'user_id' => $existing_user_id,
            ]);
        }

        // 準備用戶資料
        $display_name = $profile['displayName'] ?? '';

        // Email: 優先使用 LINE email，否則使用假 email
        $email = !empty($profile['email']) ? $profile['email'] : "line_{$line_uid}@line.local";

        // 生成 username（參考 Nextend Social Login 做法）
        $username = $this->generate_username($profile, $email);

        // 如果 username 重複，加上數字後綴
        $base_username = $username;
        $counter = 1;
        while (username_exists($username)) {
            $username = $base_username . $counter;
            $counter++;
        }

        // 檢查 email 是否已存在
        if (!str_ends_with($email, '@line.local') && email_exists($email)) {
            return new \WP_Error('email_exists', 'Email already exists');
        }

        // 建立用戶
        $user_id = wp_create_user($username, wp_generate_password(), $email);

        if (is_wp_error($user_id)) {
            return new \WP_Error('user_creation_failed', 'Failed to create user', [
                'original_error' => $user_id->get_error_message(),
            ]);
        }

        // 更新用戶資料
        wp_update_user([
            'ID' => $user_id,
            'display_name' => $display_name,
            'role' => 'subscriber',
        ]);

        // 儲存 profile picture
        if (!empty($profile['pictureUrl'])) {
            update_user_meta($user_id, 'line_picture_url', sanitize_url($profile['pictureUrl']));
        }

        // 綁定 LINE UID
        $bind_result = $this->bind_line_to_user($user_id, $profile);
        if (is_wp_error($bind_result)) {
            // 綁定失敗，刪除剛建立的用戶（清理）
            wp_delete_user($user_id);
            return new \WP_Error('bind_failed_after_creation', 'User created but binding failed', [
                'original_error' => $bind_result->get_error_message(),
            ]);
        }

        return $user_id;
    }

    /**
     * 綁定 LINE UID 到現有用戶
     *
     * @param int   $user_id WordPress 用戶 ID
     * @param array $profile LINE profile 資料 (userId, displayName, pictureUrl)
     * @return bool|\WP_Error 成功返回 true，失敗返回 WP_Error
     */
    public function bind_line_to_user(int $user_id, array $profile) {
        // 檢查必要欄位
        if (empty($profile['userId'])) {
            return new \WP_Error('missing_line_uid', 'LINE userId is required');
        }

        $line_uid = $profile['userId'];

        // 檢查用戶是否存在
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return new \WP_Error('user_not_found', 'User not found');
        }

        // 檢查 LINE UID 是否已綁定到其他用戶
        $existing_user_id = $this->get_user_by_line_uid($line_uid);
        if ($existing_user_id && $existing_user_id !== $user_id) {
            return new \WP_Error('line_uid_already_bound', 'This LINE account is already bound to another user', [
                'bound_user_id' => $existing_user_id,
            ]);
        }

        // 檢查用戶是否已綁定其他 LINE 帳號
        $existing_line_uid = get_user_meta($user_id, 'line_uid', true);
        if (!empty($existing_line_uid) && $existing_line_uid !== $line_uid) {
            return new \WP_Error('user_already_bound', 'This user is already bound to another LINE account', [
                'existing_line_uid' => $existing_line_uid,
            ]);
        }

        // 儲存到 user_meta
        update_user_meta($user_id, 'line_uid', $line_uid);

        // 儲存額外資料
        if (!empty($profile['displayName'])) {
            update_user_meta($user_id, 'line_display_name', sanitize_text_field($profile['displayName']));
        }
        if (!empty($profile['pictureUrl'])) {
            update_user_meta($user_id, 'line_picture_url', sanitize_url($profile['pictureUrl']));
        }

        // 儲存到 bindings 表（使用 LineUserService）
        $bind_result = LineUserService::bind_line_account($user_id, $line_uid, [
            'displayName' => $profile['displayName'] ?? '',
            'pictureUrl' => $profile['pictureUrl'] ?? '',
        ]);

        if (!$bind_result) {
            // Bindings 表寫入失敗，清理 user_meta
            delete_user_meta($user_id, 'line_uid');
            delete_user_meta($user_id, 'line_display_name');
            delete_user_meta($user_id, 'line_picture_url');

            return new \WP_Error('bind_failed', 'Failed to bind LINE account to database');
        }

        return true;
    }

    /**
     * 根據 LINE UID 取得用戶 ID
     *
     * @param string $line_uid LINE UID
     * @return int|false 用戶 ID，未找到返回 false
     */
    public function get_user_by_line_uid(string $line_uid) {
        // 優先查詢 user_meta（快速，有快取）
        $users = get_users([
            'meta_key' => 'line_uid',
            'meta_value' => $line_uid,
            'number' => 1,
            'fields' => 'ID',
        ]);

        if (!empty($users)) {
            return (int) $users[0];
        }

        // Fallback 查詢 bindings 表
        $line_user = LineUserService::get_line_user($line_uid);
        if ($line_user && !empty($line_user->user_id)) {
            return (int) $line_user->user_id;
        }

        return false;
    }

    /**
     * 解除綁定
     *
     * @param int $user_id WordPress 用戶 ID
     * @return bool 是否成功
     */
    public function unbind_line(int $user_id): bool {
        // 檢查用戶是否存在
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }

        // 刪除 user_meta
        delete_user_meta($user_id, 'line_uid');
        delete_user_meta($user_id, 'line_display_name');
        delete_user_meta($user_id, 'line_picture_url');

        // 刪除 bindings 表記錄（軟刪除）
        LineUserService::unbind_line_user($user_id);

        return true;
    }

    /**
     * 生成安全的 username（不暴露 LINE UID）
     *
     * 策略（參考 Nextend Social Login）：
     * 1. 優先使用 displayName（清理後）
     * 2. 使用 email 前綴
     * 3. Fallback：'line_user_' + 隨機 hash
     *
     * @param array $profile LINE profile
     * @param string $email Email address
     * @return string Sanitized username
     */
    private function generate_username(array $profile, string $email): string {
        // 方法 1: 使用 displayName
        if (!empty($profile['displayName'])) {
            $username = $this->sanitize_username($profile['displayName']);
            if ($username) {
                return $username;
            }
        }

        // 方法 2: 使用 email 前綴（如果不是假 email）
        if (!str_ends_with($email, '@line.local') && strpos($email, '@') !== false) {
            $email_parts = explode('@', $email);
            $username = $this->sanitize_username($email_parts[0]);
            if ($username) {
                return $username;
            }
        }

        // 方法 3: Fallback - 使用隨機 hash（絕不暴露 LINE UID）
        return 'line_user_' . substr(md5(uniqid(rand(), true)), 0, 8);
    }

    /**
     * 清理並驗證 username
     *
     * @param string $username 原始 username
     * @return string|false 清理後的 username，失敗返回 false
     */
    private function sanitize_username(string $username) {
        // 清理 username
        $sanitized = sanitize_user($username, true);

        // 檢查是否為空
        if (empty($sanitized)) {
            return false;
        }

        // 檢查長度（WordPress 限制 60 字元）
        if (mb_strlen($sanitized) > 60) {
            $sanitized = mb_substr($sanitized, 0, 60);
        }

        // 驗證是否為有效的 WordPress username
        if (!validate_username($sanitized)) {
            return false;
        }

        return $sanitized;
    }
}
