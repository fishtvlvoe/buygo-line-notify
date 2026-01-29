<?php

namespace BuygoLineNotify\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * SettingsService
 *
 * 管理 LINE 相關設定（Channel Access Token、Channel Secret 等）
 * 支援加密儲存敏感資料和向後相容讀取（buygo_core_settings）
 */
class SettingsService
{
    /**
     * 需要加密的欄位列表
     */
    private static array $encrypted_fields = [
        'channel_access_token',
        'channel_secret',
        'login_channel_id',
        'login_channel_secret',
    ];

    /**
     * 取得加密金鑰
     * 優先使用 wp-config.php 定義的 BUYGO_ENCRYPTION_KEY
     */
    private static function get_encryption_key(): string
    {
        return defined('BUYGO_ENCRYPTION_KEY')
            ? BUYGO_ENCRYPTION_KEY
            : 'buygo-secret-key-default';
    }

    /**
     * 加密演算法（與舊外掛相同，確保向後相容）
     */
    private static function cipher(): string
    {
        return 'AES-128-ECB';
    }

    /**
     * 加密資料
     *
     * @param string $data 要加密的資料
     * @return string 加密後的資料
     */
    public static function encrypt(string $data): string
    {
        if (empty($data)) {
            return $data;
        }

        $encrypted = openssl_encrypt($data, self::cipher(), self::get_encryption_key());
        return $encrypted !== false ? $encrypted : $data;
    }

    /**
     * 解密資料
     *
     * @param string $data 要解密的資料
     * @return string 解密後的資料，失敗時返回原值
     */
    public static function decrypt(string $data): string
    {
        if (empty($data)) {
            return $data;
        }

        $decrypted = openssl_decrypt($data, self::cipher(), self::get_encryption_key());

        // 解密失敗時返回原值（避免 Pitfall 2）
        return $decrypted !== false ? $decrypted : $data;
    }

    /**
     * 檢查欄位是否需要加密
     *
     * @param string $key 欄位名稱
     * @return bool
     */
    private static function is_encrypted_field(string $key): bool
    {
        return in_array($key, self::$encrypted_fields, true);
    }

    /**
     * 讀取設定（含向後相容）
     * 優先順序：buygo_line_{key} > buygo_core_settings[key] > default
     *
     * @param string $key 設定 key（例如 'channel_access_token'）
     * @param mixed $default 預設值
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        // 優先從新外掛 option 讀取
        $option_key = "buygo_line_{$key}";
        $value = get_option($option_key, '');

        if (!empty($value)) {
            // 如果是加密欄位，解密
            if (self::is_encrypted_field($key)) {
                return self::decrypt($value);
            }
            return $value;
        }

        // 向後相容：從舊外掛讀取
        $core_settings = get_option('buygo_core_settings', []);
        if (isset($core_settings[$key]) && !empty($core_settings[$key])) {
            $value = $core_settings[$key];

            // 舊資料也可能加密
            if (self::is_encrypted_field($key)) {
                return self::decrypt($value);
            }
            return $value;
        }

        return $default;
    }

    /**
     * 儲存設定（自動加密敏感欄位）
     *
     * @param string $key 設定 key
     * @param mixed $value 設定值
     * @return bool
     */
    public static function set(string $key, $value): bool
    {
        if (self::is_encrypted_field($key) && !empty($value)) {
            $value = self::encrypt($value);
        }

        $option_key = "buygo_line_{$key}";
        return update_option($option_key, $value);
    }

    /**
     * 刪除設定
     *
     * @param string $key 設定 key
     * @return bool
     */
    public static function delete(string $key): bool
    {
        $option_key = "buygo_line_{$key}";
        return delete_option($option_key);
    }

    /**
     * 取得 LINE Login Channel ID
     *
     * @return string
     */
    public static function get_line_login_channel_id(): string
    {
        return self::get('login_channel_id', '');
    }

    /**
     * 取得 LINE Login Channel Secret
     *
     * @return string
     */
    public static function get_line_login_channel_secret(): string
    {
        return self::get('login_channel_secret', '');
    }

    /**
     * 取得 LINE 登入按鈕位置設定
     *
     * @return string 'before' 或 'after'，預設 'before'
     */
    public static function get_login_button_position(): string
    {
        return self::get('login_button_position', 'before');
    }

    /**
     * 取得 LINE 登入按鈕文字
     *
     * @return string 預設「使用 LINE 登入」
     */
    public static function get_login_button_text(): string
    {
        return self::get('login_button_text', '使用 LINE 登入');
    }

    /**
     * 取得所有設定（用於設定頁面顯示）
     *
     * @return array
     */
    public static function get_all(): array
    {
        $keys = [
            'channel_access_token',
            'channel_secret',
            'login_channel_id',
            'login_channel_secret',
            'liff_id',
            'liff_endpoint_url',
            'login_button_position',
            'login_button_text',
            'sync_on_login',       // 登入時是否同步 profile
            'conflict_strategy',   // 衝突處理策略
        ];

        $settings = [];
        foreach ($keys as $key) {
            $settings[$key] = self::get($key, '');
        }

        return $settings;
    }

    /**
     * 取得是否在登入時同步 Profile
     *
     * @return bool
     */
    public static function get_sync_on_login(): bool
    {
        return (bool) self::get('sync_on_login', false);
    }

    /**
     * 取得衝突處理策略
     *
     * @return string 'line_priority' | 'wordpress_priority' | 'manual'
     */
    public static function get_conflict_strategy(): string
    {
        $strategy = self::get('conflict_strategy', 'line_priority');
        $valid = ['line_priority', 'wordpress_priority', 'manual'];
        return in_array($strategy, $valid, true) ? $strategy : 'line_priority';
    }
}
