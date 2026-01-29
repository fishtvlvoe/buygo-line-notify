<?php
/**
 * LINE Login Button Shortcode
 *
 * 提供 [buygo_line_login] shortcode，讓站長可在任何頁面插入 LINE 登入按鈕
 *
 * @package BuygoLineNotify
 */

namespace BuygoLineNotify\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LoginButtonShortcode
 *
 * 渲染 LINE 登入按鈕的 shortcode
 */
class LoginButtonShortcode {

	/**
	 * 註冊 shortcode
	 */
	public static function register(): void {
		\add_shortcode( 'buygo_line_login', [ __CLASS__, 'render' ] );
	}

	/**
	 * 渲染 shortcode
	 *
	 * @param array $atts Shortcode 參數
	 * @return string HTML 輸出
	 */
	public static function render( $atts ): string {
		// 解析參數
		$atts = \shortcode_atts(
			[
				'redirect_url'         => '',    // 登入後導向 URL
				'button_text'          => '',    // 自訂按鈕文字
				'button_class'         => '',    // 自訂 CSS class
				'show_when_logged_in'  => 'no',  // yes/no - 已登入時是否顯示
			],
			$atts,
			'buygo_line_login'
		);

		// 檢查用戶是否已登入
		if ( \is_user_logged_in() ) {
			if ( $atts['show_when_logged_in'] === 'yes' ) {
				return self::render_logged_in_message();
			}
			// 已登入且 show_when_logged_in=no，不顯示按鈕
			return '';
		}

		// 未登入，渲染登入按鈕
		return self::render_login_button( $atts );
	}

	/**
	 * 渲染已登入訊息
	 *
	 * @return string HTML
	 */
	private static function render_logged_in_message(): string {
		$current_user = \wp_get_current_user();
		ob_start();
		?>
		<div class="buygo-line-login-wrapper buygo-line-logged-in" style="margin-bottom: 15px;">
			<p style="padding: 12px; background: #f0f0f0; border-left: 3px solid #06C755; margin: 0;">
				您已登入為 <strong><?php echo \esc_html( $current_user->display_name ); ?></strong>
			</p>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * 渲染 LINE 登入按鈕
	 *
	 * @param array $atts Shortcode 參數
	 * @return string HTML
	 */
	private static function render_login_button( array $atts ): string {
		// 取得 redirect_url（優先使用 shortcode 參數，再使用當前頁面 URL）
		$redirect_url = ! empty( $atts['redirect_url'] )
			? \esc_url_raw( $atts['redirect_url'] )
			: self::get_current_url();

		// 產生 REST API URL
		$api_url = \rest_url( 'buygo-line-notify/v1/login/authorize' );
		$api_url = \add_query_arg( 'redirect_url', \urlencode( $redirect_url ), $api_url );

		// 取得按鈕文字（優先使用 shortcode 參數，再使用後台設定，最後使用預設值）
		$button_text = ! empty( $atts['button_text'] )
			? $atts['button_text']
			: \BuygoLineNotify\Services\SettingsService::get_login_button_text();

		// 取得按鈕 CSS class
		$button_class = 'buygo-line-login-btn';
		if ( ! empty( $atts['button_class'] ) ) {
			$button_class .= ' ' . \sanitize_html_class( $atts['button_class'] );
		}

		// 產生唯一 ID
		$unique_id = 'buygo-line-login-' . \uniqid();

		// 開始輸出緩衝
		ob_start();
		?>
		<div class="buygo-line-login-wrapper buygo-line-login-shortcode" style="margin-bottom: 15px;">
			<button type="button"
			        id="<?php echo \esc_attr( $unique_id ); ?>"
			        class="<?php echo \esc_attr( $button_class ); ?>"
			        data-api-url="<?php echo \esc_url( $api_url ); ?>">
				<div class="buygo-line-login-icon">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor">
						<path d="M19.365 9.863c.349 0 .63.285.63.631 0 .345-.281.63-.63.63H17.61v1.125h1.755c.349 0 .63.283.63.63 0 .344-.281.629-.63.629h-2.386c-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63h2.386c.346 0 .627.285.627.63 0 .349-.281.63-.63.63H17.61v1.125h1.755zm-3.855 3.016c0 .27-.174.51-.432.596-.064.021-.133.031-.199.031-.211 0-.391-.09-.51-.25l-2.443-3.317v2.94c0 .344-.279.629-.631.629-.346 0-.626-.285-.626-.629V8.108c0-.27.173-.51.43-.595.06-.023.136-.033.194-.033.195 0 .375.104.495.254l2.462 3.33V8.108c0-.345.282-.63.63-.63.345 0 .63.285.63.63v4.771zm-5.741 0c0 .344-.282.629-.631.629-.345 0-.627-.285-.627-.629V8.108c0-.345.282-.63.63-.63.346 0 .628.285.628.63v4.771zm-2.466.629H4.917c-.345 0-.63-.285-.63-.629V8.108c0-.345.285-.63.63-.63.348 0 .63.285.63.63v4.141h1.756c.348 0 .629.283.629.63 0 .344-.282.629-.629.629M24 10.314C24 4.943 18.615.572 12 .572S0 4.943 0 10.314c0 4.811 4.27 8.842 10.035 9.608.391.082.923.258 1.058.59.12.301.079.766.038 1.08l-.164 1.02c-.045.301-.24 1.186 1.049.645 1.291-.539 6.916-4.078 9.436-6.975C23.176 14.393 24 12.458 24 10.314"/>
					</svg>
				</div>
				<div class="buygo-line-login-label">
					<?php echo \esc_html( $button_text ); ?>
				</div>
			</button>
			<span class="buygo-line-login-loading" style="display:none; margin-top: 8px; font-size: 13px; color: #666; text-align: center;">載入中...</span>
		</div>

		<style>
		/* 重用 LoginButtonService 的樣式 */
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
			var btn = document.getElementById('<?php echo \esc_js( $unique_id ); ?>');
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
		return ob_get_clean();
	}

	/**
	 * 取得當前頁面 URL
	 *
	 * @return string
	 */
	private static function get_current_url(): string {
		$protocol   = ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) ? 'https://' : 'http://';
		$host       = $_SERVER['HTTP_HOST'] ?? '';
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';

		return $protocol . $host . $request_uri;
	}
}
