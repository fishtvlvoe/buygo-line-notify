<?php
/**
 * LINE Binding Status Shortcode
 *
 * 提供 [buygo_line_binding] shortcode，顯示完整的 LINE 綁定管理介面
 *
 * @package BuygoLineNotify
 */

namespace BuygoLineNotify\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LineBindingShortcode
 *
 * 渲染 LINE 綁定狀態和管理介面的 shortcode
 */
class LineBindingShortcode {

	/**
	 * 註冊 shortcode
	 */
	public static function register(): void {
		\add_shortcode( 'buygo_line_binding', [ __CLASS__, 'render' ] );
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
				'show_title'     => 'yes',      // yes/no - 是否顯示標題
				'title'          => 'LINE 帳號綁定', // 自訂標題
				'redirect_url'   => '',         // 綁定後導向 URL
			],
			$atts,
			'buygo_line_binding'
		);

		// 檢查用戶是否已登入
		if ( ! \is_user_logged_in() ) {
			return self::render_login_required();
		}

		// 載入 JavaScript 和 CSS
		self::enqueue_assets();

		// 產生唯一 ID
		$unique_id = 'buygo-line-binding-' . \uniqid();

		// 開始輸出緩衝
		ob_start();
		?>
		<div class="buygo-line-binding-wrapper">
			<?php if ( $atts['show_title'] === 'yes' ) : ?>
				<h2 class="buygo-line-binding-title"><?php echo \esc_html( $atts['title'] ); ?></h2>
			<?php endif; ?>

			<div id="<?php echo \esc_attr( $unique_id ); ?>"
			     class="buygo-line-binding-widget"
			     data-redirect-url="<?php echo \esc_url( $atts['redirect_url'] ); ?>">
				<!-- Widget 將由 JavaScript 渲染 -->
				<div class="loading">載入中...</div>
			</div>
		</div>

		<?php
		return ob_get_clean();
	}

	/**
	 * 渲染登入提示訊息
	 *
	 * @return string HTML
	 */
	private static function render_login_required(): string {
		ob_start();
		?>
		<div class="buygo-line-binding-wrapper">
			<div class="buygo-line-binding-notice">
				<p>請先 <a href="<?php echo \esc_url( \wp_login_url( \get_permalink() ) ); ?>">登入</a> 才能管理 LINE 綁定。</p>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * 載入 JavaScript 和 CSS
	 */
	private static function enqueue_assets(): void {
		// 註冊並載入 JavaScript
		\wp_enqueue_script(
			'buygo-line-binding-widget',
			\plugin_dir_url( BuygoLineNotify_PLUGIN_FILE ) . 'assets/js/fluentcart-line-integration-standalone.js',
			[],
			BuygoLineNotify_VERSION,
			true
		);

		// 傳遞 API URL 到 JavaScript
		\wp_localize_script(
			'buygo-line-binding-widget',
			'buygoLineFluentCart',
			[
				'apiBase' => \rest_url( 'buygo-line-notify/v1/fluentcart' ),
				'nonce'   => \wp_create_nonce( 'wp_rest' ),
			]
		);

		// 載入樣式
		\wp_register_style(
			'buygo-line-binding-widget',
			false
		);
		\wp_enqueue_style( 'buygo-line-binding-widget' );
		\wp_add_inline_style(
			'buygo-line-binding-widget',
			self::get_inline_css()
		);
	}

	/**
	 * 取得內聯 CSS
	 *
	 * @return string
	 */
	private static function get_inline_css(): string {
		return '
		/* LINE 綁定頁面樣式 */
		.buygo-line-binding-wrapper {
			max-width: 800px;
			margin: 0 auto;
			padding: 20px;
		}

		.buygo-line-binding-title {
			margin: 0 0 24px 0;
			font-size: 24px;
			font-weight: 600;
			color: #333;
		}

		.buygo-line-binding-widget {
			background: #fff;
			border: 1px solid #e0e0e0;
			border-radius: 8px;
			padding: 24px;
		}

		.buygo-line-binding-notice {
			padding: 16px;
			background: #f0f0f0;
			border-left: 4px solid #06C755;
			border-radius: 4px;
		}

		.buygo-line-binding-notice p {
			margin: 0;
			color: #666;
		}

		.buygo-line-binding-notice a {
			color: #06C755;
			text-decoration: none;
			font-weight: 600;
		}

		.buygo-line-binding-notice a:hover {
			text-decoration: underline;
		}

		.line-binding-status h3 {
			margin: 0 0 16px 0;
			font-size: 18px;
			font-weight: 600;
			color: #333;
		}

		.line-profile {
			display: flex;
			align-items: center;
			gap: 16px;
			margin-bottom: 16px;
			padding: 16px;
			background: #f9f9f9;
			border-radius: 8px;
		}

		.line-avatar {
			width: 60px;
			height: 60px;
			border-radius: 50%;
			object-fit: cover;
			border: 2px solid #06C755;
		}

		.line-avatar-placeholder {
			width: 60px;
			height: 60px;
			border-radius: 50%;
			background: #e0e0e0;
			display: flex;
			align-items: center;
			justify-content: center;
			font-size: 24px;
			color: #999;
		}

		.line-info {
			flex: 1;
		}

		.line-name {
			margin: 0 0 4px 0;
			font-size: 16px;
			font-weight: 600;
			color: #333;
		}

		.line-uid {
			margin: 0 0 4px 0;
			font-size: 12px;
			color: #666;
		}

		.line-date {
			margin: 0;
			font-size: 12px;
			color: #999;
		}

		.btn {
			padding: 12px 24px;
			border: none;
			border-radius: 4px;
			cursor: pointer;
			font-size: 14px;
			font-weight: 500;
			transition: all 0.2s;
			display: inline-flex;
			align-items: center;
			gap: 8px;
		}

		.btn-primary {
			background: #06C755;
			color: white;
		}

		.btn-primary:hover {
			background: #05b34a;
		}

		.btn-danger {
			background: #dc3545;
			color: white;
		}

		.btn-danger:hover {
			background: #c82333;
		}

		.btn:disabled {
			opacity: 0.6;
			cursor: not-allowed;
		}

		.loading {
			text-align: center;
			padding: 20px;
			color: #666;
		}

		.error-message {
			padding: 12px;
			background: #fee;
			color: #c00;
			border-radius: 4px;
			margin-bottom: 16px;
		}

		/* 響應式設計 */
		@media (max-width: 768px) {
			.buygo-line-binding-wrapper {
				padding: 16px;
			}

			.line-profile {
				flex-direction: column;
				text-align: center;
			}

			.btn {
				width: 100%;
				justify-content: center;
			}
		}
		';
	}
}
