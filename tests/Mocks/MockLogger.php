<?php
/**
 * Mock Logger class for unit testing
 *
 * 必須在 autoloader 載入真正的 Logger 之前 require
 */

namespace BuygoLineNotify\Services;

class Logger {
    private static $instance = null;
    private static $logs = [];

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function log($level, $data) {
        self::$logs[] = [
            'level' => $level,
            'data' => $data,
            'timestamp' => time(),
        ];
        return true;
    }

    public static function getLogs() {
        return self::$logs;
    }

    public static function clearLogs() {
        self::$logs = [];
        self::$instance = null;
    }

    public static function logWebhookEvent($event_type, $line_uid = null, $user_id = null, $webhook_event_id = null) {
        return 1;
    }

    public static function logMessageSent($user_id, $line_uid, $message_type, $status = 'success', $error_message = '') {
        return 1;
    }
}
