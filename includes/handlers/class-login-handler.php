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

		// 檢查是否為表單提交
		if ( isset( $_POST['action'] ) && $_POST['action'] === 'buygo_line_register' ) {
			$this->handle_register_submission();
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

	/**
	 * 處理註冊表單提交
	 *
	 * @return void
	 */
	private function handle_register_submission(): void {
		// 取得 state 用於 Transient 清除（錯誤時也需要清除）
		$state       = isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '';
		$profile_key = self::PROFILE_TRANSIENT_PREFIX . $state;

		// 1. Nonce 驗證
		if ( ! isset( $_POST['buygo_line_register_nonce'] ) ||
		     ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['buygo_line_register_nonce'] ) ), 'buygo_line_register_action' ) ) {
			// 安全驗證失敗不清除 Transient（可能是 CSRF 攻擊）
			wp_die( '安全驗證失敗', 'Error', array( 'response' => 403 ) );
		}

		// 2. 驗證 state 和 Transient
		$data = get_transient( $profile_key );

		if ( $data === false ) {
			Logger::get_instance()->log(
				'error',
				array(
					'message' => 'Registration: Profile transient not found',
					'state'   => $state,
				)
			);
			wp_die( '登入資料已過期，請重新嘗試 LINE 登入', 'Error', array( 'response' => 400 ) );
		}

		$profile    = $data['profile'];
		$state_data = $data['state_data'];

		// 3. 取得表單資料
		$user_login = isset( $_POST['user_login'] ) ? sanitize_user( wp_unslash( $_POST['user_login'] ), true ) : '';
		$user_email = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
		$line_uid   = isset( $_POST['line_uid'] ) ? sanitize_text_field( wp_unslash( $_POST['line_uid'] ) ) : '';

		// 4. 驗證 LINE UID 一致性（防篡改）
		if ( $line_uid !== $profile['userId'] ) {
			Logger::get_instance()->log(
				'error',
				array(
					'message'       => 'Registration: LINE UID mismatch',
					'form_line_uid' => $line_uid,
					'profile_uid'   => $profile['userId'],
				)
			);
			// LINE UID 不一致，清除 Transient 防止重複嘗試
			delete_transient( $profile_key );
			wp_die( 'LINE 帳號資訊不一致', 'Error', array( 'response' => 400 ) );
		}

		// 5. 驗證用戶名和 Email
		if ( empty( $user_login ) ) {
			// 不清除 Transient，允許用戶修正後重試
			wp_die( '請填寫用戶名', 'Error', array( 'response' => 400, 'back_link' => true ) );
		}

		if ( empty( $user_email ) || ! is_email( $user_email ) ) {
			// 不清除 Transient，允許用戶修正後重試
			wp_die( '請輸入有效的 Email 地址', 'Error', array( 'response' => 400, 'back_link' => true ) );
		}

		// 6. 檢查 LINE UID 是否已綁定其他用戶
		$existing_line_user = LineUserService::getUserByLineUid( $line_uid );
		if ( $existing_line_user ) {
			Logger::get_instance()->log(
				'error',
				array(
					'message'  => 'Registration: LINE UID already linked',
					'line_uid' => $line_uid,
					'user_id'  => $existing_line_user,
				)
			);
			// LINE 已綁定，清除 Transient
			delete_transient( $profile_key );
			wp_die( '此 LINE 帳號已綁定其他用戶', 'Error', array( 'response' => 400 ) );
		}

		// 7. 檢查 Email 是否已存在（Auto-link）
		$existing_user_id = email_exists( $user_email );
		if ( $existing_user_id ) {
			$this->handle_auto_link( $existing_user_id, $line_uid, $profile, $state_data, $profile_key );
			return;
		}

		// 8. 檢查用戶名是否已存在（加數字後綴）
		$original_login = $user_login;
		$counter        = 1;
		while ( username_exists( $user_login ) ) {
			$user_login = $original_login . $counter;
			$counter++;
		}

		// 9. 建立用戶
		$user_id = wp_insert_user(
			array(
				'user_login'   => $user_login,
				'user_email'   => $user_email,
				'user_pass'    => wp_generate_password( 16, false ),
				'display_name' => $profile['displayName'] ?? $user_login,
				'role'         => 'subscriber',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			Logger::get_instance()->log(
				'error',
				array(
					'message'       => 'Registration: wp_insert_user failed',
					'error_code'    => $user_id->get_error_code(),
					'error_message' => $user_id->get_error_message(),
				)
			);
			// 用戶建立失敗，清除 Transient 防止數據不一致
			delete_transient( $profile_key );
			wp_die( '用戶建立失敗: ' . esc_html( $user_id->get_error_message() ), 'Error', array( 'response' => 500 ) );
		}

		// 10. 綁定 LINE（is_registration = true）
		$link_result = LineUserService::linkUser( $user_id, $line_uid, true );
		if ( ! $link_result ) {
			// 理論上不應該發生（前面已檢查），但保險起見
			Logger::get_instance()->log(
				'error',
				array(
					'message'  => 'Registration: linkUser failed after user creation',
					'user_id'  => $user_id,
					'line_uid' => $line_uid,
				)
			);
			// 用戶已建立，繼續流程（LINE 綁定問題可稍後處理）
		}

		// 11. 儲存 LINE 頭像 URL 到 user_meta
		if ( ! empty( $profile['pictureUrl'] ) ) {
			update_user_meta( $user_id, 'buygo_line_avatar_url', $profile['pictureUrl'] );
		}

		Logger::get_instance()->log(
			'info',
			array(
				'message'  => 'User registered via LINE',
				'user_id'  => $user_id,
				'line_uid' => $line_uid,
				'email'    => $user_email,
			)
		);

		// 12. 清除 Transient（成功）
		delete_transient( $profile_key );

		// 13. 自動登入
		wp_set_auth_cookie( $user_id, true );

		// 14. 觸發 hook（供其他外掛使用）
		do_action( 'buygo_line_after_register', $user_id, $line_uid, $profile );

		// 15. 導向
		$redirect_to = $state_data['redirect_url'] ?? home_url();
		$redirect_to = apply_filters( 'login_redirect', $redirect_to, '', get_user_by( 'id', $user_id ) );

		wp_safe_redirect( $redirect_to );
		exit;
	}

	/**
	 * 處理 Auto-link（Email 已存在時綁定現有帳號）
	 *
	 * @param int    $user_id WordPress User ID
	 * @param string $line_uid LINE UID
	 * @param array  $profile LINE profile
	 * @param array  $state_data State 資料
	 * @param string $profile_key Transient key
	 * @return void
	 */
	private function handle_auto_link(
		int $user_id,
		string $line_uid,
		array $profile,
		array $state_data,
		string $profile_key
	): void {
		Logger::get_instance()->log(
			'info',
			array(
				'message'  => 'Auto-link: Email exists, linking to existing user',
				'user_id'  => $user_id,
				'line_uid' => $line_uid,
				'email'    => $profile['email'] ?? 'N/A',
			)
		);

		// 檢查該用戶是否已綁定其他 LINE 帳號
		$existing_line_uid = LineUserService::getLineUidByUserId( $user_id );
		if ( $existing_line_uid ) {
			Logger::get_instance()->log(
				'error',
				array(
					'message'           => 'Auto-link: User already linked to another LINE',
					'user_id'           => $user_id,
					'existing_line_uid' => $existing_line_uid,
					'new_line_uid'      => $line_uid,
				)
			);
			// 用戶已綁定其他 LINE，清除 Transient
			delete_transient( $profile_key );
			wp_die( '此 Email 的帳號已綁定其他 LINE 帳號', 'Error', array( 'response' => 400 ) );
		}

		// 綁定 LINE（is_registration = false，因為是 auto-link 不是新註冊）
		$link_result = LineUserService::linkUser( $user_id, $line_uid, false );
		if ( ! $link_result ) {
			Logger::get_instance()->log(
				'error',
				array(
					'message'  => 'Auto-link: linkUser failed',
					'user_id'  => $user_id,
					'line_uid' => $line_uid,
				)
			);
			// 綁定失敗，清除 Transient
			delete_transient( $profile_key );
			wp_die( 'LINE 帳號綁定失敗', 'Error', array( 'response' => 500 ) );
		}

		// 儲存 LINE 頭像 URL
		if ( ! empty( $profile['pictureUrl'] ) ) {
			update_user_meta( $user_id, 'buygo_line_avatar_url', $profile['pictureUrl'] );
		}

		Logger::get_instance()->log(
			'info',
			array(
				'message'  => 'Auto-link completed',
				'user_id'  => $user_id,
				'line_uid' => $line_uid,
			)
		);

		// 清除 Transient（成功）
		delete_transient( $profile_key );

		// 自動登入
		wp_set_auth_cookie( $user_id, true );

		// 觸發 hook
		do_action( 'buygo_line_after_link', $user_id, $line_uid, $profile );

		// 顯示訊息並導向
		// 使用 WordPress admin notice（透過 transient 傳遞）
		set_transient(
			'buygo_line_notice_' . $user_id,
			array(
				'type'    => 'success',
				'message' => '已將 LINE 帳號綁定到您的現有帳號',
			),
			60
		);

		// 導向
		$redirect_to = $state_data['redirect_url'] ?? home_url();
		$redirect_to = apply_filters( 'login_redirect', $redirect_to, '', get_user_by( 'id', $user_id ) );

		wp_safe_redirect( $redirect_to );
		exit;
	}
}
