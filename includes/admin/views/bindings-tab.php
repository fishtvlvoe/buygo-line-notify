<?php
/**
 * LINE 綁定管理 Tab
 *
 * Available variables:
 * @var array $bindings - 所有 LINE 綁定紀錄
 */

if (!defined('ABSPATH')) {
    exit;
}

// 取得所有 LINE 綁定
global $wpdb;
$table_name = $wpdb->prefix . 'buygo_line_users';
$bindings = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY id DESC", ARRAY_A);
?>

<div class="buygo-line-bindings-manager" style="margin-top: 20px;">
    <h2>LINE 綁定管理</h2>
    <p class="description">查看和管理所有用戶的 LINE 綁定狀態</p>

    <div style="background: white; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin-top: 20px;">
        <?php if (empty($bindings)): ?>
            <p>目前沒有任何 LINE 綁定紀錄。</p>
        <?php else: ?>
            <p><strong>找到 <?php echo count($bindings); ?> 筆 LINE 綁定紀錄</strong></p>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th style="width: 80px;">User ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>LINE UID</th>
                        <th>Display Name</th>
                        <th style="width: 80px;">Avatar</th>
                        <th style="width: 150px;">綁定日期</th>
                        <th style="width: 120px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bindings as $binding): 
                        $user = get_user_by('id', $binding['user_id']);
                        $display_name = get_user_meta($binding['user_id'], 'buygo_line_display_name', true);
                        $avatar_url = get_user_meta($binding['user_id'], 'buygo_line_avatar_url', true);
                    ?>
                        <tr>
                            <td><?php echo esc_html($binding['id']); ?></td>
                            <td><?php echo esc_html($binding['user_id']); ?></td>
                            <td>
                                <?php if ($user): ?>
                                    <a href="<?php echo admin_url('user-edit.php?user_id=' . $binding['user_id']); ?>" target="_blank">
                                        <?php echo esc_html($user->user_login); ?>
                                    </a>
                                <?php else: ?>
                                    <em style="color: #999;">用戶不存在</em>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $user ? esc_html($user->user_email) : ''; ?></td>
                            <td><code style="font-size: 11px;"><?php echo esc_html($binding['line_uid']); ?></code></td>
                            <td><?php echo $display_name ? esc_html($display_name) : '<em style="color: #999;">無</em>'; ?></td>
                            <td>
                                <?php if ($avatar_url): ?>
                                    <img src="<?php echo esc_url($avatar_url); ?>" 
                                         style="width: 40px; height: 40px; border-radius: 50%;" 
                                         alt="LINE Avatar">
                                <?php else: ?>
                                    <em style="color: #999;">無</em>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($binding['link_date']); ?></td>
                            <td>
                                <button type="button" 
                                        class="button button-secondary button-small"
                                        onclick="testBinding(<?php echo $binding['user_id']; ?>)">
                                    測試
                                </button>
                                <button type="button" 
                                        class="button button-link-delete button-small"
                                        onclick="unbindUser(<?php echo $binding['user_id']; ?>, '<?php echo esc_js($user ? $user->user_login : 'Unknown'); ?>')">
                                    解除
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- API 測試區域 -->
    <div style="background: white; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin-top: 20px;">
        <h3>API 測試工具</h3>
        <p class="description">測試 LINE 綁定 REST API 功能</p>
        
        <table class="form-table">
            <tr>
                <th scope="row">測試用戶</th>
                <td>
                    <select id="test-user-id" class="regular-text">
                        <option value="">選擇用戶...</option>
                        <?php foreach ($bindings as $binding): 
                            $user = get_user_by('id', $binding['user_id']);
                            if ($user):
                        ?>
                            <option value="<?php echo $binding['user_id']; ?>">
                                User ID: <?php echo $binding['user_id']; ?> (<?php echo esc_html($user->user_login); ?>)
                            </option>
                        <?php endif; endforeach; ?>
                    </select>
                    <button type="button" class="button button-primary" onclick="runApiTest()">執行測試</button>
                </td>
            </tr>
        </table>

        <div id="api-test-result" style="margin-top: 20px; display: none;">
            <h4>測試結果</h4>
            <div id="api-test-output" style="background: #f5f5f5; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 12px;"></div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // 解除綁定
    window.unbindUser = function(userId, username) {
        if (!confirm('確定要解除 ' + username + ' (User ID: ' + userId + ') 的 LINE 綁定嗎?\n\n此操作將:\n- 刪除 LINE 綁定紀錄\n- 清除 LINE 頭像和顯示名稱')) {
            return;
        }

        $.post(ajaxurl, {
            action: 'buygo_line_unbind',
            user_id: userId,
            _wpnonce: '<?php echo wp_create_nonce('buygo_line_unbind'); ?>'
        }, function(response) {
            if (response.success) {
                alert('已成功解除 LINE 綁定');
                location.reload();
            } else {
                alert('解除綁定失敗: ' + (response.data || '未知錯誤'));
            }
        });
    };

    // 測試綁定
    window.testBinding = function(userId) {
        $('#test-user-id').val(userId);
        runApiTest();
    };

    // 執行 API 測試
    window.runApiTest = function() {
        const userId = $('#test-user-id').val();
        if (!userId) {
            alert('請選擇測試用戶');
            return;
        }

        const $output = $('#api-test-output');
        const $result = $('#api-test-result');
        
        $output.html('⏳ 測試中...');
        $result.show();

        // 呼叫 REST API
        const apiUrl = '<?php echo rest_url('buygo-line-notify/v1/fluentcart/binding-status'); ?>';
        const nonce = '<?php echo wp_create_nonce('wp_rest'); ?>';

        // 模擬該用戶的 API 呼叫(透過 AJAX 代理)
        $.post(ajaxurl, {
            action: 'buygo_line_test_api',
            user_id: userId,
            _wpnonce: '<?php echo wp_create_nonce('buygo_line_test_api'); ?>'
        }, function(response) {
            if (response.success) {
                let html = '<strong>✅ API 回應:</strong>\n';
                html += JSON.stringify(response.data, null, 2);
                html += '\n\n<strong>狀態:</strong> ';
                if (response.data.is_linked) {
                    html += '<span style="color: #06C755; font-weight: bold;">已綁定 ✓</span>';
                } else {
                    html += '<span style="color: #dc3545; font-weight: bold;">未綁定 ✗</span>';
                }
                $output.html(html);
            } else {
                $output.html('❌ 錯誤: ' + (response.data || '未知錯誤'));
            }
        }).fail(function() {
            $output.html('❌ 網路錯誤');
        });
    };
});
</script>

<style>
.buygo-line-bindings-manager .wp-list-table th {
    background: #f9f9f9;
    font-weight: 600;
}

.buygo-line-bindings-manager .wp-list-table td {
    vertical-align: middle;
}

.buygo-line-bindings-manager .button-small {
    height: 26px;
    line-height: 24px;
    padding: 0 8px;
    font-size: 12px;
}

#api-test-output {
    white-space: pre-wrap;
    word-wrap: break-word;
    max-height: 400px;
    overflow-y: auto;
}
</style>
