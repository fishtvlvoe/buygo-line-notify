<?php
/**
 * Login Button Service
 *
 * 負責在各種登入頁面中顯示 LINE 登入按鈕
 * 支援 Fluent Community、Ajax Login Modal、WordPress 原生登入頁面
 *
 * @package BuygoLineNotify
 */

namespace BuygoLineNotify\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LoginButtonService
 *
 * 在不同的登入頁面中整合 LINE 登入按鈕
 */
class LoginButtonService {

	/**
	 * 追蹤已渲染的按鈕，避免重複顯示
	 *
	 * @var array
	 */
	private static $rendered_contexts = [];

	/**
	 * 註冊所有登入按鈕 hooks
	 */
	public static function register_hooks() {
		// 取得按鈕位置設定
		$position = \BuygoLineNotify\Services\SettingsService::get_login_button_position();
		$priority = ( $position === 'after' ) ? 20 : 5; // after = 20（較晚執行），before = 5（較早執行）

		// Fluent Community 登入頁面
		add_action( 'fluent_community/before_auth_form_header', [ __CLASS__, 'render_fluent_community_button' ], $priority, 1 );

		// Ajax Login and Registration Modal Popup Pro
		add_action( 'lrm/login_form/before', [ __CLASS__, 'render_lrm_button' ], $priority );

		// WordPress 原生登入頁面
		add_action( 'login_form', [ __CLASS__, 'render_wp_login_button' ], $priority );
	}

	/**
	 * 渲染 Fluent Community 登入按鈕
	 *
	 * @param string $auth_type 'login' or 'signup'
	 */
	public static function render_fluent_community_button( $auth_type ) {
		// 只在登入頁面顯示
		if ( $auth_type !== 'login' ) {
			return;
		}

		self::render_button( 'fluent-community' );
	}

	/**
	 * 渲染 Ajax Login Modal 登入按鈕
	 */
	public static function render_lrm_button() {
		self::render_button( 'lrm' );
	}

	/**
	 * 渲染 WordPress 原生登入按鈕
	 */
	public static function render_wp_login_button() {
		self::render_button( 'wp-login' );
	}

	/**
	 * 渲染 LINE 登入按鈕
	 *
	 * @param string $context 按鈕顯示的上下文（fluent-community, lrm, wp-login）
	 */
	private static function render_button( $context ) {
		// 避免在同一個 context 中重複渲染按鈕
		if ( isset( self::$rendered_contexts[ $context ] ) ) {
			return;
		}
		self::$rendered_contexts[ $context ] = true;

		// 取得當前頁面 URL 作為 redirect_url
		$redirect_url = self::get_redirect_url();

		// 產生 REST API URL
		$api_url = rest_url( 'buygo-line-notify/v1/login/authorize' );
		$api_url = add_query_arg( 'redirect_url', urlencode( $redirect_url ), $api_url );

		// 按鈕樣式（根據不同上下文調整）
		$button_class = self::get_button_class( $context );

		// 取得自訂按鈕文字（優先使用設定，再使用 filter，最後使用預設值）
		$custom_text  = \BuygoLineNotify\Services\SettingsService::get_login_button_text();
		$button_text  = apply_filters( 'buygo_line_notify/login_button/text', $custom_text, $context );

		// 產生唯一 ID
		$unique_id = 'buygo-line-login-' . uniqid();

		?>
		<div class="buygo-line-login-wrapper <?php echo esc_attr( "buygo-line-login-{$context}" ); ?>" style="margin-bottom: 15px;">
			<button type="button"
			        id="<?php echo esc_attr( $unique_id ); ?>"
			        class="<?php echo esc_attr( $button_class ); ?> buygo-line-login-btn"
			        data-api-url="<?php echo esc_url( $api_url ); ?>">
				<div class="buygo-line-login-icon">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
						<path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63.346 0 .628.285.628.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.282.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/>
					</svg>
				</div>
				<div class="buygo-line-login-label">
					<?php echo esc_html( $button_text ); ?>
				</div>
			</button>
			<span class="buygo-line-login-loading" style="display:none; margin-top: 8px; font-size: 13px; color: #666; text-align: center;">載入中...</span>
		</div>

		<style>
		/* 參考 Nextend Social Login 樣式 */
		.buygo-line-login-btn {
			width: 100% !important;
			padding: 0 !important;
			border: none !important;
			border-radius: 4px !important;
			cursor: pointer !important;
			background-color: #06C755 !important;
			color: #fff !important;
			display: flex !important;
			align-items: center !important;
			vertical-align: top !important;
			transition: background-color 0.2s !important;
			box-sizing: border-box !important;
			font-family: Helvetica, Arial, sans-serif !important;
		}
		.buygo-line-login-btn:hover {
			background-color: #05b34a !important;
		}
		.buygo-line-login-btn:active {
			background-color: #049a3f !important;
		}
		.buygo-line-login-btn:disabled {
			opacity: 0.6 !important;
			cursor: not-allowed !important;
		}
		.buygo-line-login-icon {
			flex: 0 0 auto !important;
			padding: 8px !important;
			display: flex !important;
			align-items: center !important;
		}
		.buygo-line-login-icon svg {
			height: 24px !important;
			width: 24px !important;
			vertical-align: top !important;
		}
		.buygo-line-login-label {
			margin: 0 24px 0 12px !important;
			padding: 10px 0 !important;
			font-size: 16px !important;
			line-height: 20px !important;
			letter-spacing: 0.25px !important;
			overflow: hidden !important;
			text-align: center !important;
			text-overflow: clip !important;
			white-space: nowrap !important;
			flex: 1 1 auto !important;
			-webkit-font-smoothing: antialiased !important;
			-moz-osx-font-smoothing: grayscale !important;
			text-transform: none !important;
			display: inline-block !important;
		}
		</style>

		<script>
		(function() {
			var btn = document.getElementById('<?php echo esc_js( $unique_id ); ?>');
			if (!btn) return;

			btn.addEventListener('click', function() {
				var apiUrl = this.getAttribute('data-api-url');
				var wrapper = this.closest('.buygo-line-login-wrapper');
				var loading = wrapper ? wrapper.querySelector('.buygo-line-login-loading') : null;

				this.disabled = true;
				if (loading) loading.style.display = 'inline';

				fetch(apiUrl, {
					method: 'GET',
					credentials: 'same-origin'
				})
				.then(function(response) { return response.json(); })
				.then(function(data) {
					if (data.success && data.authorize_url) {
						// 直接在當前視窗跳轉到 LINE 授權頁面
						window.location.href = data.authorize_url;
					} else {
						alert('取得授權 URL 失敗：' + (data.message || '未知錯誤'));
						btn.disabled = false;
						if (loading) loading.style.display = 'none';
					}
				})
				.catch(function(error) {
					console.error('Error:', error);
					alert('發生錯誤：' + error.message);
					btn.disabled = false;
					if (loading) loading.style.display = 'none';
				});
			});
		})();
		</script>
		<?php
	}

	/**
	 * 取得登入後的導向 URL
	 *
	 * @return string
	 */
	private static function get_redirect_url() {
		// 如果是 Ajax 請求或 REST API，使用 referer
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX || defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$redirect_url = wp_get_referer();
			if ( $redirect_url ) {
				return $redirect_url;
			}
		}

		// 檢查是否有 redirect_to 參數
		if ( ! empty( $_GET['redirect_to'] ) ) {
			return esc_url_raw( $_GET['redirect_to'] );
		}

		// 使用當前頁面 URL
		$current_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

		// 如果是登入頁面，導向首頁
		if ( strpos( $current_url, 'wp-login.php' ) !== false ) {
			return home_url();
		}

		return $current_url;
	}

	/**
	 * 根據上下文取得按鈕 CSS class
	 *
	 * @param string $context
	 * @return string
	 */
	private static function get_button_class( $context ) {
		$classes = [ 'buygo-line-login-button' ];

		switch ( $context ) {
			case 'fluent-community':
				$classes[] = 'fcom_button';
				$classes[] = 'fcom_button_primary';
				break;

			case 'lrm':
				$classes[] = 'lrm-button';
				break;

			case 'wp-login':
				$classes[] = 'button';
				$classes[] = 'button-primary';
				break;
		}

		return implode( ' ', apply_filters( 'buygo_line_notify/login_button/classes', $classes, $context ) );
	}
}
