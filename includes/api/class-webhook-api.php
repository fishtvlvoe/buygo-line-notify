<?php

namespace BuygoLineNotify\Api;

use BuygoLineNotify\Services\WebhookVerifier;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Webhook_API
 *
 * 註冊 REST API endpoint 接收 LINE Webhook 事件
 * 負責簽名驗證、Verify Event 處理、以及觸發 Hooks 讓其他外掛處理事件
 */
class Webhook_API
{
    /**
     * Webhook 簽名驗證器
     *
     * @var WebhookVerifier
     */
    private $verifier;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->verifier = new WebhookVerifier();
    }

    /**
     * 註冊 REST API routes
     */
    public function register_routes()
    {
        register_rest_route(
            'buygo-line-notify/v1',
            '/webhook',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'handle_webhook'],
                'permission_callback' => '__return_true', // 公開 endpoint，簽名驗證在 callback 中處理
            ]
        );
    }

    /**
     * 處理 Webhook 請求
     *
     * @param \WP_REST_Request $request WordPress REST API 請求物件
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_webhook(\WP_REST_Request $request)
    {
        // 第一步：驗證簽名（必須在處理 body 之前，使用原始 body）
        if (!$this->verifier->verify_signature($request)) {
            error_log('BUYGO_LINE_NOTIFY: Webhook rejected - Invalid signature');
            return new \WP_Error(
                'invalid_signature',
                'Invalid signature',
                ['status' => 401]
            );
        }

        // 第二步：解析 JSON body
        $body = $request->get_body();
        $data = json_decode($body, true);

        if (!is_array($data) || !isset($data['events'])) {
            error_log('BUYGO_LINE_NOTIFY: Webhook rejected - No events array in data');
            return rest_ensure_response(['success' => false, 'error' => 'No events']);
        }

        // 第三步：檢查是否為 Verify Event
        if ($this->is_verify_event($data)) {
            error_log('BUYGO_LINE_NOTIFY: Verify Event detected (replyToken: 000...000), returning success');
            return rest_ensure_response(['success' => true, 'message' => 'Verify event received']);
        }

        // 第四步：背景處理事件
        if (function_exists('fastcgi_finish_request')) {
            // FastCGI 環境：先返回 200，然後背景處理
            $handler = new \BuygoLineNotify\Services\WebhookHandler();
            add_action('shutdown', function () use ($data, $handler) {
                fastcgi_finish_request(); // 釋放連線
                $handler->process_events($data['events']);
            });
        } else {
            // 非 FastCGI 環境：使用 WP_Cron
            wp_schedule_single_event(time(), 'buygo_process_line_webhook', [$data['events']]);
        }

        // 第五步：立即返回 200（LINE 要求 5 秒內回應）
        return rest_ensure_response(['success' => true]);
    }

    /**
     * 檢查是否為 LINE Verify Event
     * Verify Event 的 replyToken 固定為 32 個 0
     *
     * @param array $data Webhook 資料
     * @return bool
     */
    private function is_verify_event(array $data): bool
    {
        if (!isset($data['events']) || !is_array($data['events'])) {
            return false;
        }

        foreach ($data['events'] as $event) {
            if (!isset($event['replyToken'])) {
                continue;
            }

            // LINE Verify Event 的 replyToken 是固定的 32 個 0
            if ($event['replyToken'] === '00000000000000000000000000000000') {
                return true;
            }
        }

        return false;
    }
}
