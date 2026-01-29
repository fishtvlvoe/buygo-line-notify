<?php

namespace BuygoLineNotify\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Logger Service
 *
 * 簡單的日誌服務，用於記錄 LINE 相關操作
 */
class Logger
{
    /**
     * @var self|null
     */
    private static ?self $_instance = null;

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function get_instance(): self
    {
        if (self::$_instance === null) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Log message
     *
     * @param string $level Log level (info, warning, error)
     * @param array $data Log data
     * @param int|null $user_id User ID (optional)
     * @param string|null $line_uid LINE UID (optional)
     * @return void
     */
    public function log($level, $data, $user_id = null, $line_uid = null)
    {
        // 簡單實作：使用 error_log
        // 如果 buygo-plus-one-dev 有 WebhookLogger，可以透過 hook 整合
        $message = sprintf(
            '[BuygoLineNotify] [%s] %s',
            strtoupper($level),
            wp_json_encode($data)
        );

        if ($user_id) {
            $message .= sprintf(' [user_id: %d]', $user_id);
        }

        if ($line_uid) {
            $message .= sprintf(' [line_uid: %s]', substr($line_uid, 0, 10) . '...');
        }

        error_log($message);

        // 如果 buygo-plus-one-dev 的 WebhookLogger 存在，也記錄到那邊
        if (class_exists('\BuyGoPlus\Services\WebhookLogger')) {
            $webhook_logger = \BuyGoPlus\Services\WebhookLogger::get_instance();
            if (method_exists($webhook_logger, 'log')) {
                $webhook_logger->log($level, $data, $user_id, $line_uid);
            }
        }
    }
}
