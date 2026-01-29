<?php
/**
 * Logger Service
 *
 * 記錄 Webhook 事件和訊息發送狀態(不包含業務邏輯內容)
 *
 * @package BuygoLineNotify
 */

namespace BuygoLineNotify\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Logger
 *
 * Debug 日誌服務(基礎設施層)
 */
class Logger
{
    /**
     * 記錄 Webhook 事件
     *
     * @param string      $event_type      事件類型(如:message, follow, postback)
     * @param string|null $line_uid        LINE User ID
     * @param int|null    $user_id         WordPress User ID
     * @param string|null $webhook_event_id Webhook Event ID(用於去重)
     * @return int|false Insert ID 或 false
     */
    public static function logWebhookEvent(
        string $event_type,
        ?string $line_uid = null,
        ?int $user_id = null,
        ?string $webhook_event_id = null
    ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'buygo_webhook_logs';

        $result = $wpdb->insert(
            $table_name,
            [
                'event_type'       => $event_type,
                'line_uid'         => $line_uid,
                'user_id'          => $user_id,
                'webhook_event_id' => $webhook_event_id,
                'received_at'      => current_time('mysql'),
            ],
            ['%s', '%s', '%d', '%s', '%s']
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * 記錄訊息發送狀態
     *
     * @param int    $user_id       WordPress User ID
     * @param string $line_uid      LINE User ID
     * @param string $message_type  訊息類型(text, flex, image)
     * @param string $status        發送狀態(success, failed, pending)
     * @param string $error_message 錯誤訊息(選填)
     * @return int|false Insert ID 或 false
     */
    public static function logMessageSent(
        int $user_id,
        string $line_uid,
        string $message_type,
        string $status = 'success',
        string $error_message = ''
    ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'buygo_message_logs';

        $result = $wpdb->insert(
            $table_name,
            [
                'user_id'       => $user_id,
                'line_uid'      => $line_uid,
                'message_type'  => $message_type,
                'status'        => $status,
                'error_message' => $error_message,
                'sent_at'       => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s']
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * 取得 Webhook 事件記錄(分頁)
     *
     * @param int $page     頁數(從 1 開始)
     * @param int $per_page 每頁筆數
     * @return array
     */
    public static function getWebhookLogs(int $page = 1, int $per_page = 50): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'buygo_webhook_logs';
        $offset = ($page - 1) * $per_page;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name}
             ORDER BY received_at DESC
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ), ARRAY_A);

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

        return [
            'logs'       => $results,
            'total'      => (int) $total,
            'page'       => $page,
            'per_page'   => $per_page,
            'total_pages' => ceil($total / $per_page),
        ];
    }

    /**
     * 取得訊息發送記錄(分頁)
     *
     * @param int $page     頁數(從 1 開始)
     * @param int $per_page 每頁筆數
     * @return array
     */
    public static function getMessageLogs(int $page = 1, int $per_page = 50): array
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'buygo_message_logs';
        $offset = ($page - 1) * $per_page;

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table_name}
             ORDER BY sent_at DESC
             LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ), ARRAY_A);

        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

        return [
            'logs'       => $results,
            'total'      => (int) $total,
            'page'       => $page,
            'per_page'   => $per_page,
            'total_pages' => ceil($total / $per_page),
        ];
    }

    /**
     * 清除超過指定天數的舊記錄
     *
     * @param int $days 保留天數(預設 30 天)
     * @return array 刪除結果 ['webhook_deleted' => int, 'message_deleted' => int]
     */
    public static function cleanOldLogs(int $days = 30): array
    {
        global $wpdb;

        $webhook_table = $wpdb->prefix . 'buygo_webhook_logs';
        $message_table = $wpdb->prefix . 'buygo_message_logs';

        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $webhook_deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$webhook_table} WHERE received_at < %s",
            $cutoff_date
        ));

        $message_deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$message_table} WHERE sent_at < %s",
            $cutoff_date
        ));

        return [
            'webhook_deleted' => $webhook_deleted,
            'message_deleted' => $message_deleted,
        ];
    }

    /**
     * 取得統計資料
     *
     * @return array
     */
    public static function getStatistics(): array
    {
        global $wpdb;

        $webhook_table = $wpdb->prefix . 'buygo_webhook_logs';
        $message_table = $wpdb->prefix . 'buygo_message_logs';

        // Webhook 統計
        $webhook_total = $wpdb->get_var("SELECT COUNT(*) FROM {$webhook_table}");
        $webhook_today = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$webhook_table} WHERE DATE(received_at) = %s",
            current_time('Y-m-d')
        ));

        // 訊息統計
        $message_total = $wpdb->get_var("SELECT COUNT(*) FROM {$message_table}");
        $message_today = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$message_table} WHERE DATE(sent_at) = %s",
            current_time('Y-m-d')
        ));

        $message_success = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$message_table} WHERE status = 'success'"
        );

        $message_failed = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$message_table} WHERE status = 'failed'"
        );

        return [
            'webhook' => [
                'total' => (int) $webhook_total,
                'today' => (int) $webhook_today,
            ],
            'message' => [
                'total'   => (int) $message_total,
                'today'   => (int) $message_today,
                'success' => (int) $message_success,
                'failed'  => (int) $message_failed,
            ],
        ];
    }
}
