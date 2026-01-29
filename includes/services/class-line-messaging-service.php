<?php

namespace BuygoLineNotify\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * LineMessagingService
 *
 * 統一處理 LINE 訊息發送（reply 和 push）
 * 從 buygo-plus-one-dev 遷移而來
 */
class LineMessagingService
{
    /**
     * LINE Channel Access Token
     *
     * @var string
     */
    private $channel_access_token;

    /**
     * Logger instance
     *
     * @var Logger|null
     */
    private $logger;

    /**
     * Constructor
     *
     * @param string $channel_access_token Channel Access Token
     */
    public function __construct($channel_access_token)
    {
        $this->channel_access_token = $channel_access_token;
        $this->logger = Logger::get_instance();
    }

    /**
     * 發送回覆訊息（Reply Message）
     *
     * @param string $reply_token Reply token
     * @param string|array $message Message content（文字字串或 LINE 訊息物件陣列）
     * @param string|null $line_uid LINE user ID (optional, for logging)
     * @return bool|\WP_Error 成功返回 true，失敗返回 WP_Error
     */
    public function send_reply($reply_token, $message, $line_uid = null)
    {
        if (empty($this->channel_access_token)) {
            $this->log('error', [
                'message' => 'Channel Access Token is empty',
                'action' => 'send_reply',
            ]);
            return new \WP_Error('missing_token', 'Channel Access Token 未設定');
        }

        $url = 'https://api.line.me/v2/bot/message/reply';

        // Handle Text vs Flex/Array
        $messages_payload = [];
        if (is_array($message)) {
            if (isset($message['type'])) {
                // 單一訊息物件
                $messages_payload = [$message];
            } else {
                // 訊息陣列
                $messages_payload = $message;
            }
        } else {
            // 文字訊息
            $messages_payload = [
                [
                    'type' => 'text',
                    'text' => $message,
                ]
            ];
        }

        $data = [
            'replyToken' => $reply_token,
            'messages' => $messages_payload,
        ];

        $response = wp_remote_post(
            $url,
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->channel_access_token,
                ],
                'body' => wp_json_encode($data),
                'timeout' => 30,
            ]
        );

        if (is_wp_error($response)) {
            $this->log('error', [
                'message' => 'Failed to send LINE reply',
                'error' => $response->get_error_message(),
                'action' => 'send_reply',
                'reply_token' => substr($reply_token, 0, 10) . '...',
            ]);
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($status_code === 200) {
            $message_type = is_array($message) ? (isset($message['type']) ? $message['type'] : 'array') : 'text';
            $this->log('reply_sent', [
                'message' => 'LINE reply sent successfully',
                'message_type' => $message_type,
                'status_code' => $status_code,
            ], null, $line_uid);
            return true;
        } else {
            $this->log('error', [
                'message' => 'LINE API returned error',
                'status_code' => $status_code,
                'response' => $response_body,
                'action' => 'send_reply',
            ], null, $line_uid);
            return new \WP_Error('line_api_error', 'LINE API 錯誤：' . $status_code . ' ' . $response_body);
        }
    }

    /**
     * 發送推播訊息（Push Message）
     *
     * @param string $line_uid LINE User ID
     * @param array $message LINE 訊息物件
     * @return true|\WP_Error 成功返回 true，失敗返回 WP_Error
     */
    public function push_message($line_uid, $message)
    {
        if (empty($this->channel_access_token)) {
            return new \WP_Error('missing_token', 'LINE Channel Access Token 未設定');
        }

        $url = 'https://api.line.me/v2/bot/message/push';
        $body = [
            'to' => $line_uid,
            'messages' => is_array($message) && isset($message['type']) ? [$message] : $message,
        ];

        $response = wp_remote_post(
            $url,
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->channel_access_token,
                ],
                'body' => wp_json_encode($body),
                'timeout' => 20,
            ]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $resp = (string) wp_remote_retrieve_body($response);

        if ($status < 200 || $status >= 300) {
            return new \WP_Error('line_push_failed', 'LINE push failed: ' . $status . ' ' . $resp);
        }

        return true;
    }

    /**
     * Log message
     *
     * @param string $level Log level
     * @param array $data Log data
     * @param int|null $user_id User ID (optional)
     * @param string|null $line_uid LINE UID (optional)
     */
    private function log($level, $data, $user_id = null, $line_uid = null)
    {
        if ($this->logger) {
            $this->logger->log($level, $data, $user_id, $line_uid);
        }
    }
}
