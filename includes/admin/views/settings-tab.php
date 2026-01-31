<?php
/**
 * 設定 Tab
 *
 * Available variables:
 * @var array $settings - 設定值（已解密）
 * @var string $webhook_url - Webhook URL
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

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
                        <code id="callback-url"><?php echo esc_url(site_url('wp-login.php?loginSocial=buygo-line')); ?></code>
                        <button type="button"
                                class="button button-secondary"
                                onclick="copyCallbackUrl()">
                            複製
                        </button>
                        <p class="description">請將此 URL 設定到 LINE Developers Console 的 Callback URL 欄位</p>
                    </td>
                </tr>

                <!-- 預設登入後跳轉 URL -->
                <tr>
                    <th scope="row">
                        <label for="default_redirect_url">預設登入後跳轉 URL</label>
                    </th>
                    <td>
                        <input type="url"
                               id="default_redirect_url"
                               name="default_redirect_url"
                               value="<?php echo esc_attr($settings['default_redirect_url'] ?? home_url('/my-account/')); ?>"
                               class="regular-text"
                               placeholder="<?php echo esc_attr(home_url('/my-account/')); ?>">
                        <p class="description">
                            用戶完成 LINE 登入/註冊/綁定後的預設跳轉頁面。<br>
                            若未設定，將使用「<?php echo esc_html(home_url('/my-account/')); ?>」<br>
                            常用設定：<code><?php echo esc_html(home_url('/')); ?></code>（首頁）、<code><?php echo esc_html(home_url('/my-account/')); ?></code>（我的帳號）
                        </p>
                    </td>
                </tr>

                <!-- Register Flow Page 選擇器 -->
                <tr>
                    <th scope="row">
                        <label for="register_flow_page">LINE 註冊流程頁面</label>
                    </th>
                    <td>
                        <?php
                        $register_flow_page_id = get_option('buygo_line_register_flow_page', 0);
                        wp_dropdown_pages([
                            'name'              => 'register_flow_page',
                            'id'                => 'register_flow_page',
                            'selected'          => $register_flow_page_id,
                            'show_option_none'  => '— 使用預設（wp-login.php）—',
                            'option_none_value' => 0,
                        ]);
                        ?>
                        <p class="description">
                            選擇一個包含 <code>[buygo_line_register_flow]</code> shortcode 的頁面。<br>
                            若未選擇，新用戶將在 wp-login.php 上看到註冊表單。
                        </p>
                        <?php
                        // 檢查所選頁面是否包含 shortcode
                        if ($register_flow_page_id) {
                            $page = get_post($register_flow_page_id);
                            if ($page) {
                                $has_shortcode = has_shortcode($page->post_content, 'buygo_line_register_flow');
                                if (!$has_shortcode) {
                                    echo '<div class="notice notice-warning inline" style="margin-top: 10px; padding: 10px;">';
                                    echo '<p><strong>警告：</strong>所選頁面未包含 <code>[buygo_line_register_flow]</code> shortcode。</p>';
                                    echo '<p>請編輯該頁面並新增 shortcode，或選擇其他頁面。</p>';
                                    echo '</div>';
                                } else {
                                    echo '<div class="notice notice-success inline" style="margin-top: 10px; padding: 10px;">';
                                    echo '<p>✓ 頁面已正確包含 shortcode</p>';
                                    echo '</div>';
                                }
                            }
                        }
                        ?>
                    </td>
                </tr>

                <!-- 快速建立頁面按鈕 -->
                <tr>
                    <th scope="row">
                        <label>快速建立頁面</label>
                    </th>
                    <td>
                        <button type="button" id="create-register-flow-page" class="button button-secondary">
                            自動建立註冊頁面
                        </button>
                        <span id="create-page-status" style="margin-left: 10px;"></span>
                        <p class="description">
                            點擊後會自動建立一個包含 shortcode 的「LINE 註冊」頁面。
                        </p>

                        <script>
                        document.getElementById('create-register-flow-page').addEventListener('click', function() {
                            const btn = this;
                            const status = document.getElementById('create-page-status');

                            btn.disabled = true;
                            status.textContent = '建立中...';
                            status.style.color = '#666';

                            // 發送 AJAX 請求建立頁面
                            fetch('<?php echo esc_js(admin_url('admin-ajax.php')); ?>', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: new URLSearchParams({
                                    action: 'buygo_line_create_register_page',
                                    _ajax_nonce: '<?php echo wp_create_nonce('buygo_line_create_register_page'); ?>'
                                })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    status.textContent = '✓ 頁面建立成功！';
                                    status.style.color = '#46b450';

                                    // 更新下拉選單
                                    const select = document.getElementById('register_flow_page');
                                    const option = document.createElement('option');
                                    option.value = data.data.page_id;
                                    option.textContent = data.data.page_title;
                                    option.selected = true;
                                    select.appendChild(option);

                                    // 顯示編輯連結
                                    status.innerHTML = '✓ 頁面建立成功！<a href="' + data.data.edit_url + '" target="_blank" style="margin-left: 10px;">編輯頁面</a>';
                                } else {
                                    status.textContent = '✗ 建立失敗：' + data.data.message;
                                    status.style.color = '#dc3232';
                                    btn.disabled = false;
                                }
                            })
                            .catch(error => {
                                status.textContent = '✗ 發生錯誤';
                                status.style.color = '#dc3232';
                                btn.disabled = false;
                            });
                        });
                        </script>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label>測試登入</label>
                    </th>
                    <td>
                        <button type="button"
                                id="line-login-test"
                                class="button">
                            使用 LINE 登入測試
                        </button>
                        <span id="line-login-loading" style="display:none; margin-left: 10px;">載入中...</span>
                        <p class="description">點擊測試 LINE Login 流程</p>

                        <script>
                        document.getElementById('line-login-test').addEventListener('click', function() {
                            const btn = this;
                            const loading = document.getElementById('line-login-loading');

                            // 禁用按鈕
                            btn.disabled = true;
                            loading.style.display = 'inline';

                            // 取得 authorize URL
                            const redirectUrl = '<?php echo esc_js(admin_url('admin.php?page=buygo-line-notify-settings')); ?>';
                            const apiUrl = '<?php echo esc_js(rest_url('buygo-line-notify/v1/login/authorize')); ?>' +
                                          '?redirect_url=' + encodeURIComponent(redirectUrl);

                            fetch(apiUrl, {
                                method: 'GET',
                                credentials: 'same-origin'
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success && data.authorize_url) {
                                    // 重導向到 LINE 授權頁面
                                    window.location.href = data.authorize_url;
                                } else {
                                    alert('取得授權 URL 失敗');
                                    btn.disabled = false;
                                    loading.style.display = 'none';
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                alert('發生錯誤：' + error.message);
                                btn.disabled = false;
                                loading.style.display = 'none';
                            });
                        });
                        </script>
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

        <h2>前台登入按鈕設定</h2>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="login_button_position">按鈕位置</label>
                    </th>
                    <td>
                        <select id="login_button_position" name="login_button_position">
                            <option value="before" <?php selected($settings['login_button_position'], 'before'); ?>>
                                在其他登入方式之前顯示
                            </option>
                            <option value="after" <?php selected($settings['login_button_position'], 'after'); ?>>
                                在其他登入方式之後顯示
                            </option>
                        </select>
                        <p class="description">控制 LINE 登入按鈕在登入頁面的顯示位置</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="login_button_text">按鈕文字</label>
                    </th>
                    <td>
                        <input type="text"
                               id="login_button_text"
                               name="login_button_text"
                               value="<?php echo esc_attr($settings['login_button_text'] ?: '使用 LINE 登入'); ?>"
                               class="regular-text"
                               placeholder="使用 LINE 登入">
                        <p class="description">自訂 LINE 登入按鈕上顯示的文字</p>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- Profile Sync 設定 -->
        <h2>Profile 同步設定</h2>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="buygo_line_sync_on_login">登入時更新 Profile</label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" name="buygo_line_sync_on_login" id="buygo_line_sync_on_login"
                                value="1" <?php checked(\BuygoLineNotify\Services\SettingsService::get('sync_on_login', false)); ?>>
                            啟用登入時自動同步 Profile
                        </label>
                        <p class="description">
                            從 LINE 同步最新的名稱、Email、頭像。<br>
                            <strong>注意：</strong>可能覆蓋用戶手動修改的資料，建議僅在初期使用。
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label>衝突處理策略</label>
                    </th>
                    <td>
                        <?php $conflict_strategy = \BuygoLineNotify\Services\SettingsService::get('conflict_strategy', 'line_priority'); ?>
                        <fieldset>
                            <legend class="screen-reader-text"><span>衝突處理策略</span></legend>

                            <label>
                                <input type="radio" name="buygo_line_conflict_strategy" value="line_priority"
                                    <?php checked($conflict_strategy, 'line_priority'); ?>>
                                <strong>LINE 優先</strong> — LINE profile 覆蓋 WordPress 資料
                            </label>
                            <br>

                            <label>
                                <input type="radio" name="buygo_line_conflict_strategy" value="wordpress_priority"
                                    <?php checked($conflict_strategy, 'wordpress_priority'); ?>>
                                <strong>WordPress 優先</strong> — 保留 WordPress 現有資料，只寫入空白欄位
                            </label>
                            <br>

                            <label>
                                <input type="radio" name="buygo_line_conflict_strategy" value="manual"
                                    <?php checked($conflict_strategy, 'manual'); ?>>
                                <strong>手動處理</strong> — 不自動同步，記錄差異讓管理員決定
                            </label>

                            <p class="description">
                                當 LINE profile 與 WordPress 用戶資料不一致時的處理方式。<br>
                                預設「LINE 優先」適合大多數情況，「手動處理」適合需要審核用戶資料變更的場景。
                            </p>
                        </fieldset>
                    </td>
                </tr>
                <tr>
                    <th scope="row">清除頭像快取</th>
                    <td>
                        <button type="button" class="button" id="buygo-clear-avatar-cache">
                            清除所有用戶的 LINE 頭像快取
                        </button>
                        <span id="buygo-clear-cache-result" style="margin-left: 10px;"></span>
                        <p class="description">
                            清除後，下次顯示頭像時會使用快取（若未過期）或等待下次登入更新。
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>

        <!-- 開發者工具 -->
        <h2 style="color: #d63638;">⚠️ 開發者工具</h2>
        <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-bottom: 20px;">
            <strong>警告：</strong>此區塊僅供開發和測試使用。刪除操作無法復原，請謹慎操作。
        </div>
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">已綁定 LINE 的測試用戶</th>
                    <td>
                        <div id="line-users-list" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fafafa;">
                            <p style="text-align: center; color: #666;">載入中...</p>
                        </div>
                        <p class="description">
                            點擊「刪除」會同時移除：<br>
                            1. LINE 綁定資料（wp_buygo_line_users 表）<br>
                            2. WordPress 用戶帳號（包含所有 user_meta）<br>
                            3. Profile Sync 和 Avatar 快取<br>
                            <strong style="color: #d63638;">此操作無法復原！</strong>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">批次操作</th>
                    <td>
                        <button type="button" class="button button-secondary" id="buygo-refresh-users">
                            🔄 重新整理用戶列表
                        </button>
                        <button type="button" class="button" id="buygo-delete-all-test-users" style="margin-left: 10px; background: #d63638; color: #fff; border-color: #d63638;">
                            🗑️ 刪除所有測試用戶
                        </button>
                        <p class="description">
                            「刪除所有測試用戶」會刪除除了管理員以外的所有已綁定 LINE 的用戶。
                        </p>
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
    const callbackUrl = '<?php echo esc_js(site_url('wp-login.php?loginSocial=buygo-line')); ?>';

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

// 清除頭像快取按鈕處理
jQuery(document).ready(function($) {
    $('#buygo-clear-avatar-cache').on('click', function() {
        var $button = $(this);
        var $result = $('#buygo-clear-cache-result');

        $button.prop('disabled', true).text('清除中...');
        $result.text('');

        $.post(ajaxurl, {
            action: 'buygo_line_clear_avatar_cache',
            nonce: '<?php echo wp_create_nonce('buygo_line_clear_avatar_cache'); ?>'
        }, function(response) {
            if (response.success) {
                $result.html('<span style="color: green;">已清除 ' + response.data.count + ' 個用戶的頭像快取</span>');
            } else {
                $result.html('<span style="color: red;">清除失敗：' + (response.data.message || '未知錯誤') + '</span>');
            }
            $button.prop('disabled', false).text('清除所有用戶的 LINE 頭像快取');
        }).fail(function() {
            $result.html('<span style="color: red;">請求失敗，請重試</span>');
            $button.prop('disabled', false).text('清除所有用戶的 LINE 頭像快取');
        });
    });

    // 載入 LINE 用戶列表
    function loadLineUsers() {
        var $list = $('#line-users-list');
        $list.html('<p style="text-align: center; color: #666;">載入中...</p>');

        $.post(ajaxurl, {
            action: 'buygo_line_get_users',
            nonce: '<?php echo wp_create_nonce('buygo_line_dev_tools'); ?>'
        }, function(response) {
            if (response.success && response.data.users.length > 0) {
                var html = '<table class="widefat" style="background: #fff;"><thead><tr>' +
                    '<th>User ID</th><th>用戶名稱</th><th>Email</th><th>LINE UID</th><th>角色</th><th>操作</th>' +
                    '</tr></thead><tbody>';

                response.data.users.forEach(function(user) {
                    var isAdmin = user.roles.includes('administrator');
                    var deleteBtn = isAdmin
                        ? '<span style="color: #999;">無法刪除管理員</span>'
                        : '<button type="button" class="button button-small delete-user-btn" data-user-id="' + user.ID + '" style="background: #d63638; color: #fff; border-color: #d63638;">刪除</button>';

                    html += '<tr>' +
                        '<td>' + user.ID + '</td>' +
                        '<td>' + user.display_name + '</td>' +
                        '<td>' + user.user_email + '</td>' +
                        '<td style="font-family: monospace; font-size: 11px;">' + user.line_uid + '</td>' +
                        '<td>' + user.roles.join(', ') + '</td>' +
                        '<td>' + deleteBtn + '</td>' +
                        '</tr>';
                });

                html += '</tbody></table>';
                $list.html(html);
            } else {
                $list.html('<p style="text-align: center; color: #666;">目前沒有已綁定 LINE 的用戶</p>');
            }
        }).fail(function() {
            $list.html('<p style="text-align: center; color: #d63638;">載入失敗，請重試</p>');
        });
    }

    // 頁面載入時讀取用戶列表
    loadLineUsers();

    // 重新整理按鈕
    $('#buygo-refresh-users').on('click', function() {
        loadLineUsers();
    });

    // 刪除單一用戶
    $(document).on('click', '.delete-user-btn', function() {
        var userId = $(this).data('user-id');
        var $row = $(this).closest('tr');

        if (!confirm('確定要刪除此用戶嗎？\n\n此操作會同時移除：\n1. LINE 綁定資料\n2. WordPress 用戶帳號\n3. 所有相關資料\n\n此操作無法復原！')) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('刪除中...');

        $.post(ajaxurl, {
            action: 'buygo_line_delete_user',
            nonce: '<?php echo wp_create_nonce('buygo_line_dev_tools'); ?>',
            user_id: userId
        }, function(response) {
            if (response.success) {
                $row.fadeOut(300, function() {
                    $(this).remove();
                    if ($('#line-users-list tbody tr').length === 0) {
                        loadLineUsers();
                    }
                });
                alert('用戶已刪除');
            } else {
                alert('刪除失敗：' + (response.data.message || '未知錯誤'));
                $btn.prop('disabled', false).text('刪除');
            }
        }).fail(function() {
            alert('請求失敗，請重試');
            $btn.prop('disabled', false).text('刪除');
        });
    });

    // 批次刪除所有測試用戶
    $('#buygo-delete-all-test-users').on('click', function() {
        if (!confirm('⚠️ 警告：確定要刪除所有測試用戶嗎？\n\n此操作會刪除除了管理員以外的所有已綁定 LINE 的用戶。\n\n此操作無法復原！')) {
            return;
        }

        if (!confirm('最後確認：真的要刪除所有測試用戶嗎？')) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('刪除中...');

        $.post(ajaxurl, {
            action: 'buygo_line_delete_all_test_users',
            nonce: '<?php echo wp_create_nonce('buygo_line_dev_tools'); ?>'
        }, function(response) {
            if (response.success) {
                alert('已刪除 ' + response.data.count + ' 個測試用戶');
                loadLineUsers();
            } else {
                alert('刪除失敗：' + (response.data.message || '未知錯誤'));
            }
            $btn.prop('disabled', false).text('🗑️ 刪除所有測試用戶');
        }).fail(function() {
            alert('請求失敗，請重試');
            $btn.prop('disabled', false).text('🗑️ 刪除所有測試用戶');
        });
    });
});
</script>

<style>
.form-table th {
    width: 200px;
}
.form-table input[readonly] {
    cursor: not-allowed;
}
</style>