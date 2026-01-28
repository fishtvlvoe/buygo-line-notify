<?php
/**
 * 設定頁面模板
 *
 * Available variables:
 * @var array $settings - 設定值（已解密）
 * @var string $webhook_url - Webhook URL
 * @var string $message - 表單提交訊息
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>LINE 通知設定</h1>

    <?php if (!empty($message)) echo $message; ?>

    <form method="post" action="">
        <?php wp_nonce_field('buygo_line_settings_action', 'buygo_line_settings_nonce'); ?>

        <table class="form-table" role="presentation">
            <tbody>
                <!-- Webhook URL（唯讀） -->
                <tr>
                    <th scope="row">
                        <label>Webhook URL</label>
                    </th>
                    <td>
                        <input type="text"
                               id="webhook-url"
                               value="<?php echo esc_url($webhook_url); ?>"
                               readonly
                               class="regular-text"
                               style="background-color: #f0f0f0;">
                        <button type="button"
                                class="button button-secondary"
                                onclick="copyWebhookUrl()">
                            複製
                        </button>
                        <p class="description">
                            請複製此 URL 到 <a href="https://developers.line.biz/console/" target="_blank">LINE Developers Console</a> 的 Webhook 設定
                        </p>
                    </td>
                </tr>

                <!-- Messaging API 設定 -->
            </tbody>
        </table>

        <h2>Messaging API 設定</h2>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="channel_access_token">Channel Access Token</label>
                    </th>
                    <td>
                        <input type="text"
                               id="channel_access_token"
                               name="channel_access_token"
                               value="<?php echo esc_attr($settings['channel_access_token']); ?>"
                               class="regular-text">
                        <p class="description">LINE Messaging API 的 Channel Access Token（長期）</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="channel_secret">Channel Secret</label>
                    </th>
                    <td>
                        <input type="text"
                               id="channel_secret"
                               name="channel_secret"
                               value="<?php echo esc_attr($settings['channel_secret']); ?>"
                               class="regular-text">
                        <p class="description">LINE Messaging API 的 Channel Secret（用於 Webhook 簽名驗證）</p>
                    </td>
                </tr>
            </tbody>
        </table>

        <h2>LINE Login 設定</h2>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="login_channel_id">LINE Login Channel ID</label>
                    </th>
                    <td>
                        <input type="text"
                               id="login_channel_id"
                               name="login_channel_id"
                               value="<?php echo esc_attr($settings['login_channel_id']); ?>"
                               class="regular-text">
                        <p class="description">從 LINE Developers Console 的 LINE Login Channel 取得</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="login_channel_secret">LINE Login Channel Secret</label>
                    </th>
                    <td>
                        <input type="password"
                               id="login_channel_secret"
                               name="login_channel_secret"
                               value="<?php echo esc_attr($settings['login_channel_secret']); ?>"
                               class="regular-text">
                        <p class="description">從 LINE Developers Console 的 LINE Login Channel 取得</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label>Callback URL</label>
                    </th>
                    <td>
                        <code><?php echo esc_url(rest_url('buygo-line-notify/v1/login/callback')); ?></code>
                        <button type="button"
                                class="button button-secondary"
                                onclick="copyCallbackUrl()">
                            複製
                        </button>
                        <p class="description">請將此 URL 設定到 LINE Developers Console 的 Callback URL 欄位</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label>測試登入</label>
                    </th>
                    <td>
                        <a href="<?php echo esc_url(rest_url('buygo-line-notify/v1/login/authorize?redirect_url=' . urlencode(admin_url('admin.php?page=buygo-line-notify-settings')))); ?>"
                           class="button">
                            使用 LINE 登入測試
                        </a>
                        <p class="description">點擊測試 LINE Login 流程</p>
                    </td>
                </tr>

            </tbody>
        </table>

        <h2>LIFF 設定</h2>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="liff_id">LIFF ID</label>
                    </th>
                    <td>
                        <input type="text"
                               id="liff_id"
                               name="liff_id"
                               value="<?php echo esc_attr($settings['liff_id']); ?>"
                               class="regular-text"
                               placeholder="1234567890-abcdefgh">
                        <p class="description">LIFF App 的 ID（格式：1234567890-abcdefgh）</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="liff_endpoint_url">LIFF Endpoint URL</label>
                    </th>
                    <td>
                        <input type="url"
                               id="liff_endpoint_url"
                               name="liff_endpoint_url"
                               value="<?php echo esc_attr($settings['liff_endpoint_url']); ?>"
                               class="regular-text"
                               placeholder="https://test.buygo.me/liff">
                        <p class="description">LIFF 頁面的 URL（用於 LINE 瀏覽器登入）</p>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button('儲存設定', 'primary', 'buygo_line_settings_submit'); ?>
    </form>
</div>

<script>
function copyWebhookUrl() {
    const input = document.getElementById('webhook-url');
    input.select();
    input.setSelectionRange(0, 99999); // Mobile compatibility

    navigator.clipboard.writeText(input.value).then(() => {
        // 顯示成功提示
        const button = event.target;
        const originalText = button.textContent;
        button.textContent = '已複製！';
        button.style.color = '#46b450';

        setTimeout(() => {
            button.textContent = originalText;
            button.style.color = '';
        }, 2000);
    }).catch(() => {
        // Fallback for older browsers
        alert('請手動複製 Webhook URL');
    });
}

function copyCallbackUrl() {
    const callbackUrl = '<?php echo esc_js(rest_url('buygo-line-notify/v1/login/callback')); ?>';

    navigator.clipboard.writeText(callbackUrl).then(() => {
        // 顯示成功提示
        const button = event.target;
        const originalText = button.textContent;
        button.textContent = '已複製！';
        button.style.color = '#46b450';

        setTimeout(() => {
            button.textContent = originalText;
            button.style.color = '';
        }, 2000);
    }).catch(() => {
        // Fallback for older browsers
        alert('請手動複製 Callback URL');
    });
}
</script>

<style>
.form-table th {
    width: 200px;
}
.form-table input[readonly] {
    cursor: not-allowed;
}
</style>
