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
			// 不是錯誤,讓頁面繼續渲染（Phase 10 處理）
			// 例如：新用戶需要顯示註冊表單
			return;
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
			// 未綁定用戶，拋出例外讓頁面繼續渲染（Phase 10 處理註冊表單）
			Logger::get_instance()->log(
				'info',
				array(
					'message'  => 'New LINE user, need registration',
					'line_uid' => $line_uid,
				)
			);

			throw new NSLContinuePageRenderException(
				NSLContinuePageRenderException::FLOW_REGISTER,
				array(
					'profile'    => $profile,
					'state_data' => $state_data,
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
}
