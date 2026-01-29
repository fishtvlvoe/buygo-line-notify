<?php
/**
 * Debug Page
 *
 * 後台 Debug 工具頁面
 *
 * @package BuygoLineNotify
 */

namespace BuygoLineNotify\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DebugPage
 *
 * 提供後台 Debug 工具界面
 */
class DebugPage
{
    /**
     * 註冊 hooks
     */
    public static function register_hooks(): void
    {
        add_action('admin_menu', [__CLASS__, 'add_menu_page']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /**
     * 註冊後台選單
     */
    public static function add_menu_page(): void
    {
        add_submenu_page(
            'buygo-line-notify',
            'Debug 工具',
            'Debug 工具',
            'manage_options',
            'buygo-line-notify-debug',
            [__CLASS__, 'render_debug_page']
        );
    }

    /**
     * 載入前端資源
     *
     * @param string $hook_suffix
     */
    public static function enqueue_assets(string $hook_suffix): void
    {
        if ($hook_suffix !== 'buygo-line-notify_page_buygo-line-notify-debug') {
            return;
        }

        // 載入 WordPress REST API nonce
        wp_enqueue_script('wp-api');
    }

    /**
     * 渲染 Debug 頁面
     */
    public static function render_debug_page(): void
    {
        $api_base = rest_url('buygo-line-notify/v1');
        $nonce = wp_create_nonce('wp_rest');
        ?>
        <div class="wrap">
            <h1>LINE Notify Debug 工具</h1>

            <!-- 統計資料 -->
            <div id="debug-statistics" style="margin-top: 20px;">
                <h2>統計資料</h2>
                <div id="stats-loading">載入中...</div>
                <div id="stats-content" style="display:none;">
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>類型</th>
                                <th>總計</th>
                                <th>今日</th>
                                <th>額外資訊</th>
                            </tr>
                        </thead>
                        <tbody id="stats-tbody"></tbody>
                    </table>
                </div>
            </div>

            <!-- Webhook 事件記錄 -->
            <div id="debug-webhook-logs" style="margin-top: 40px;">
                <h2>Webhook 事件記錄</h2>
                <div id="webhook-loading">載入中...</div>
                <div id="webhook-content" style="display:none;">
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>事件類型</th>
                                <th>LINE UID</th>
                                <th>User ID</th>
                                <th>Webhook Event ID</th>
                                <th>接收時間</th>
                            </tr>
                        </thead>
                        <tbody id="webhook-tbody"></tbody>
                    </table>
                    <div id="webhook-pagination" style="margin-top: 10px;"></div>
                </div>
            </div>

            <!-- 訊息發送記錄 -->
            <div id="debug-message-logs" style="margin-top: 40px;">
                <h2>訊息發送記錄</h2>
                <div id="message-loading">載入中...</div>
                <div id="message-content" style="display:none;">
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User ID</th>
                                <th>LINE UID</th>
                                <th>訊息類型</th>
                                <th>狀態</th>
                                <th>錯誤訊息</th>
                                <th>發送時間</th>
                            </tr>
                        </thead>
                        <tbody id="message-tbody"></tbody>
                    </table>
                    <div id="message-pagination" style="margin-top: 10px;"></div>
                </div>
            </div>

            <!-- 清除舊記錄 -->
            <div id="debug-clean-logs" style="margin-top: 40px;">
                <h2>清除舊記錄</h2>
                <p>
                    <label for="clean-days">保留天數：</label>
                    <input type="number" id="clean-days" value="30" min="1" max="365" style="width: 80px;">
                    <button type="button" id="clean-logs-btn" class="button button-secondary">清除舊記錄</button>
                </p>
                <div id="clean-result" style="margin-top: 10px;"></div>
            </div>
        </div>

        <script>
        (function($) {
            const API_BASE = '<?php echo esc_js($api_base); ?>';
            const NONCE = '<?php echo esc_js($nonce); ?>';

            // 載入統計資料
            function loadStatistics() {
                $.ajax({
                    url: API_BASE + '/debug/statistics',
                    method: 'GET',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', NONCE);
                    },
                    success: function(data) {
                        $('#stats-loading').hide();
                        $('#stats-content').show();

                        const html = '<tr><td><strong>Webhook 事件</strong></td><td>' + data.webhook.total +
                            '</td><td>' + data.webhook.today + '</td><td>-</td></tr>' +
                            '<tr><td><strong>訊息發送</strong></td><td>' + data.message.total +
                            '</td><td>' + data.message.today + '</td><td>成功: ' + data.message.success +
                            ' / 失敗: ' + data.message.failed + '</td></tr>';
                        $('#stats-tbody').html(html);
                    },
                    error: function() {
                        $('#stats-loading').html('<span style="color: red;">載入失敗</span>');
                    }
                });
            }

            // 載入 Webhook 記錄
            function loadWebhookLogs(page) {
                page = page || 1;
                $.ajax({
                    url: API_BASE + '/debug/webhook-logs',
                    method: 'GET',
                    data: { page: page, per_page: 20 },
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', NONCE);
                    },
                    success: function(data) {
                        $('#webhook-loading').hide();
                        $('#webhook-content').show();

                        let html = '';
                        data.logs.forEach(function(log) {
                            html += '<tr><td>' + log.id + '</td><td>' + log.event_type +
                                '</td><td>' + (log.line_uid || '-') + '</td><td>' + (log.user_id || '-') +
                                '</td><td>' + (log.webhook_event_id || '-') + '</td><td>' + log.received_at + '</td></tr>';
                        });
                        $('#webhook-tbody').html(html);

                        // 分頁
                        let pagination = '第 ' + page + ' / ' + data.total_pages + ' 頁 (總計 ' + data.total + ' 筆) ';
                        if (page > 1) {
                            pagination += '<button class="button" onclick="loadWebhookLogs(' + (page - 1) + ')">上一頁</button> ';
                        }
                        if (page < data.total_pages) {
                            pagination += '<button class="button" onclick="loadWebhookLogs(' + (page + 1) + ')">下一頁</button>';
                        }
                        $('#webhook-pagination').html(pagination);

                        // 將函數暴露給全域
                        window.loadWebhookLogs = loadWebhookLogs;
                    },
                    error: function() {
                        $('#webhook-loading').html('<span style="color: red;">載入失敗</span>');
                    }
                });
            }

            // 載入訊息記錄
            function loadMessageLogs(page) {
                page = page || 1;
                $.ajax({
                    url: API_BASE + '/debug/message-logs',
                    method: 'GET',
                    data: { page: page, per_page: 20 },
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', NONCE);
                    },
                    success: function(data) {
                        $('#message-loading').hide();
                        $('#message-content').show();

                        let html = '';
                        data.logs.forEach(function(log) {
                            const statusClass = log.status === 'success' ? 'green' : 'red';
                            html += '<tr><td>' + log.id + '</td><td>' + log.user_id +
                                '</td><td>' + log.line_uid + '</td><td>' + log.message_type +
                                '</td><td style="color: ' + statusClass + '; font-weight: bold;">' + log.status +
                                '</td><td>' + (log.error_message || '-') + '</td><td>' + log.sent_at + '</td></tr>';
                        });
                        $('#message-tbody').html(html);

                        // 分頁
                        let pagination = '第 ' + page + ' / ' + data.total_pages + ' 頁 (總計 ' + data.total + ' 筆) ';
                        if (page > 1) {
                            pagination += '<button class="button" onclick="loadMessageLogs(' + (page - 1) + ')">上一頁</button> ';
                        }
                        if (page < data.total_pages) {
                            pagination += '<button class="button" onclick="loadMessageLogs(' + (page + 1) + ')">下一頁</button>';
                        }
                        $('#message-pagination').html(pagination);

                        // 將函數暴露給全域
                        window.loadMessageLogs = loadMessageLogs;
                    },
                    error: function() {
                        $('#message-loading').html('<span style="color: red;">載入失敗</span>');
                    }
                });
            }

            // 清除舊記錄
            $('#clean-logs-btn').on('click', function() {
                const days = $('#clean-days').val();

                if (!confirm('確定要刪除 ' + days + ' 天前的記錄嗎？')) {
                    return;
                }

                $.ajax({
                    url: API_BASE + '/debug/clean-logs',
                    method: 'POST',
                    data: { days: days },
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', NONCE);
                    },
                    success: function(data) {
                        $('#clean-result').html('<p style="color: green;">✓ 已刪除：Webhook 事件 ' +
                            data.data.webhook_deleted + ' 筆 / 訊息記錄 ' + data.data.message_deleted + ' 筆</p>');

                        // 重新載入統計資料
                        loadStatistics();
                        loadWebhookLogs(1);
                        loadMessageLogs(1);
                    },
                    error: function() {
                        $('#clean-result').html('<p style="color: red;">✗ 刪除失敗</p>');
                    }
                });
            });

            // 頁面載入時執行
            $(document).ready(function() {
                loadStatistics();
                loadWebhookLogs(1);
                loadMessageLogs(1);
            });
        })(jQuery);
        </script>

        <style>
        .widefat td, .widefat th {
            padding: 8px 10px;
        }
        #debug-webhook-logs, #debug-message-logs, #debug-clean-logs {
            background: white;
            padding: 20px;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        #debug-statistics {
            background: white;
            padding: 20px;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        </style>
        <?php
    }
}
