<?php
/**
 * FluentCart Customer Profile Integration
 *
 * 使用 WordPress hooks 在 FluentCart 客戶檔案頁面中顯示 LINE 綁定狀態
 *
 * @package BuygoLineNotify
 */

namespace BuygoLineNotify\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FluentCartCustomerProfileIntegration
 *
 * 在 FluentCart 客戶檔案頁面注入 LINE 綁定狀態區塊
 */
class FluentCartCustomerProfileIntegration {

	/**
	 * 註冊 hooks
	 */
	public static function register_hooks(): void {
		// FluentCart 客戶檔案頁面 hooks
		// 嘗試多個可能的 hook 點
		\add_action( 'fluent_cart/customer_profile/before_content', [ __CLASS__, 'render_line_binding_section' ], 10 );
		\add_action( 'fluent_cart/customer_profile/after_content', [ __CLASS__, 'render_line_binding_section' ], 10 );
		\add_action( 'fluent_cart_customer_profile_sidebar', [ __CLASS__, 'render_line_binding_section' ], 10 );

		// 載入 JavaScript 和 CSS
		\add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	/**
	 * 渲染 LINE 綁定狀態區塊
	 */
	public static function render_line_binding_section(): void {
		if ( ! \is_user_logged_in() ) {
			return;
		}

		?>
		<div id="buygo-line-binding-widget" class="buygo-line-fluentcart-widget"></div>
		<?php
	}

	/**
	 * 載入 JavaScript 和 CSS
	 */
	public static function enqueue_assets(): void {
		// 只在客戶檔案頁面載入
		if ( ! self::is_customer_profile_page() ) {
			return;
		}

		// 註冊 JavaScript（使用純 JS 版本）
		\wp_enqueue_script(
			'buygo-line-fluentcart-integration',
			BuygoLineNotify_PLUGIN_URL . 'assets/js/fluentcart-line-integration-standalone.js',
			[],
			BuygoLineNotify_PLUGIN_VERSION,
			true
		);

		// 傳遞 API URL 到 JavaScript
		\wp_localize_script(
			'buygo-line-fluentcart-integration',
			'buygoLineFluentCart',
			[
				'apiBase' => \rest_url( 'buygo-line-notify/v1/fluentcart' ),
				'nonce'   => \wp_create_nonce( 'wp_rest' ),
			]
		);

		// 載入樣式（使用 wp_enqueue_style + wp_add_inline_style 更安全）
		\wp_register_style(
			'buygo-line-fluentcart-widget',
			false // 不需要外部 CSS 檔案
		);
		\wp_enqueue_style( 'buygo-line-fluentcart-widget' );
		\wp_add_inline_style(
			'buygo-line-fluentcart-widget',
			self::get_inline_css()
		);
	}

	/**
	 * 檢查是否為客戶檔案頁面
	 *
	 * @return bool
	 */
	private static function is_customer_profile_page(): bool {
		// 檢查是否為 FluentCart 客戶檔案頁面
		// 可能的判斷方式：
		// 1. URL 包含 /my-account/
		// 2. 頁面 slug
		// 3. FluentCart 的條件函數（如果有）

		$current_url = $_SERVER['REQUEST_URI'] ?? '';

		return (
			\is_user_logged_in() &&
			( strpos( $current_url, '/my-account/' ) !== false ||
			  strpos( $current_url, '/customer-profile/' ) !== false )
		);
	}

	/**
	 * 取得內聯 CSS
	 *
	 * @return string
	 */
	private static function get_inline_css(): string {
		return '
		/* FluentCart LINE 綁定區塊樣式 */
		.buygo-line-fluentcart-widget {
			margin: 20px 0;
			padding: 20px;
			background: #fff;
			border: 1px solid #e0e0e0;
			border-radius: 8px;
		}

		.buygo-line-fluentcart-widget .line-binding-status {
			width: 100%;
		}

		.buygo-line-fluentcart-widget h3 {
			margin: 0 0 16px 0;
			font-size: 18px;
			font-weight: 600;
			color: #333;
		}

		.buygo-line-fluentcart-widget .line-profile {
			display: flex;
			align-items: center;
			gap: 16px;
			margin-bottom: 16px;
			padding: 16px;
			background: #f9f9f9;
			border-radius: 8px;
		}

		.buygo-line-fluentcart-widget .line-avatar {
			width: 60px;
			height: 60px;
			border-radius: 50%;
			object-fit: cover;
			border: 2px solid #06C755;
		}

		.buygo-line-fluentcart-widget .line-avatar-placeholder {
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

		.buygo-line-fluentcart-widget .line-info {
			flex: 1;
		}

		.buygo-line-fluentcart-widget .line-name {
			margin: 0 0 4px 0;
			font-size: 16px;
			font-weight: 600;
			color: #333;
		}

		.buygo-line-fluentcart-widget .line-uid {
			margin: 0 0 4px 0;
			font-size: 12px;
			color: #666;
		}

		.buygo-line-fluentcart-widget .line-date {
			margin: 0;
			font-size: 12px;
			color: #999;
		}

		.buygo-line-fluentcart-widget .btn {
			padding: 10px 24px;
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

		.buygo-line-fluentcart-widget .btn-primary {
			background: #06C755;
			color: white;
		}

		.buygo-line-fluentcart-widget .btn-primary:hover {
			background: #05b34a;
		}

		.buygo-line-fluentcart-widget .btn-danger {
			background: #dc3545;
			color: white;
		}

		.buygo-line-fluentcart-widget .btn-danger:hover {
			background: #c82333;
		}

		.buygo-line-fluentcart-widget .btn:disabled {
			opacity: 0.6;
			cursor: not-allowed;
		}

		.buygo-line-fluentcart-widget .loading {
			text-align: center;
			padding: 20px;
			color: #666;
		}

		.buygo-line-fluentcart-widget .error-message {
			padding: 12px;
			background: #fee;
			color: #c00;
			border-radius: 4px;
			margin-bottom: 16px;
		}

		/* 響應式設計 */
		@media (max-width: 768px) {
			.buygo-line-fluentcart-widget .line-profile {
				flex-direction: column;
				text-align: center;
			}

			.buygo-line-fluentcart-widget .btn {
				width: 100%;
				justify-content: center;
			}
		}
		';
	}
}
