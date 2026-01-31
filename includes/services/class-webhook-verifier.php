<?php

namespace BuygoLineNotify\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * WebhookVerifier
 *
 * 負責驗證 LINE Webhook 請求的 HMAC-SHA256 簽名
 * 確保只處理來自 LINE Platform 的合法請求
 */
class WebhookVerifier
{
    /**
     * 驗證 Webhook 簽名
     *
     * @param \WP_REST_Request $request WordPress REST API 請求物件
     * @return bool 簽名是否有效
     */
    public function verify_signature(\WP_REST_Request $request): bool
    {
        // 嘗試多種方式獲取簽名 header（LINE 可能使用不同大小寫）
        $signature = $this->get_signature_header($request);

        // 如果沒有簽名且是開發環境，允許測試
        if (empty($signature) && $this->is_development_mode()) {
            error_log('BUYGO_LINE_NOTIFY: Development mode - Signature not required');
            return true;
        }

        // 正式環境必須有簽名
        if (empty($signature)) {
            error_log('BUYGO_LINE_NOTIFY: Signature verification failed - Missing x-line-signature header');
            return false;
        }

        // 取得 Channel Secret
        $channel_secret = SettingsService::get('channel_secret', '');

        // 如果沒有設定 Channel Secret
        if (empty($channel_secret)) {
            if ($this->is_development_mode()) {
                error_log('BUYGO_LINE_NOTIFY: Development mode - Channel Secret not configured, allowing request');
                return true;
            } else {
                error_log('BUYGO_LINE_NOTIFY: Production mode - Channel Secret not configured, rejecting request');
                return false;
            }
        }

        // 計算 HMAC-SHA256 簽名
        $body = $request->get_body();
        $hash = hash_hmac('sha256', $body, $channel_secret, true);
        $computed_signature = base64_encode($hash);

        // 使用 hash_equals 進行安全比較（防止時序攻擊）
        $is_valid = hash_equals($signature, $computed_signature);

        if (!$is_valid) {
            error_log('BUYGO_LINE_NOTIFY: Signature verification failed - Signature mismatch');
        }

        return $is_valid;
    }

    /**
     * 取得簽名 header（嘗試多種大小寫）
     *
     * @param \WP_REST_Request $request WordPress REST API 請求物件
     * @return string|null 簽名值或 null
     */
    private function get_signature_header(\WP_REST_Request $request): ?string
    {
        // 嘗試多種 header 名稱
        $signature_alternatives = [
            'x-line-signature' => $request->get_header('x-line-signature'),
            'X-LINE-Signature' => $request->get_header('X-LINE-Signature'),
            'X-Line-Signature' => $request->get_header('X-Line-Signature'),
            'HTTP_X_LINE_SIGNATURE' => isset($_SERVER['HTTP_X_LINE_SIGNATURE'])
                ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_LINE_SIGNATURE']))
                : null,
        ];

        // 返回第一個非空的簽名
        foreach ($signature_alternatives as $value) {
            if (!empty($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * 檢查是否為開發模式
     *
     * @return bool
     */
    public function is_development_mode(): bool
    {
        // 方法1: 檢查 WP_DEBUG（最常用）
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            return true;
        }

        // 方法2: 檢查環境類型（WordPress 5.5+）
        if (function_exists('wp_get_environment_type')) {
            $env_type = wp_get_environment_type();
            if (in_array($env_type, ['development', 'local'], true)) {
                return true;
            }
        }

        // 方法3: 檢查伺服器名稱（補充判斷）
        if (isset($_SERVER['SERVER_NAME'])) {
            $server_name = sanitize_text_field(wp_unslash($_SERVER['SERVER_NAME']));
            if (in_array($server_name, ['localhost', '127.0.0.1', '::1'], true)) {
                return true;
            }
        }

        // 預設為正式環境（安全優先）
        return false;
    }
}
