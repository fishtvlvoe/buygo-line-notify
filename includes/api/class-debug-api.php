<?php
/**
 * Debug API
 *
 * 提供 Debug 工具的 REST API 端點
 *
 * @package BuygoLineNotify
 */

namespace BuygoLineNotify\Api;

use BuygoLineNotify\Services\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DebugAPI
 *
 * Debug 工具 REST API
 */
class DebugAPI
{
    /**
     * 命名空間
     */
    const NAMESPACE = 'buygo-line-notify/v1';

    /**
     * 註冊路由
     */
    public function register_routes(): void
    {
        // 取得 Webhook 事件記錄
        register_rest_route(self::NAMESPACE, '/debug/webhook-logs', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_webhook_logs'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // 取得訊息發送記錄
        register_rest_route(self::NAMESPACE, '/debug/message-logs', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_message_logs'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // 取得統計資料
        register_rest_route(self::NAMESPACE, '/debug/statistics', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_statistics'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        // 清除舊記錄
        register_rest_route(self::NAMESPACE, '/debug/clean-logs', [
            'methods'             => 'POST',
            'callback'            => [$this, 'clean_old_logs'],
            'permission_callback' => [$this, 'check_admin_permission'],
            'args'                => [
                'days' => [
                    'required'          => false,
                    'default'           => 30,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /**
     * 取得 Webhook 事件記錄
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_webhook_logs(\WP_REST_Request $request): \WP_REST_Response
    {
        $page = $request->get_param('page') ?? 1;
        $per_page = $request->get_param('per_page') ?? 50;

        $logs = Logger::getWebhookLogs((int) $page, (int) $per_page);

        return new \WP_REST_Response($logs, 200);
    }

    /**
     * 取得訊息發送記錄
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_message_logs(\WP_REST_Request $request): \WP_REST_Response
    {
        $page = $request->get_param('page') ?? 1;
        $per_page = $request->get_param('per_page') ?? 50;

        $logs = Logger::getMessageLogs((int) $page, (int) $per_page);

        return new \WP_REST_Response($logs, 200);
    }

    /**
     * 取得統計資料
     *
     * @return \WP_REST_Response
     */
    public function get_statistics(): \WP_REST_Response
    {
        $stats = Logger::getStatistics();

        return new \WP_REST_Response($stats, 200);
    }

    /**
     * 清除超過指定天數的舊記錄
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function clean_old_logs(\WP_REST_Request $request): \WP_REST_Response
    {
        $days = $request->get_param('days');

        $result = Logger::cleanOldLogs((int) $days);

        return new \WP_REST_Response([
            'success' => true,
            'data'    => $result,
        ], 200);
    }

    /**
     * 檢查管理員權限
     *
     * @return bool
     */
    public function check_admin_permission(): bool
    {
        return current_user_can('manage_options');
    }
}
