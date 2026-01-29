<?php

namespace BuygoLineNotify\Admin;

use BuygoLineNotify\Cron\RetryDispatcher;

final class DemoPage
{
    public const MENU_SLUG = 'buygo-line-notify-demo';

    public const OPTION_CHANNEL_ACCESS_TOKEN = 'buygo_line_notify_channel_access_token';

    public const OPTION_ENABLE_REAL_PUSH = 'buygo_line_notify_enable_real_push';

    public const OPTION_TEST_UID = 'buygo_line_notify_test_uid';

    public const USER_META_UID = 'buygo_line_notify_uid';

    public static function register_hooks(): void
    {
        \add_action('admin_menu', [self::class, 'registerMenu']);

        \add_action('admin_init', [self::class, 'handlePostActions']);
    }

    public static function registerMenu(): void
    {
        \add_menu_page(
            \__('BuyGo LINE 通知（Demo）', 'buygo-line-notify'),
            \__('BuyGo LINE 通知', 'buygo-line-notify'),
            'manage_options',
            self::MENU_SLUG,
            [self::class, 'renderPage'],
            'dashicons-format-chat',
            58
        );
    }

    public static function handlePostActions(): void
    {
        if (!\is_admin()) {
            return;
        }

        if (!\current_user_can('manage_options')) {
            return;
        }

        if (empty($_POST['buygo_line_notify_action'])) {
            return;
        }

        \check_admin_referer('buygo_line_notify_demo_action', 'buygo_line_notify_nonce');

        $action = \sanitize_text_field(\wp_unslash($_POST['buygo_line_notify_action']));

        if ($action === 'save_settings') {
            $token = \sanitize_text_field(\wp_unslash($_POST['channel_access_token'] ?? ''));

            $enableRealPush = !empty($_POST['enable_real_push']) ? 'yes' : 'no';

            $testUid = \sanitize_text_field(\wp_unslash($_POST['test_uid'] ?? ''));

            \update_option(self::OPTION_CHANNEL_ACCESS_TOKEN, $token, false);

            \update_option(self::OPTION_ENABLE_REAL_PUSH, $enableRealPush, false);

            \update_option(self::OPTION_TEST_UID, $testUid, false);

            RetryDispatcher::log('settings_saved', [
                'enable_real_push' => $enableRealPush,
                'has_token' => $token !== '',
                'has_test_uid' => $testUid !== '',
            ]);

            \wp_safe_redirect(self::adminUrl(['saved' => '1']));

            exit;
        }

        if ($action === 'bind_uid_to_user') {
            $wpUserId = \absint($_POST['wp_user_id'] ?? 0);

            $uid = \sanitize_text_field(\wp_unslash($_POST['uid'] ?? ''));

            if ($wpUserId > 0 && $uid !== '') {
                \update_user_meta($wpUserId, self::USER_META_UID, $uid);

                RetryDispatcher::log('user_uid_bound', [
                    'wp_user_id' => $wpUserId,
                    'uid' => $uid,
                ]);
            } else {
                RetryDispatcher::log('user_uid_bind_failed', [
                    'wp_user_id' => $wpUserId,
                    'uid_present' => $uid !== '',
                ]);
            }

            \wp_safe_redirect(self::adminUrl());

            exit;
        }

        if ($action === 'clear_logs') {
            RetryDispatcher::clear_logs();

            \wp_safe_redirect(self::adminUrl());

            exit;
        }

        if ($action === 'enqueue_order_paid' || $action === 'enqueue_shipped') {
            $wpUserId = \absint($_POST['wp_user_id'] ?? 0);

            $orderId = \sanitize_text_field(\wp_unslash($_POST['order_id'] ?? ''));

            if ($orderId === '') {
                $orderId = 'demo-' . gmdate('Ymd-His');
            }

            $event = $action === 'enqueue_order_paid' ? RetryDispatcher::EVENT_ORDER_PAID : RetryDispatcher::EVENT_SHIPPED;

            RetryDispatcher::enqueue_with_retry_schedule($event, $orderId, $wpUserId);

            \wp_safe_redirect(self::adminUrl(['enqueued' => $event]));

            exit;
        }
    }

    public static function renderPage(): void
    {
        if (!\current_user_can('manage_options')) {
            \wp_die(\esc_html__('你沒有權限使用這個功能。', 'buygo-line-notify'));
        }

        $token = (string) \get_option(self::OPTION_CHANNEL_ACCESS_TOKEN, '');

        $enableRealPush = (string) \get_option(self::OPTION_ENABLE_REAL_PUSH, 'no');

        $testUid = (string) \get_option(self::OPTION_TEST_UID, '');

        $logs = RetryDispatcher::get_logs();

        $adminUrl = self::adminUrl();

        ?>
        <div class="wrap">

            <h1><?php echo \esc_html__('BuyGo LINE 通知（Demo）', 'buygo-line-notify'); ?></h1>

            <p><?php echo \esc_html__('這是 Demo 頁：先讓你在後台測試「排程重試 1/2/5 分鐘、最多 3 次、每筆只送一次」的整體流程。', 'buygo-line-notify'); ?></p>

            <hr />

            <h2><?php echo \esc_html__('基本設定', 'buygo-line-notify'); ?></h2>

            <form method="post" action="<?php echo esc_url($adminUrl); ?>">

                <?php \wp_nonce_field('buygo_line_notify_demo_action', 'buygo_line_notify_nonce'); ?>

                <input type="hidden" name="buygo_line_notify_action" value="save_settings" />

                <table class="form-table" role="presentation">

                    <tr>

                        <th scope="row">
                            <label for="channel_access_token"><?php echo \esc_html__('LINE Channel access token', 'buygo-line-notify'); ?></label>
                        </th>

                        <td>
                            <input
                                name="channel_access_token"
                                id="channel_access_token"
                                type="text"
                                class="regular-text"
                                value="<?php echo \esc_attr($token); ?>"
                                autocomplete="off"
                            />

                            <p class="description"><?php echo \esc_html__('Demo 先用 push message。沒有填 token 也可以跑流程（只會記錄不會真的推播）。', 'buygo-line-notify'); ?></p>
                        </td>

                    </tr>

                    <tr>

                        <th scope="row"><?php echo \esc_html__('是否真的推播', 'buygo-line-notify'); ?></th>

                        <td>
                            <label>
                                <input name="enable_real_push" type="checkbox" value="1" <?php \checked($enableRealPush, 'yes'); ?> />

                                <?php echo \esc_html__('啟用（未勾選＝只記錄，不送出）', 'buygo-line-notify'); ?>
                            </label>
                        </td>

                    </tr>

                    <tr>

                        <th scope="row">
                            <label for="test_uid"><?php echo \esc_html__('測試用 LINE UID（可推播）', 'buygo-line-notify'); ?></label>
                        </th>

                        <td>
                            <input
                                name="test_uid"
                                id="test_uid"
                                type="text"
                                class="regular-text"
                                value="<?php echo \esc_attr($testUid); ?>"
                                autocomplete="off"
                            />

                            <p class="description"><?php echo \esc_html__('沒有指定 WP user_id 時，會用這個 UID 當推播目標（方便 Demo）。', 'buygo-line-notify'); ?></p>
                        </td>

                    </tr>

                </table>

                <?php \submit_button(\__('儲存設定', 'buygo-line-notify')); ?>

            </form>

            <hr />

            <h2><?php echo \esc_html__('Demo：綁定 UID 到 WP user_meta', 'buygo-line-notify'); ?></h2>

            <form method="post" action="<?php echo esc_url($adminUrl); ?>">

                <?php \wp_nonce_field('buygo_line_notify_demo_action', 'buygo_line_notify_nonce'); ?>

                <input type="hidden" name="buygo_line_notify_action" value="bind_uid_to_user" />

                <table class="form-table" role="presentation">

                    <tr>

                        <th scope="row"><label for="wp_user_id"><?php echo \esc_html__('WP user_id', 'buygo-line-notify'); ?></label></th>

                        <td><input name="wp_user_id" id="wp_user_id" type="number" class="small-text" value="" /></td>

                    </tr>

                    <tr>

                        <th scope="row"><label for="uid"><?php echo \esc_html__('LINE UID', 'buygo-line-notify'); ?></label></th>

                        <td><input name="uid" id="uid" type="text" class="regular-text" value="" autocomplete="off" /></td>

                    </tr>

                </table>

                <?php \submit_button(\__('寫入 user_meta（Demo）', 'buygo-line-notify'), 'secondary'); ?>

            </form>

            <hr />

            <h2><?php echo \esc_html__('Demo：排程推播（含重試）', 'buygo-line-notify'); ?></h2>

            <form method="post" action="<?php echo esc_url($adminUrl); ?>">

                <?php \wp_nonce_field('buygo_line_notify_demo_action', 'buygo_line_notify_nonce'); ?>

                <table class="form-table" role="presentation">

                    <tr>

                        <th scope="row"><label for="order_id"><?php echo \esc_html__('訂單代號（Demo）', 'buygo-line-notify'); ?></label></th>

                        <td>
                            <input name="order_id" id="order_id" type="text" class="regular-text" value="" placeholder="留空會自動生成 demo-YYYYmmdd-HHMMSS" />
                        </td>

                    </tr>

                    <tr>

                        <th scope="row"><label for="wp_user_id_2"><?php echo \esc_html__('WP user_id（可選）', 'buygo-line-notify'); ?></label></th>

                        <td>
                            <input name="wp_user_id" id="wp_user_id_2" type="number" class="small-text" value="" />

                            <p class="description"><?php echo \esc_html__('有填就用該 user_meta 的 UID；沒填就用上面的「測試用 UID」。', 'buygo-line-notify'); ?></p>
                        </td>

                    </tr>

                </table>

                <p>
                    <button class="button button-primary" name="buygo_line_notify_action" value="enqueue_order_paid">
                        <?php echo \esc_html__('排程：已下單成功（1/2/5 分鐘）', 'buygo-line-notify'); ?>
                    </button>

                    <button class="button" name="buygo_line_notify_action" value="enqueue_shipped">
                        <?php echo \esc_html__('排程：已出貨（1/2/5 分鐘）', 'buygo-line-notify'); ?>
                    </button>
                </p>

            </form>

            <hr />

            <h2><?php echo \esc_html__('推播紀錄（Demo）', 'buygo-line-notify'); ?></h2>

            <form method="post" action="<?php echo esc_url($adminUrl); ?>">

                <?php \wp_nonce_field('buygo_line_notify_demo_action', 'buygo_line_notify_nonce'); ?>

                <input type="hidden" name="buygo_line_notify_action" value="clear_logs" />

                <?php \submit_button(\__('清空紀錄', 'buygo-line-notify'), 'delete', 'submit', false); ?>

            </form>

            <table class="widefat striped" style="margin-top: 12px;">

                <thead>
                    <tr>
                        <th><?php echo \esc_html__('時間', 'buygo-line-notify'); ?></th>
                        <th><?php echo \esc_html__('事件', 'buygo-line-notify'); ?></th>
                        <th><?php echo \esc_html__('內容', 'buygo-line-notify'); ?></th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($logs)) : ?>
                        <tr>
                            <td colspan="3"><?php echo \esc_html__('目前沒有紀錄。', 'buygo-line-notify'); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($logs as $row) : ?>
                            <tr>
                                <td><?php echo \esc_html($row['ts'] ?? ''); ?></td>
                                <td><code><?php echo \esc_html($row['event'] ?? ''); ?></code></td>
                                <td><pre style="white-space: pre-wrap; margin: 0;"><?php echo \esc_html(\wp_json_encode($row['data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); ?></pre></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>

            </table>

        </div>
        <?php
    }

    private static function adminUrl(array $query = []): string
    {
        $url = \admin_url('admin.php?page=' . self::MENU_SLUG);

        if (!empty($query)) {
            $url = \add_query_arg($query, $url);
        }

        return $url;
    }
}

