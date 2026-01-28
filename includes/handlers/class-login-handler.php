<?php
/**
 * Login Handler
 *
 * 處理 login_init hook,攔截 LINE Login 流程
 * 使用標準 WordPress URL 機制（wp-login.php?loginSocial=buygo-line）
 * 對齊 Nextend Social Login 架構
 *
 * @package BuygoLineNotify
 */

namespace BuygoLineNotify\Handlers;

use BuygoLineNotify\Services\LoginService;
use BuygoLineNotify\Services\LineUserService;
use BuygoLineNotify\Services\StateManager;
use BuygoLineNotify\Services\Logger;
use BuygoLineNotify\Exceptions\NSLContinuePageRenderException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Login_Handler
 *
 * 處理 LINE Login OAuth 流程：
 * 1. 初始授權請求：wp-login.php?loginSocial=buygo-line
 * 2. OAuth callback：wp-login.php?loginSocial=buygo-line&code=xxx&state=xxx
 *
 * 流程：
 * - Authorize: 產生 state → 儲存到 StateManager → 導向 LINE
 * - Callback: 驗證 state → exchange token → 取得 profile → 登入/註冊/綁定
 */
class Login_Handler {

	/**
	 * Transient 前綴（儲存 LINE profile）
	 */
	const PROFILE_TRANSIENT_PREFIX = 'buygo_line_profile_';

	/**
	 * Transient 有效期（10 分鐘）
	 */
	const PROFILE_TRANSIENT_EXPIRY = 600;

	/**
	 * Login Service 實例
	 *
	 * @var LoginService
	 */
	private $login_service;

	/**
	 * State Manager 實例
	 *
	 * @var StateManager
	 */
	private $state_manager;

	/**
	 * 建構子
	 */
	public function __construct() {
		$this->login_service = new LoginService();
		$this->state_manager = new StateManager();
	}

	/**
	 * 註冊 hooks
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		$handler = new self();
		add_action( 'login_init', array( $handler, 'handle_login_init' ) );
	}

	/**
	 * 處理 login_init hook
	 *
	 * 攔截 LINE Login 流程（wp-login.php?loginSocial=buygo-line）
	 *
	 * @return void
	 */
	public function handle_login_init(): void {
		// 檢查是否為 LINE Login 請求
		if ( ! isset( $_GET['loginSocial'] ) || $_GET['loginSocial'] !== 'buygo-line' ) {
			return;
		}

		try {
			// 判斷是 authorize 還是 callback
			if ( isset( $_GET['code'] ) && isset( $_GET['state'] ) ) {
				// OAuth callback
				$this->handle_callback(
					sanitize_text_field( wp_unslash( $_GET['code'] ) ),
					sanitize_text_field( wp_unslash( $_GET['state'] ) )
				);
			} else {
				// 初始授權請求
				$this->handle_authorize();
			}
		} catch ( NSLContinuePageRenderException $e ) {
			// 不是錯誤,讓頁面繼續渲染
			$flow_type = $e->getFlowType();
			$data      = $e->getData();
			$state     = $data['state'] ?? '';

			Logger::get_instance()->log(
				'info',
				array(
					'message'   => 'NSLContinuePageRenderException caught',
					'flow_type' => $flow_type,
					'state'     => $state,
				)
			);

			// 根據流程類型處理
			switch ( $flow_type ) {
				case NSLContinuePageRenderException::FLOW_REGISTER:
					// 新用戶需要顯示註冊表單
					$register_page_id = get_option( 'buygo_line_register_flow_page', 0 );

					if ( $register_page_id && get_post_status( $register_page_id ) === 'publish' ) {
						// 有設定頁面：重定向到該頁面（URL 帶 state 參數）
						$register_url = add_query_arg( 'state', $state, get_permalink( $register_page_id ) );
						wp_redirect( $register_url );
						exit;
					}

					// 沒有設定頁面：渲染 fallback 表單
					$this->render_fallback_registration_form( $data );
					exit;

				case NSLContinuePageRenderException::FLOW_LINK:
					// 帳號連結流程：用戶已登入，需要確認連結 LINE
					$link_page_id = get_option( 'buygo_line_link_flow_page', 0 );

					if ( $link_page_id && get_post_status( $link_page_id ) === 'publish' ) {
						$link_url = add_query_arg( 'state', $state, get_permalink( $link_page_id ) );
						wp_redirect( $link_url );
						exit;
					}

					// Fallback: 直接在 wp-login.php 顯示連結確認
					$this->render_fallback_link_confirmation( $data );
					exit;

				default:
					// 未知流程類型，記錄警告並讓頁面繼續
					Logger::get_instance()->log(
						'warning',
						array(
							'message'   => 'Unknown flow type in NSLContinuePageRenderException',
							'flow_type' => $flow_type,
						)
					);
					return;
			}
		} catch ( \Exception $e ) {
			// 其他錯誤
			Logger::get_instance()->log(
				'error',
				array(
					'message' => 'LINE Login error',
					'error'   => $e->getMessage(),
					'trace'   => $e->getTraceAsString(),
				)
			);
			wp_die(
				'LINE 登入失敗: ' . esc_html( $e->getMessage() ),
				'LINE Login Error',
				array( 'response' => 400 )
			);
		}
	}

	/**
	 * 處理初始授權請求
	 *
	 * 產生 state → 儲存 → 導向 LINE
	 *
	 * @return void
	 */
	private function handle_authorize(): void {
		// 取得 redirect_to 參數（授權完成後的導向 URL）
		$redirect_to = isset( $_GET['redirect_to'] ) ? esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ) : home_url();

		Logger::get_instance()->log(
			'info',
			array(
				'message'      => 'LINE Login authorize started',
				'redirect_url' => $redirect_to,
			)
		);

		// 取得授權 URL（使用 LoginService）
		// LoginService->get_authorize_url() 內部會產生並儲存 state
		$authorize_url = $this->login_service->get_authorize_url( $redirect_to );

		// 導向 LINE
		wp_redirect( $authorize_url );
		exit;
	}

	/**
	 * 處理 OAuth callback
	 *
	 * 驗證 state → exchange token → 取得 profile → 登入/註冊/綁定
	 *
	 * @param string $code LINE 授權碼
	 * @param string $state State 參數
	 * @return void
	 */
	private function handle_callback( string $code, string $state ): void {
		// 1. 驗證 state（StateManager 整合）
		$state_data = $this->state_manager->verify_state( $state );
		if ( $state_data === false ) {
			Logger::get_instance()->log(
				'error',
				array(
					'message' => 'State verification failed',
					'state'   => $state,
				)
			);
			wp_die(
				'State 驗證失敗，請重新嘗試登入',
				'State Error',
				array( 'response' => 400 )
			);
		}

		// 2. Exchange token 並取得 profile（使用 LoginService）
		$result = $this->login_service->handle_callback( $code, $state );
		if ( is_wp_error( $result ) ) {
			Logger::get_instance()->log(
				'error',
				array(
					'message'       => 'LINE Login callback failed',
					'error_code'    => $result->get_error_code(),
					'error_message' => $result->get_error_message(),
				)
			);
			wp_die(
				'LINE 登入失敗: ' . esc_html( $result->get_error_message() ),
				'LINE Login Error',
				array( 'response' => 400 )
			);
		}

		$profile    = $result['profile'];
		$state_data = $result['state_data'];
		$line_uid   = $profile['userId'];

		Logger::get_instance()->log(
			'info',
			array(
				'message'  => 'LINE Login callback successful',
				'line_uid' => $line_uid,
				'state'    => $state,
			)
		);

		// 3. 查詢是否已綁定用戶
		$user_id = LineUserService::getUserByLineUid( $line_uid );

		if ( $user_id ) {
			// 已綁定用戶，執行登入
			$this->perform_login( $user_id, $state_data );
		} else {
			// 未綁定用戶，需要註冊流程

			// 1. 產生 profile transient key（使用原始 state）
			$profile_key = self::PROFILE_TRANSIENT_PREFIX . $state;

			// 2. 儲存 LINE profile 到 Transient（供 shortcode 使用）
			set_transient(
				$profile_key,
				array(
					'profile'    => $profile,
					'state_data' => $state_data,
					'state'      => $state,
					'timestamp'  => time(),
				),
				self::PROFILE_TRANSIENT_EXPIRY
			);

			Logger::get_instance()->log(
				'info',
				array(
					'message'     => 'LINE profile stored for registration',
					'profile_key' => $profile_key,
					'line_uid'    => $line_uid,
				)
			);

			// 3. 動態註冊 shortcode
			$this->register_shortcode_dynamically( $state );

			// 4. 拋出例外（讓頁面繼續渲染）
			throw new NSLContinuePageRenderException(
				NSLContinuePageRenderException::FLOW_REGISTER,
				array(
					'profile'     => $profile,
					'state_data'  => $state_data,
					'state'       => $state,
					'profile_key' => $profile_key,
				)
			);
		}

		// 4. 消費 state（防重放攻擊）
		// 注意：LoginService->handle_callback() 已經內部消費 state
		// 這裡註解掉避免重複消費
		// $this->state_manager->consume_state( $state );
	}

	/**
	 * 執行登入
	 *
	 * @param int   $user_id WordPress User ID
	 * @param array $state_data State 資料
	 * @return void
	 */
	private function perform_login( int $user_id, array $state_data ): void {
		// 設定 auth cookie
		wp_set_auth_cookie( $user_id, true );

		Logger::get_instance()->log(
			'info',
			array(
				'message' => 'User logged in via LINE',
				'user_id' => $user_id,
			)
		);

		// 取得導向 URL（使用 WordPress login_redirect filter）
		$redirect_to = $state_data['redirect_url'] ?? home_url();
		$redirect_to = apply_filters( 'login_redirect', $redirect_to, '', get_user_by( 'id', $user_id ) );

		// 導向
		wp_redirect( $redirect_to );
		exit;
	}

	/**
	 * 動態註冊 shortcode
	 *
	 * @param string $state OAuth state
	 * @return void
	 */
	private function register_shortcode_dynamically( string $state ): void {
		// 只在 shortcode 尚未註冊時註冊
		if ( ! shortcode_exists( 'buygo_line_register_flow' ) ) {
			add_shortcode(
				'buygo_line_register_flow',
				function ( $atts ) use ( $state ) {
					// 載入 shortcode 類別（如果尚未載入）
					if ( ! class_exists( 'BuygoLineNotify\Shortcodes\RegisterFlowShortcode' ) ) {
						require_once BuygoLineNotify_PLUGIN_DIR . 'includes/shortcodes/class-register-flow-shortcode.php';
					}
					$shortcode = new \BuygoLineNotify\Shortcodes\RegisterFlowShortcode();
					return $shortcode->render( $atts, array( 'state' => $state ) );
				}
			);
		}
	}

	/**
	 * 渲染 fallback 註冊表單（在 wp-login.php 上）
	 *
	 * @param array $data Exception data
	 * @return void
	 */
	private function render_fallback_registration_form( array $data ): void {
		$profile = $data['profile'];
		$state   = $data['state'];

		// 使用 WordPress login 樣式輸出簡化版表單
		login_header( 'LINE 註冊', '', new \WP_Error() );
		?>
		<div id="buygo-line-register-fallback">
			<h2>完成 LINE 註冊</h2>
			<div class="line-profile" style="text-align: center; margin-bottom: 20px;">
				<?php if ( ! empty( $profile['pictureUrl'] ) ) : ?>
					<img src="<?php echo esc_url( $profile['pictureUrl'] ); ?>"
					     alt="LINE Avatar"
					     style="width: 80px; height: 80px; border-radius: 50%;">
				<?php endif; ?>
				<p><strong><?php echo esc_html( $profile['displayName'] ?? '' ); ?></strong></p>
			</div>
			<form method="post" action="<?php echo esc_url( site_url( 'wp-login.php?loginSocial=buygo-line' ) ); ?>">
				<?php wp_nonce_field( 'buygo_line_register_action', 'buygo_line_register_nonce' ); ?>
				<input type="hidden" name="action" value="buygo_line_register">
				<input type="hidden" name="state" value="<?php echo esc_attr( $state ); ?>">
				<input type="hidden" name="line_uid" value="<?php echo esc_attr( $profile['userId'] ); ?>">
				<p>
					<label for="user_login">用戶名</label>
					<input type="text" name="user_login" id="user_login"
					       class="input" size="20"
					       value="<?php echo esc_attr( $profile['displayName'] ?? '' ); ?>" required>
				</p>
				<p>
					<label for="user_email">Email</label>
					<input type="email" name="user_email" id="user_email"
					       class="input" size="20"
					       value="<?php echo esc_attr( $profile['email'] ?? '' ); ?>" required>
				</p>
				<p class="submit">
					<input type="submit" name="wp-submit" id="wp-submit"
					       class="button button-primary button-large" value="完成註冊">
				</p>
			</form>
		</div>
		<?php
		login_footer();
	}

	/**
	 * 渲染 fallback 帳號連結確認（在 wp-login.php 上）
	 *
	 * @param array $data Exception data
	 * @return void
	 */
	private function render_fallback_link_confirmation( array $data ): void {
		$profile = $data['profile'];
		$state   = $data['state'];
		$user_id = $data['user_id'] ?? 0;

		login_header( '連結 LINE 帳號', '', new \WP_Error() );
		?>
		<div id="buygo-line-link-fallback">
			<h2>連結 LINE 帳號</h2>
			<div class="line-profile" style="text-align: center; margin-bottom: 20px;">
				<?php if ( ! empty( $profile['pictureUrl'] ) ) : ?>
					<img src="<?php echo esc_url( $profile['pictureUrl'] ); ?>"
					     alt="LINE Avatar"
					     style="width: 80px; height: 80px; border-radius: 50%;">
				<?php endif; ?>
				<p><strong><?php echo esc_html( $profile['displayName'] ?? '' ); ?></strong></p>
			</div>
			<p style="text-align: center;">確定要將此 LINE 帳號連結到您的 WordPress 帳號嗎？</p>
			<form method="post" action="<?php echo esc_url( site_url( 'wp-login.php?loginSocial=buygo-line' ) ); ?>">
				<?php wp_nonce_field( 'buygo_line_link_action', 'buygo_line_link_nonce' ); ?>
				<input type="hidden" name="action" value="buygo_line_link">
				<input type="hidden" name="state" value="<?php echo esc_attr( $state ); ?>">
				<input type="hidden" name="line_uid" value="<?php echo esc_attr( $profile['userId'] ); ?>">
				<input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>">
				<p class="submit" style="text-align: center;">
					<input type="submit" name="wp-submit" id="wp-submit"
					       class="button button-primary button-large" value="確認連結">
					<a href="<?php echo esc_url( home_url() ); ?>" class="button button-secondary">取消</a>
				</p>
			</form>
		</div>
		<?php
		login_footer();
	}
}
