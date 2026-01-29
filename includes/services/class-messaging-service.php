<?php
/**
 * Messaging Service
 *
 * 純粹的 LINE Messaging API 包裝器
 * 不包含任何業務邏輯、訊息模板或內容
 *
 * @package BuygoLineNotify
 */

namespace BuygoLineNotify\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class MessagingService
 *
 * LINE Message 發送服務（基礎設施層）
 */
class MessagingService
{
    /**
     * LINE Messaging API endpoint
     */
    const API_ENDPOINT = 'https://api.line.me/v2/bot/message';

    /**
     * 發送文字訊息
     *
     * @param int    $user_id WordPress User ID
     * @param string $text    訊息文字內容
     * @return array|WP_Error 成功返回 ['success' => true]，失敗返回 WP_Error
     */
    public static function pushText(int $user_id, string $text)
    {
        // 檢查用戶是否已綁定 LINE
        if (!LineUserService::isUserLinked($user_id)) {
            return new \WP_Error(
                'user_not_linked',
                '用戶未綁定 LINE',
                ['user_id' => $user_id]
            );
        }

        // 取得 LINE UID
        $line_data = LineUserService::getUser($user_id);
        if (!$line_data || empty($line_data['line_uid'])) {
            return new \WP_Error(
                'line_uid_not_found',
                '無法取得 LINE UID',
                ['user_id' => $user_id]
            );
        }

        $line_uid = $line_data['line_uid'];

        // 組裝訊息
        $messages = [
            [
                'type' => 'text',
                'text' => $text,
            ],
        ];

        // 發送訊息
        return self::pushMessage($line_uid, $messages);
    }

    /**
     * 發送 Flex Message
     *
     * @param int   $user_id      WordPress User ID
     * @param array $flex_contents Flex Message JSON（已組裝好的內容）
     * @return array|WP_Error 成功返回 ['success' => true]，失敗返回 WP_Error
     */
    public static function pushFlex(int $user_id, array $flex_contents)
    {
        // 檢查用戶是否已綁定 LINE
        if (!LineUserService::isUserLinked($user_id)) {
            return new \WP_Error(
                'user_not_linked',
                '用戶未綁定 LINE',
                ['user_id' => $user_id]
            );
        }

        // 取得 LINE UID
        $line_data = LineUserService::getUser($user_id);
        if (!$line_data || empty($line_data['line_uid'])) {
            return new \WP_Error(
                'line_uid_not_found',
                '無法取得 LINE UID',
                ['user_id' => $user_id]
            );
        }

        $line_uid = $line_data['line_uid'];

        // 組裝訊息
        $messages = [
            [
                'type'     => 'flex',
                'altText'  => $flex_contents['altText'] ?? 'Flex Message',
                'contents' => $flex_contents,
            ],
        ];

        // 發送訊息
        return self::pushMessage($line_uid, $messages);
    }

    /**
     * 發送圖片訊息
     *
     * @param int    $user_id        WordPress User ID
     * @param string $original_url   原始圖片 URL
     * @param string $preview_url    預覽圖片 URL（可選，預設使用原始圖片）
     * @return array|WP_Error 成功返回 ['success' => true]，失敗返回 WP_Error
     */
    public static function pushImage(int $user_id, string $original_url, string $preview_url = '')
    {
        // 檢查用戶是否已綁定 LINE
        if (!LineUserService::isUserLinked($user_id)) {
            return new \WP_Error(
                'user_not_linked',
                '用戶未綁定 LINE',
                ['user_id' => $user_id]
            );
        }

        // 取得 LINE UID
        $line_data = LineUserService::getUser($user_id);
        if (!$line_data || empty($line_data['line_uid'])) {
            return new \WP_Error(
                'line_uid_not_found',
                '無法取得 LINE UID',
                ['user_id' => $user_id]
            );
        }

        $line_uid = $line_data['line_uid'];

        // 如果沒有提供預覽圖，使用原始圖
        if (empty($preview_url)) {
            $preview_url = $original_url;
        }

        // 組裝訊息
        $messages = [
            [
                'type'               => 'image',
                'originalContentUrl' => $original_url,
                'previewImageUrl'    => $preview_url,
            ],
        ];

        // 發送訊息
        return self::pushMessage($line_uid, $messages);
    }

    /**
     * 發送 Push Message 到 LINE
     *
     * @param string $line_uid LINE User ID
     * @param array  $messages 訊息陣列
     * @return array|WP_Error 成功返回 ['success' => true]，失敗返回 WP_Error
     */
    private static function pushMessage(string $line_uid, array $messages)
    {
        // 取得 Channel Access Token
        $access_token = SettingsService::get('channel_access_token');
        if (empty($access_token)) {
            return new \WP_Error(
                'missing_access_token',
                'Channel Access Token 未設定',
                ['line_uid' => $line_uid]
            );
        }

        // 組裝 API 請求
        $url = self::API_ENDPOINT . '/push';
        $headers = [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $access_token,
        ];

        $body = [
            'to'       => $line_uid,
            'messages' => $messages,
        ];

        // 發送請求（with retry）
        $response = self::sendRequest($url, $headers, $body);

        // 檢查回應
        if (is_wp_error($response)) {
            return $response;
        }

        return ['success' => true];
    }

    /**
     * 發送 Reply Message 到 LINE
     *
     * @param string $reply_token Reply Token
     * @param array  $messages    訊息陣列
     * @return array|WP_Error 成功返回 ['success' => true]，失敗返回 WP_Error
     */
    public static function replyMessage(string $reply_token, array $messages)
    {
        // 取得 Channel Access Token
        $access_token = SettingsService::get('channel_access_token');
        if (empty($access_token)) {
            return new \WP_Error(
                'missing_access_token',
                'Channel Access Token 未設定'
            );
        }

        // 組裝 API 請求
        $url = self::API_ENDPOINT . '/reply';
        $headers = [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $access_token,
        ];

        $body = [
            'replyToken' => $reply_token,
            'messages'   => $messages,
        ];

        // 發送請求（with retry）
        $response = self::sendRequest($url, $headers, $body);

        // 檢查回應
        if (is_wp_error($response)) {
            return $response;
        }

        return ['success' => true];
    }

    /**
     * 發送 HTTP 請求到 LINE API（with retry）
     *
     * @param string $url     API URL
     * @param array  $headers HTTP Headers
     * @param array  $body    Request Body
     * @param int    $retry   重試次數（預設 3 次）
     * @return array|WP_Error
     */
    private static function sendRequest(string $url, array $headers, array $body, int $retry = 3)
    {
        $attempts = 0;
        $last_error = null;

        while ($attempts < $retry) {
            $attempts++;

            // 發送 HTTP 請求
            $response = \wp_remote_post($url, [
                'headers' => $headers,
                'body'    => \wp_json_encode($body),
                'timeout' => 30,
            ]);

            // 檢查 HTTP 錯誤
            if (\is_wp_error($response)) {
                $last_error = $response;

                // 如果還有重試次數，等待後重試
                if ($attempts < $retry) {
                    \sleep(2 * $attempts); // 指數退避：2s, 4s, 6s
                    continue;
                }

                return $last_error;
            }

            // 取得回應碼
            $status_code = \wp_remote_retrieve_response_code($response);
            $response_body = \wp_remote_retrieve_body($response);
            $response_data = \json_decode($response_body, true);

            // 成功（2xx）
            if ($status_code >= 200 && $status_code < 300) {
                return ['success' => true, 'response' => $response_data];
            }

            // 速率限制（429）或伺服器錯誤（5xx）- 重試
            if ($status_code === 429 || ($status_code >= 500 && $status_code < 600)) {
                $last_error = new \WP_Error(
                    'line_api_error',
                    'LINE API 錯誤（可重試）',
                    [
                        'status_code' => $status_code,
                        'response'    => $response_data,
                        'attempt'     => $attempts,
                    ]
                );

                // 如果還有重試次數，等待後重試
                if ($attempts < $retry) {
                    \sleep(2 * $attempts); // 指數退避：2s, 4s, 6s
                    continue;
                }

                return $last_error;
            }

            // 其他錯誤（4xx）- 不重試
            return new \WP_Error(
                'line_api_error',
                'LINE API 錯誤',
                [
                    'status_code' => $status_code,
                    'response'    => $response_data,
                ]
            );
        }

        // 重試次數用完
        return $last_error;
    }

    /**
     * 檢查用戶是否已綁定 LINE（Facade 方法）
     *
     * @param int $user_id WordPress User ID
     * @return bool
     */
    public static function isUserLinked(int $user_id): bool
    {
        return LineUserService::isUserLinked($user_id);
    }
}
