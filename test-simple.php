<?php
// 簡單測試各個步驟
require_once '/Users/fishtv/Local Sites/buygo/app/public/wp-load.php';

echo "<h1>測試步驟</h1>";

// Step 1: 確認 WordPress 載入
echo "<p>✓ WordPress 載入成功</p>";

// Step 2: 確認已登入
if ( ! is_user_logged_in() ) {
    die('<p>✗ 未登入</p>');
}
$current_user = wp_get_current_user();
echo "<p>✓ 已登入：" . esc_html( $current_user->user_login ) . "</p>";

// Step 3: 檢查外掛是否已載入
if ( ! defined( 'BuygoLineNotify_PLUGIN_VERSION' ) ) {
    echo "<p>✗ buygo-line-notify 外掛未載入</p>";
    die();
}
echo "<p>✓ buygo-line-notify 外掛已載入（版本：" . esc_html( BuygoLineNotify_PLUGIN_VERSION ) . "）</p>";

// Step 4: 檢查類別是否存在
if ( ! class_exists( '\BuygoLineNotify\Services\LineUserService' ) ) {
    echo "<p>✗ LineUserService 類別不存在</p>";
    echo "<p>  - 可能原因：外掛初始化失敗或類別檔案未載入</p>";
    die();
}
echo "<p>✓ LineUserService 類別存在</p>";

// Step 5: 測試 LineUserService
try {
    $existing_line_uid = \BuygoLineNotify\Services\LineUserService::getLineUidByUserId( $current_user->ID );
    echo "<p>✓ LineUserService 運作正常</p>";
    if ( $existing_line_uid ) {
        echo "<p>  - 已綁定 LINE UID: " . esc_html( substr( $existing_line_uid, 0, 10 ) . '...' ) . "</p>";
    } else {
        echo "<p>  - 尚未綁定 LINE</p>";
    }
} catch ( Exception $e ) {
    echo "<p>✗ LineUserService 錯誤：" . esc_html( $e->getMessage() ) . "</p>";
    echo "<pre>" . esc_html( $e->getTraceAsString() ) . "</pre>";
}

// Step 6: 測試 LoginService
try {
    $login_service = new \BuygoLineNotify\Services\LoginService();
    echo "<p>✓ LoginService 初始化成功</p>";
} catch ( Exception $e ) {
    echo "<p>✗ LoginService 初始化錯誤：" . esc_html( $e->getMessage() ) . "</p>";
    echo "<pre>" . esc_html( $e->getTraceAsString() ) . "</pre>";
    die();
}

// Step 7: 測試 get_authorize_url
try {
    $redirect_url = home_url( '/my-account/' );
    $authorize_url = $login_service->get_authorize_url( $redirect_url, $current_user->ID );
    echo "<p>✓ get_authorize_url() 運作正常</p>";
    echo "<p>  - URL: <code>" . esc_html( substr( $authorize_url, 0, 100 ) ) . "...</code></p>";
} catch ( Exception $e ) {
    echo "<p>✗ get_authorize_url() 錯誤：" . esc_html( $e->getMessage() ) . "</p>";
    echo "<pre>" . esc_html( $e->getTraceAsString() ) . "</pre>";
}

echo "<h2>測試完成</h2>";
