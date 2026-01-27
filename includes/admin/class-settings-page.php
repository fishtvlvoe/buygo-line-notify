<?php

namespace BuygoLineNotify\Admin;

/**
 * 後台設定頁面管理
 *
 * 根據父外掛（buygo-plus-one-dev）是否存在，動態決定選單位置：
 * - 父外掛存在：掛載為其子選單
 * - 父外掛不存在：建立獨立一級選單
 */
final class SettingsPage
{
    /**
     * 註冊 WordPress hooks
     */
    public static function register_hooks(): void
    {
        \add_action('admin_menu', [self::class, 'add_admin_menu']);
    }

    /**
     * 根據父外掛是否存在，動態掛載選單
     *
     * 在 admin_menu hook 執行時，所有外掛已載入完成，
     * 可以安全地使用 class_exists() 檢查父外掛。
     */
    public static function add_admin_menu(): void
    {
        // 偵測 buygo-plus-one-dev 是否存在
        if (\class_exists('BuyGoPlus\Plugin')) {
            // 父外掛存在：掛載為子選單
            \add_submenu_page(
                'buygo-plus-one',              // 父選單 slug
                'LINE 串接通知',                // 頁面標題
                'LINE 通知',                   // 選單標題
                'manage_options',              // 權限
                'buygo-line-notify-settings',  // 選單 slug
                [self::class, 'render_settings_page']
            );
        } else {
            // 父外掛不存在：建立獨立一級選單
            \add_menu_page(
                'LINE 通知',                   // 頁面標題
                'LINE 通知',                   // 選單標題
                'manage_options',              // 權限
                'buygo-line-notify',           // 選單 slug
                [self::class, 'render_settings_page'],
                'dashicons-format-chat',       // icon
                50                             // position
            );

            // 子選單：設定（與一級選單使用相同 slug，會取代預設的重複項目）
            \add_submenu_page(
                'buygo-line-notify',
                'LINE 設定',
                '設定',
                'manage_options',
                'buygo-line-notify-settings',
                [self::class, 'render_settings_page']
            );
        }
    }

    /**
     * 渲染設定頁面
     *
     * 目前為空白頁面，實際設定 UI 將在 Plan 01-04 實作。
     */
    public static function render_settings_page(): void
    {
        if (!\current_user_can('manage_options')) {
            \wp_die(\__('您沒有權限訪問此頁面。', 'buygo-line-notify'));
        }

        $is_submenu = \class_exists('BuyGoPlus\Plugin');
        $menu_position = $is_submenu ? '子選單（buygo-plus-one 下）' : '獨立一級選單';

        ?>
        <div class="wrap">
            <h1>LINE 通知設定</h1>
            <p>設定頁面 UI 將在下一個 Plan 實作。</p>
            <p>目前選單位置：<strong><?php echo \esc_html($menu_position); ?></strong></p>

            <hr />

            <h2>系統狀態</h2>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th>父外掛（buygo-plus-one-dev）</th>
                        <td><?php echo $is_submenu ? '<span style="color: green;">✓ 已安裝</span>' : '<span style="color: gray;">✗ 未安裝</span>'; ?></td>
                    </tr>
                    <tr>
                        <th>選單類型</th>
                        <td><?php echo \esc_html($menu_position); ?></td>
                    </tr>
                    <tr>
                        <th>當前用戶權限</th>
                        <td><?php echo \current_user_can('manage_options') ? '管理員' : '無權限'; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }
}
