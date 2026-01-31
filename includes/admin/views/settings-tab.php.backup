<?php
/**
 * 設定頁面模板（Tab 結構）
 *
 * Available variables:
 * @var array $settings - 設定值（已解密）
 * @var string $webhook_url - Webhook URL
 * @var string $message - 表單提交訊息
 * @var string $current_tab - 當前 tab
 * @var array $bindings - LINE 綁定紀錄
 */

if (!defined('ABSPATH')) {
    exit;
}

// Tab 定義
$tabs = [
    'settings' => 'LINE 設定',
    'bindings' => 'LINE 綁定管理',
];
?>

<div class="wrap">
    <h1>LINE 通知設定</h1>

    <?php if (!empty($message)) echo $message; ?>

    <!-- Tab Navigation -->
    <h2 class="nav-tab-wrapper">
        <?php foreach ($tabs as $tab_key => $tab_label): ?>
            <a href="?page=buygo-line-notify-settings&tab=<?php echo esc_attr($tab_key); ?>"
               class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html($tab_label); ?>
            </a>
        <?php endforeach; ?>
    </h2>

    <!-- Tab Content -->
    <div class="tab-content">
        <?php
        switch ($current_tab) {
            case 'bindings':
                // 載入 LINE 綁定管理 tab
                include BuygoLineNotify_PLUGIN_DIR . 'includes/admin/views/bindings-tab.php';
                break;

            case 'settings':
            default:
                // 載入設定 tab
                include BuygoLineNotify_PLUGIN_DIR . 'includes/admin/views/settings-tab.php';
                break;
        }
        ?>
    </div>
</div>

<style>
.nav-tab-wrapper {
    margin-bottom: 0;
}
.tab-content {
    margin-top: 20px;
}
</style>
