<?php
/**
 * Register Flow Shortcode
 *
 * 渲染 LINE 註冊流程表單
 * 從 Transient 讀取 LINE profile 並顯示註冊表單
 *
 * @package BuygoLineNotify
 */

namespace BuygoLineNotify\Shortcodes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RegisterFlowShortcode
 *
 * 處理 [buygo_line_register_flow] shortcode
 * 顯示新用戶註冊表單（包含 LINE profile 資訊）
 */
class RegisterFlowShortcode {

	/**
	 * Transient 前綴（與 Login_Handler 一致）
	 */
	const TRANSIENT_PREFIX = 'buygo_line_profile_';

	/**
	 * 渲染 shortcode
	 *
	 * @param array      $atts Shortcode 屬性
	 * @param array|null $exception_data NSLContinuePageRenderException 資料（動態註冊時傳入）
	 * @return string HTML 輸出
	 */
	public function render( $atts, $exception_data = null ): string {
		// 1. 取得 state 參數（來源：URL 參數或動態註冊時的 exception_data）
		$state = '';

		if ( $exception_data && isset( $exception_data['state'] ) ) {
			// 動態註冊時由 Login_Handler 傳入
			$state = $exception_data['state'];
		} elseif ( isset( $_GET['state'] ) ) {
			// 從 URL 參數取得（重定向到 Register Flow Page 時）
			$state = sanitize_text_field( wp_unslash( $_GET['state'] ) );
		}

		// 2. 驗證 state 參數
		if ( empty( $state ) ) {
			return $this->render_error( '請透過 LINE 登入流程訪問此頁面。' );
		}

		// 3. 從 Transient 讀取 LINE profile
		$profile_key  = self::TRANSIENT_PREFIX . $state;
		$profile_data = get_transient( $profile_key );

		if ( false === $profile_data ) {
			return $this->render_error( '登入資料已過期，請重新嘗試 LINE 登入。' );
		}

		// 4. 取出 profile 和 state_data
		$profile    = $profile_data['profile'] ?? array();
		$state_data = $profile_data['state_data'] ?? array();

		// 5. 渲染表單
		return $this->render_form( $profile, $state );
	}

	/**
	 * 渲染錯誤訊息
	 *
	 * @param string $message 錯誤訊息
	 * @return string HTML 輸出
	 */
	private function render_error( string $message ): string {
		ob_start();
		?>
		<div class="buygo-line-error">
			<p><?php echo esc_html( $message ); ?></p>
			<p>
				<a href="<?php echo esc_url( wp_login_url() ); ?>" class="button">返回登入頁面</a>
			</p>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * 渲染註冊表單
	 *
	 * @param array  $profile LINE profile 資料
	 * @param string $state OAuth state
	 * @return string HTML 輸出
	 */
	private function render_form( array $profile, string $state ): string {
		// 取得 LINE profile 欄位
		$line_uid      = $profile['userId'] ?? '';
		$display_name  = $profile['displayName'] ?? '';
		$email         = $profile['email'] ?? '';
		$picture_url   = $profile['pictureUrl'] ?? '';
		$status_message = $profile['statusMessage'] ?? '';

		ob_start();
		?>
		<div class="buygo-line-register-form">
			<!-- LINE Profile 區域 -->
			<div class="buygo-line-register-form__profile">
				<?php if ( ! empty( $picture_url ) ) : ?>
					<img src="<?php echo esc_url( $picture_url ); ?>"
					     alt="<?php echo esc_attr( $display_name ); ?>"
					     class="buygo-line-register-form__avatar">
				<?php endif; ?>
				<div class="buygo-line-register-form__info">
					<span class="buygo-line-register-form__name">
						<?php echo esc_html( $display_name ); ?>
					</span>
					<?php if ( ! empty( $status_message ) ) : ?>
						<span class="buygo-line-register-form__status">
							<?php echo esc_html( $status_message ); ?>
						</span>
					<?php endif; ?>
				</div>
			</div>

			<!-- 註冊表單 -->
			<form method="post"
			      action="<?php echo esc_url( site_url( 'wp-login.php?loginSocial=buygo-line' ) ); ?>"
			      class="buygo-line-register-form__form">

				<?php wp_nonce_field( 'buygo_line_register_action', 'buygo_line_register_nonce' ); ?>

				<!-- 隱藏欄位 -->
				<input type="hidden" name="action" value="buygo_line_register">
				<input type="hidden" name="state" value="<?php echo esc_attr( $state ); ?>">
				<input type="hidden" name="line_uid" value="<?php echo esc_attr( $line_uid ); ?>">

				<!-- 用戶名 -->
				<div class="buygo-line-register-form__field">
					<label for="user_login">用戶名 *</label>
					<input type="text"
					       name="user_login"
					       id="user_login"
					       value="<?php echo esc_attr( $display_name ); ?>"
					       required
					       class="buygo-line-register-form__input">
					<small class="buygo-line-register-form__hint">
						此用戶名將作為您的登入帳號
					</small>
				</div>

				<!-- Email -->
				<div class="buygo-line-register-form__field">
					<label for="user_email">Email *</label>
					<input type="email"
					       name="user_email"
					       id="user_email"
					       value="<?php echo esc_attr( $email ); ?>"
					       required
					       class="buygo-line-register-form__input">
					<small class="buygo-line-register-form__hint">
						用於接收通知和密碼重設
					</small>
				</div>

				<!-- 提交按鈕 -->
				<div class="buygo-line-register-form__actions">
					<button type="submit" class="buygo-line-register-form__submit button button-primary">
						完成註冊
					</button>
					<a href="<?php echo esc_url( home_url() ); ?>"
					   class="buygo-line-register-form__cancel button button-secondary">
						取消
					</a>
				</div>

				<!-- 說明文字 -->
				<div class="buygo-line-register-form__notice">
					<p>
						完成註冊後，您的 LINE 帳號將自動綁定到此 WordPress 帳號，
						未來可直接使用 LINE 登入。
					</p>
				</div>
			</form>
		</div>

		<style>
			/* 基礎樣式（使用 BEM 命名） */
			.buygo-line-register-form {
				max-width: 500px;
				margin: 0 auto;
				padding: 20px;
			}

			.buygo-line-register-form__profile {
				display: flex;
				align-items: center;
				gap: 15px;
				padding: 20px;
				margin-bottom: 30px;
				background: #f5f5f5;
				border-radius: 8px;
			}

			.buygo-line-register-form__avatar {
				width: 80px;
				height: 80px;
				border-radius: 50%;
				object-fit: cover;
			}

			.buygo-line-register-form__info {
				flex: 1;
				display: flex;
				flex-direction: column;
				gap: 5px;
			}

			.buygo-line-register-form__name {
				font-size: 18px;
				font-weight: 600;
				color: #333;
			}

			.buygo-line-register-form__status {
				font-size: 14px;
				color: #666;
			}

			.buygo-line-register-form__field {
				margin-bottom: 20px;
			}

			.buygo-line-register-form__field label {
				display: block;
				margin-bottom: 8px;
				font-weight: 500;
				color: #333;
			}

			.buygo-line-register-form__input {
				width: 100%;
				padding: 10px;
				border: 1px solid #ddd;
				border-radius: 4px;
				font-size: 16px;
			}

			.buygo-line-register-form__input:focus {
				outline: none;
				border-color: #06c755;
				box-shadow: 0 0 0 2px rgba(6, 199, 85, 0.1);
			}

			.buygo-line-register-form__hint {
				display: block;
				margin-top: 5px;
				font-size: 13px;
				color: #666;
			}

			.buygo-line-register-form__actions {
				display: flex;
				gap: 10px;
				margin-top: 30px;
			}

			.buygo-line-register-form__submit {
				flex: 1;
				padding: 12px 24px;
				font-size: 16px;
			}

			.buygo-line-register-form__cancel {
				padding: 12px 24px;
				font-size: 16px;
			}

			.buygo-line-register-form__notice {
				margin-top: 20px;
				padding: 15px;
				background: #e7f3ff;
				border-left: 4px solid #2196f3;
				border-radius: 4px;
			}

			.buygo-line-register-form__notice p {
				margin: 0;
				font-size: 14px;
				color: #555;
				line-height: 1.6;
			}

			.buygo-line-error {
				max-width: 500px;
				margin: 40px auto;
				padding: 20px;
				text-align: center;
				background: #fff3cd;
				border: 1px solid #ffc107;
				border-radius: 8px;
			}

			.buygo-line-error p {
				margin: 0 0 15px 0;
				color: #856404;
			}

			.buygo-line-error p:last-child {
				margin: 0;
			}
		</style>
		<?php
		return ob_get_clean();
	}
}
