<?php
/**
 * 綁定流程後端邏輯測試腳本
 *
 * 使用方式：
 * 1. 以已登入用戶身份訪問此腳本
 * 2. 腳本會產生帶有 user_id 的綁定 URL
 * 3. 點擊連結開始綁定流程
 *
 * 訪問路徑: https://test.buygo.me/wp-content/plugins/buygo-line-notify/test-link-flow.php
 */

// 載入 WordPress（使用絕對路徑）
require_once '/Users/fishtv/Local Sites/buygo/app/public/wp-load.php';

// 確保已登入
if ( ! is_user_logged_in() ) {
	wp_die( '請先登入 WordPress 再執行此測試。<br><a href="' . wp_login_url( $_SERVER['REQUEST_URI'] ) . '">前往登入</a>' );
}

$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// 檢查是否已綁定 LINE
$existing_line_uid = \BuyGo_Line_Notify\Services\LineUserService::getLineUidByUserId( $user_id );

// 產生綁定 URL
$login_service = new \BuyGo_Line_Notify\Services\LoginService();
$redirect_url = home_url( '/my-account/' ); // 綁定後導向
$authorize_url = $login_service->get_authorize_url( $redirect_url, $user_id );

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>LINE 綁定流程測試</title>
	<style>
		body { font-family: sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
		.info { background: #e3f2fd; padding: 15px; border-radius: 5px; margin: 10px 0; }
		.warning { background: #fff3e0; padding: 15px; border-radius: 5px; margin: 10px 0; }
		.success { background: #e8f5e9; padding: 15px; border-radius: 5px; margin: 10px 0; }
		.error { background: #ffebee; padding: 15px; border-radius: 5px; margin: 10px 0; }
		.btn { display: inline-block; padding: 10px 20px; background: #06c755; color: white; text-decoration: none; border-radius: 5px; }
		.btn:hover { background: #05b34a; }
		code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; }
	</style>
</head>
<body>
	<h1>LINE 綁定流程測試（案例 4）</h1>

	<div class="info">
		<strong>當前用戶:</strong> <?php echo esc_html( $current_user->user_login ); ?> (ID: <?php echo $user_id; ?>)<br>
		<strong>Email:</strong> <?php echo esc_html( $current_user->user_email ); ?>
	</div>

	<?php if ( $existing_line_uid ) : ?>
		<div class="warning">
			<strong>注意:</strong> 此帳號已綁定 LINE (UID: <?php echo esc_html( substr( $existing_line_uid, 0, 10 ) . '...' ); ?>)<br>
			若要測試綁定流程，請先手動清除綁定記錄。
		</div>
	<?php else : ?>
		<div class="success">
			<strong>狀態:</strong> 此帳號尚未綁定 LINE，可以進行綁定測試。
		</div>
	<?php endif; ?>

	<h2>測試步驟</h2>
	<ol>
		<li>點擊下方「開始綁定」按鈕</li>
		<li>完成 LINE OAuth 授權</li>
		<li>預期：顯示綁定確認頁面（Fallback 模式）</li>
		<li>點擊確認按鈕</li>
		<li>預期：綁定成功並導向 <?php echo esc_html( $redirect_url ); ?></li>
	</ol>

	<h2>產生的綁定 URL</h2>
	<div class="info">
		<code style="word-break: break-all;"><?php echo esc_html( $authorize_url ); ?></code>
	</div>

	<p>
		<a href="<?php echo esc_url( $authorize_url ); ?>" class="btn">開始綁定 LINE 帳號</a>
	</p>

	<h2>驗證要點</h2>
	<ul>
		<li>State 中應包含 <code>user_id: <?php echo $user_id; ?></code></li>
		<li>OAuth callback 後應顯示「綁定確認」頁面，而非「註冊」頁面</li>
		<li>確認後應執行 <code>handle_link_submission()</code>，而非 <code>handle_register_submission()</code></li>
		<li>綁定成功後應有成功訊息（Transient）</li>
	</ul>

	<hr>
	<p><small>此腳本僅供開發測試使用，請勿在正式環境保留。</small></p>
</body>
</html>
