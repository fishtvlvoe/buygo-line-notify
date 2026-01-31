<?php

namespace BuygoLineNotify\Cron;

use BuygoLineNotify\Admin\DemoPage;

final class RetryDispatcher
{
    public const CRON_HOOK_ATTEMPT = 'buygo_line_notify_attempt_event';

    public const EVENT_ORDER_PAID = 'order_paid';

    public const EVENT_SHIPPED = 'shipped';

    public const OPTION_LOGS = 'buygo_line_notify_logs';

    public const OPTION_SENT_INDEX = 'buygo_line_notify_sent_index';

    public static function register_hooks(): void
    {
        \add_action(self::CRON_HOOK_ATTEMPT, [self::class, 'handleAttempt'], 10, 1);
    }

    public static function enqueue_with_retry_schedule(string $event, string $orderId, int $wpUserId = 0): void
    {
        $scheduleSeconds = [60, 120, 300];

        foreach ($scheduleSeconds as $attemptIndex => $delay) {
            $attemptNo = $attemptIndex + 1;

            $payload = [
                'event' => $event,
                'order_id' => $orderId,
                'wp_user_id' => $wpUserId,
                'attempt' => $attemptNo,
            ];

            $ts = time() + $delay;

            \wp_schedule_single_event($ts, self::CRON_HOOK_ATTEMPT, [$payload]);
        }

        self::log('enqueue', [
            'event' => $event,
            'order_id' => $orderId,
            'wp_user_id' => $wpUserId,
            'schedule' => $scheduleSeconds,
        ]);
    }

    public static function handleAttempt(array $payload): void
    {
        $event = \sanitize_text_field($payload['event'] ?? '');

        $orderId = \sanitize_text_field($payload['order_id'] ?? '');

        $wpUserId = \absint($payload['wp_user_id'] ?? 0);

        $attempt = \absint($payload['attempt'] ?? 0);

        if ($event === '' || $orderId === '' || $attempt < 1) {
            self::log('attempt_invalid_payload', $payload);

            return;
        }

        $sentKey = self::sentKey($event, $orderId);

        if (self::isSent($sentKey)) {
            self::log('attempt_skip_already_sent', [
                'sent_key' => $sentKey,
                'attempt' => $attempt,
            ]);

            return;
        }

        $uid = '';

        if ($wpUserId > 0) {
            $uid = (string) \get_user_meta($wpUserId, DemoPage::USER_META_UID, true);
        }

        if ($uid === '') {
            $uid = (string) \get_option(DemoPage::OPTION_TEST_UID, '');
        }

        if ($uid === '') {
            self::log('attempt_no_uid', [
                'event' => $event,
                'order_id' => $orderId,
                'wp_user_id' => $wpUserId,
                'attempt' => $attempt,
            ]);

            return;
        }

        $token = (string) \get_option(DemoPage::OPTION_CHANNEL_ACCESS_TOKEN, '');

        $enableRealPush = (string) \get_option(DemoPage::OPTION_ENABLE_REAL_PUSH, 'no') === 'yes';

        $messageText = self::buildDemoMessage($event, $orderId, $attempt);

        if (!$enableRealPush) {
            self::markSent($sentKey);

            self::log('attempt_dry_run_sent', [
                'event' => $event,
                'order_id' => $orderId,
                'attempt' => $attempt,
                'uid' => $uid,
                'message' => $messageText,
            ]);

            return;
        }

        if ($token === '') {
            self::log('attempt_missing_token', [
                'event' => $event,
                'order_id' => $orderId,
                'attempt' => $attempt,
                'uid' => $uid,
            ]);

            return;
        }

        $res = self::pushMessage($token, $uid, $messageText);

        if (\is_wp_error($res)) {
            self::log('attempt_push_failed', [
                'event' => $event,
                'order_id' => $orderId,
                'attempt' => $attempt,
                'uid' => $uid,
                'error' => $res->get_error_message(),
            ]);

            return;
        }

        $status = (int) \wp_remote_retrieve_response_code($res);

        $body = (string) \wp_remote_retrieve_body($res);

        if ($status >= 200 && $status < 300) {
            self::markSent($sentKey);

            self::log('attempt_push_sent', [
                'event' => $event,
                'order_id' => $orderId,
                'attempt' => $attempt,
                'uid' => $uid,
                'status' => $status,
            ]);

            return;
        }

        self::log('attempt_push_non_2xx', [
            'event' => $event,
            'order_id' => $orderId,
            'attempt' => $attempt,
            'uid' => $uid,
            'status' => $status,
            'body' => $body,
        ]);
    }

    private static function buildDemoMessage(string $event, string $orderId, int $attempt): string
    {
        if ($event === self::EVENT_SHIPPED) {
            return "（Demo）你的訂單已出貨\n\n訂單：{$orderId}\n\n嘗試：{$attempt}/3";
        }

        return "（Demo）感謝你下單成功\n\n訂單：{$orderId}\n\n嘗試：{$attempt}/3";
    }

    private static function pushMessage(string $token, string $uid, string $text)
    {
        $endpoint = 'https://api.line.me/v2/bot/message/push';

        $payload = [
            'to' => $uid,
            'messages' => [
                [
                    'type' => 'text',
                    'text' => $text,
                ],
            ],
        ];

        return \wp_remote_post($endpoint, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => \wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public static function get_logs(): array
    {
        $logs = \get_option(self::OPTION_LOGS, []);

        return is_array($logs) ? $logs : [];
    }

    public static function clear_logs(): void
    {
        \update_option(self::OPTION_LOGS, [], false);

        \update_option(self::OPTION_SENT_INDEX, [], false);
    }

    public static function log(string $event, array $data = []): void
    {
        $logs = self::get_logs();

        array_unshift($logs, [
            'ts' => \current_time('mysql'),
            'event' => $event,
            'data' => $data,
        ]);

        $logs = array_slice($logs, 0, 100);

        \update_option(self::OPTION_LOGS, $logs, false);
    }

    private static function sentKey(string $event, string $orderId): string
    {
        return $event . ':' . $orderId;
    }

    private static function isSent(string $sentKey): bool
    {
        $index = \get_option(self::OPTION_SENT_INDEX, []);

        if (!is_array($index)) {
            return false;
        }

        return !empty($index[$sentKey]);
    }

    private static function markSent(string $sentKey): void
    {
        $index = \get_option(self::OPTION_SENT_INDEX, []);

        if (!is_array($index)) {
            $index = [];
        }

        $index[$sentKey] = [
            'sent_at' => \current_time('mysql'),
        ];

        \update_option(self::OPTION_SENT_INDEX, $index, false);
    }
}

