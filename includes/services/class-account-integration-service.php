<?php
/**
 * Account Integration Service
 *
 * 在「我的帳號」頁面顯示 LINE 綁定狀態
 * 支援 WooCommerce 和 WordPress 標準頁面
 *
 * @package BuygoLineNotify
 */

namespace BuygoLineNotify\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AccountIntegrationService
 *
 * 提供前台帳號頁面的 LINE 綁定狀態顯示
 */
class AccountIntegrationService {

	/**
	 * 註冊所有前台 hooks
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		$service = new self();

		// WooCommerce 我的帳號頁面
		if ( class_exists( 'WooCommerce' ) ) {
			add_action( 'woocommerce_account_dashboard', array( $service, 'render_line_binding_status' ), 5 );
		}

		// WordPress 個人資料頁面（後台）
		add_action( 'show_user_profile', array( $service, 'render_line_binding_status_admin' ), 5 );
		add_action( 'edit_user_profile', array( $service, 'render_line_binding_status_admin' ), 5 );
	}

	/**
	 * 渲染 LINE 綁定狀態（前台）
	 *
	 * @return void
	 */
	public function render_line_binding_status(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$user_id = get_current_user_id();
		$this->render_binding_widget( $user_id, 'frontend' );
	}

	/**
	 * 渲染 LINE 綁定狀態（後台）
	 *
	 * @param \WP_User $user 用戶物件
	 * @return void
	 */
	public function render_line_binding_status_admin( $user ): void {
		if ( ! $user instanceof \WP_User ) {
			return;
		}

		$this->render_binding_widget( $user->ID, 'admin' );
	}

	/**
	 * 渲染綁定狀態小工具
	 *
	 * @param int    $user_id 用戶 ID
	 * @param string $context 上下文（frontend/admin）
	 * @return void
	 */
	private function render_binding_widget( int $user_id, string $context = 'frontend' ): void {
		// 檢查是否已綁定
		$is_linked = LineUserService::isUserLinked( $user_id );

		// 取得綁定資料
		$binding_data = $this->get_binding_data( $user_id );

		// 產生綁定 URL
		$bind_url = add_query_arg(
			array(
				'loginSocial' => 'buygo-line',
				'redirect_to' => $this->get_current_url(),
			),
			wp_login_url()
		);

		// 渲染 HTML
		?>
		<div class="buygo-line-binding-status <?php echo $is_linked ? 'linked' : 'not-linked'; ?>" data-context="<?php echo esc_attr( $context ); ?>">
			<?php if ( $context === 'admin' ) : ?>
				<h2>LINE 帳號綁定</h2>
			<?php else : ?>
				<h3>LINE 帳號綁定</h3>
			<?php endif; ?>

			<?php if ( $is_linked && $binding_data ) : ?>
				<!-- 已綁定狀態 -->
				<div class="line-profile-display">
					<img src="<?php echo esc_url( $binding_data['avatar_url'] ); ?>"
					     alt="LINE Avatar"
					     class="line-avatar">
					<div class="line-info">
						<p class="line-name"><strong><?php echo esc_html( $binding_data['display_name'] ); ?></strong></p>
						<p class="line-uid">LINE UID: <code><?php echo esc_html( substr( $binding_data['line_uid'], 0, 20 ) . '...' ); ?></code></p>
						<?php if ( ! empty( $binding_data['link_date'] ) ) : ?>
							<p class="line-date">綁定於：<?php echo esc_html( $binding_data['link_date'] ); ?></p>
						<?php endif; ?>
					</div>
				</div>

				<button type="button"
				        class="buygo-line-unbind-button button"
				        data-user-id="<?php echo esc_attr( $user_id ); ?>"
				        data-nonce="<?php echo esc_attr( wp_create_nonce( 'buygo_line_unbind_' . $user_id ) ); ?>">
					解除綁定
				</button>

			<?php else : ?>
				<!-- 未綁定狀態 -->
				<p class="description">綁定 LINE 帳號後，您可以使用 LINE 快速登入。</p>
				<a href="<?php echo esc_url( $bind_url ); ?>"
				   class="buygo-line-bind-button button button-primary">
					綁定 LINE 帳號
				</a>
			<?php endif; ?>
		</div>

		<?php
		// 輸出樣式
		$this->render_styles();

		// 輸出 JavaScript（解除綁定）
		if ( $is_linked ) {
			$this->render_scripts();
		}
	}

	/**
	 * 取得綁定資料
	 *
	 * @param int $user_id 用戶 ID
	 * @return array|null 綁定資料或 null
	 */
	private function get_binding_data( int $user_id ): ?array {
		$line_uid = LineUserService::getLineUidByUserId( $user_id );
		if ( ! $line_uid ) {
			return null;
		}

		// 取得 LINE 頭像
		$avatar_url = get_user_meta( $user_id, 'buygo_line_avatar_url', true );
		if ( empty( $avatar_url ) ) {
			// 使用預設 LINE 頭像
			$avatar_url = 'https://via.placeholder.com/80x80?text=LINE';
		}

		// 取得綁定日期
		global $wpdb;
		$table_name = $wpdb->prefix . 'buygo_line_users';
		$result     = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT link_date FROM {$table_name} WHERE user_id = %d LIMIT 1",
				$user_id
			)
		);

		$link_date = $result && $result->link_date ? $result->link_date : '';

		return array(
			'line_uid'     => $line_uid,
			'avatar_url'   => $avatar_url,
			'display_name' => get_user_meta( $user_id, 'buygo_line_display_name', true ) ?: '未知',
			'link_date'    => $link_date,
		);
	}

	/**
	 * 取得當前頁面 URL
	 *
	 * @return string
	 */
	private function get_current_url(): string {
		$protocol = is_ssl() ? 'https://' : 'http://';
		return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	}

	/**
	 * 渲染樣式
	 *
	 * @return void
	 */
	private function render_styles(): void {
		// 只渲染一次
		static $rendered = false;
		if ( $rendered ) {
			return;
		}
		$rendered = true;

		?>
		<style>
		.buygo-line-binding-status {
			background: #f9f9f9;
			border: 1px solid #ddd;
			border-radius: 8px;
			padding: 20px;
			margin: 20px 0;
		}
		.buygo-line-binding-status h2,
		.buygo-line-binding-status h3 {
			margin-top: 0;
			color: #06C755;
			font-size: 18px;
			font-weight: 600;
		}
		.buygo-line-binding-status .description {
			color: #666;
			margin: 10px 0;
		}
		.line-profile-display {
			display: flex;
			align-items: center;
			gap: 15px;
			margin: 15px 0;
			padding: 15px;
			background: white;
			border-radius: 8px;
		}
		.line-avatar {
			width: 60px;
			height: 60px;
			border-radius: 50%;
			object-fit: cover;
		}
		.line-info {
			flex: 1;
		}
		.line-info p {
			margin: 5px 0;
			font-size: 14px;
		}
		.line-name {
			font-size: 16px !important;
			color: #333;
		}
		.line-uid {
			color: #666;
			font-size: 12px !important;
		}
		.line-uid code {
			background: #f0f0f0;
			padding: 2px 6px;
			border-radius: 3px;
			font-size: 11px;
		}
		.line-date {
			color: #999;
			font-size: 13px !important;
		}
		.buygo-line-bind-button,
		.buygo-line-unbind-button {
			margin-top: 10px;
		}
		.buygo-line-bind-button {
			background: #06C755 !important;
			border-color: #06C755 !important;
			color: white !important;
			text-decoration: none !important;
			display: inline-block;
			padding: 10px 20px;
			border-radius: 4px;
			transition: background 0.2s;
		}
		.buygo-line-bind-button:hover {
			background: #05b34a !important;
		}
		.buygo-line-unbind-button {
			background: #dc3545;
			border-color: #dc3545;
			color: white;
		}
		.buygo-line-unbind-button:hover {
			background: #c82333;
			border-color: #bd2130;
		}
		.buygo-line-unbind-button:disabled {
			opacity: 0.6;
			cursor: not-allowed;
		}
		</style>
		<?php
	}

	/**
	 * 渲染 JavaScript（解除綁定）
	 *
	 * @return void
	 */
	private function render_scripts(): void {
		// 只渲染一次
		static $rendered = false;
		if ( $rendered ) {
			return;
		}
		$rendered = true;

		?>
		<script>
		(function() {
			document.addEventListener('DOMContentLoaded', function() {
				var unbindBtn = document.querySelector('.buygo-line-unbind-button');
				if (!unbindBtn) return;

				unbindBtn.addEventListener('click', function() {
					if (!confirm('確定要解除 LINE 綁定嗎？\n\n解除後您將無法使用 LINE 登入此帳號。')) {
						return;
					}

					var userId = this.getAttribute('data-user-id');
					var nonce = this.getAttribute('data-nonce');

					unbindBtn.disabled = true;
					unbindBtn.textContent = '處理中...';

					// AJAX 解除綁定（下一個 plan 實作）
					fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded',
						},
						body: new URLSearchParams({
							action: 'buygo_line_unbind',
							user_id: userId,
							_ajax_nonce: nonce
						})
					})
					.then(function(response) { return response.json(); })
					.then(function(data) {
						if (data.success) {
							alert('已成功解除 LINE 綁定');
							location.reload();
						} else {
							alert('解除綁定失敗：' + (data.data || '未知錯誤'));
							unbindBtn.disabled = false;
							unbindBtn.textContent = '解除綁定';
						}
					})
					.catch(function(error) {
						alert('發生錯誤：' + error.message);
						unbindBtn.disabled = false;
						unbindBtn.textContent = '解除綁定';
					});
				});
			});
		})();
		</script>
		<?php
	}
}
