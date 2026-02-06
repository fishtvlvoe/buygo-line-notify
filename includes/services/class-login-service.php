<?php
/**
 * Login Service
 *
 * 處理 LINE Login OAuth 2.0 完整流程
 * - 產生 authorize URL
 * - 處理 callback（驗證 state + code）
 * - Exchange code for access token
 * - 取得 LINE user profile
 *
 * @package BuygoLineNotify
 */

namespace BuygoLineNotify\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class LoginService
 *
 * 負責 LINE Login OAuth 2.0 授權流程：
 * - 產生 authorize URL（含 state 和 bot_prompt）
 * - 驗證 callback（state 驗證）
 * - Token exchange（code → access_token）
 * - Profile 取得（access_token → user profile）
 */
class LoginService {

	/**
	 * State Manager 實例
	 *
	 * @var StateManager
	 */
	private $state_manager;

	/**
	 * Settings Service 實例
	 *
	 * @var SettingsService
	 */
	private $settings;

	/**
	 * LINE Login API Base URL
	 */
	const LINE_OAUTH_BASE_URL = 'https://access.line.me/oauth2/v2.1';
	const LINE_API_BASE_URL   = 'https://api.line.me';

	/**
	 * 建構子
	 */
	public function __construct() {
		$this->state_manager = new StateManager();
	}

	/**
	 * 產生 LINE Login authorize URL
	 *
	 * 優先順序：
	 * 1. 使用 NSL (Nextend Social Login) 如果已啟用
	 * 2. 使用 buygo-line-notify 自己的 LINE Login
	 *
	 * @param string|null $redirect_url 授權完成後的導向 URL（null 表示使用後台設定）
	 * @param int|null    $user_id WordPress 使用者 ID（可選）
	 * @return string LINE authorize URL
	 */
	public function get_authorize_url( ?string $redirect_url = null, ?int $user_id = null ): string {
		// 若未指定 redirect_url，使用後台設定的預設值
		if ( empty( $redirect_url ) ) {
			$default_redirect = SettingsService::get( 'default_redirect_url', '' );
			$redirect_url = ! empty( $default_redirect ) ? $default_redirect : home_url( '/my-account/' );
		}

		// 檢查是否使用 NSL（優先使用 NSL）
		if ( $this->should_use_nsl() ) {
			Logger::log_placeholder(
				'info',
				array(
					'message'      => 'Using NSL for LINE Login',
					'redirect_url' => $redirect_url,
					'user_id'      => $user_id,
				)
			);
			return $this->get_nsl_authorize_url( $redirect_url );
		}

		// 產生並儲存 state
		$state = $this->state_manager->generate_state();
		$stored = $this->state_manager->store_state(
			$state,
			array(
				'redirect_url' => $redirect_url,
				'user_id'      => $user_id,
			)
		);

		// Debug: 記錄 state 儲存結果
		Logger::log_placeholder(
			'info',
			array(
				'message' => 'State storage result',
				'state'   => $state,
				'stored'  => $stored ? 'success' : 'failed',
			)
		);

		// 取得 Channel ID
		$channel_id = SettingsService::get( 'login_channel_id', '' );

		// 取得 Callback URL（標準 WordPress URL）
		$callback_url = site_url( 'wp-login.php?loginSocial=buygo-line' );

		// 建立 authorize URL
		$params = array(
			'response_type' => 'code',
			'client_id'     => $channel_id,
			'redirect_uri'  => $callback_url,
			'state'         => $state,
			'scope'         => 'profile openid email',
			'bot_prompt'    => 'aggressive', // 強制引導加入官方帳號
		);

		$authorize_url = self::LINE_OAUTH_BASE_URL . '/authorize?' . http_build_query( $params );

		Logger::log_placeholder(
			'info',
			array(
				'message'      => 'LINE Login authorize URL generated',
				'state'        => $state,
				'redirect_url' => $redirect_url,
				'user_id'      => $user_id,
			)
		);

		return $authorize_url;
	}

	/**
	 * 處理 LINE Login callback
	 *
	 * 驗證 state → exchange token → 取得 profile
	 *
	 * @param string $code LINE 授權碼
	 * @param string $state State 參數
	 * @return array|\WP_Error 成功時返回 ['profile' => array, 'state_data' => array]，失敗時返回 WP_Error
	 */
	public function handle_callback( string $code, string $state ) {
		// 1. 驗證 state
		$state_data = $this->state_manager->verify_state( $state );
		if ( $state_data === false ) {
			Logger::log_placeholder( 'error', array( 'message' => 'Invalid or expired state', 'state' => $state ) );
			return new \WP_Error( 'invalid_state', 'Invalid or expired state parameter' );
		}

		// 消費 state（一次性使用）
		$this->state_manager->consume_state( $state );

		// 2. Exchange code for token
		$token_result = $this->exchange_token( $code );
		if ( is_wp_error( $token_result ) ) {
			return $token_result;
		}

		$access_token = $token_result['access_token'];

		// 3. 取得 profile
		$profile = $this->get_profile( $access_token );
		if ( is_wp_error( $profile ) ) {
			return $profile;
		}

		// 4. 從 id_token 解析 email（LINE Profile API 不返回 email）
		if ( ! empty( $token_result['id_token'] ) ) {
			$id_token_data = $this->decode_id_token( $token_result['id_token'] );
			if ( ! empty( $id_token_data['email'] ) ) {
				$profile['email'] = $id_token_data['email'];
			}
		}

		Logger::log_placeholder(
			'info',
			array(
				'message'   => 'LINE Login callback handled successfully',
				'line_uid'  => $profile['userId'] ?? 'unknown',
				'has_email' => ! empty( $profile['email'] ),
				'state'     => $state,
			)
		);

		return array(
			'profile'    => $profile,
			'state_data' => $state_data,
		);
	}

	/**
	 * Exchange authorization code for access token
	 *
	 * @param string $code LINE 授權碼
	 * @return array|\WP_Error 成功時返回 token 資料，失敗時返回 WP_Error
	 */
	private function exchange_token( string $code ) {
		$channel_id     = SettingsService::get( 'login_channel_id', '' );
		$channel_secret = SettingsService::get( 'login_channel_secret', '' );
		$callback_url   = site_url( 'wp-login.php?loginSocial=buygo-line' );

		$response = wp_remote_post(
			self::LINE_API_BASE_URL . '/oauth2/v2.1/token',
			array(
				'body' => array(
					'grant_type'    => 'authorization_code',
					'code'          => $code,
					'redirect_uri'  => $callback_url,
					'client_id'     => $channel_id,
					'client_secret' => $channel_secret,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			Logger::log_placeholder( 'error', array( 'message' => 'Token exchange HTTP request failed', 'error' => $response->get_error_message() ) );
			return new \WP_Error( 'token_exchange_failed', 'Failed to exchange token: ' . $response->get_error_message() );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['access_token'] ) ) {
			Logger::log_placeholder( 'error', array( 'message' => 'Token exchange failed', 'response' => $body ) );
			return new \WP_Error( 'token_exchange_failed', 'Token exchange failed: ' . ( $data['error_description'] ?? 'Unknown error' ) );
		}

		Logger::log_placeholder( 'info', array( 'message' => 'Token exchange successful', 'has_token' => true ) );

		return $data;
	}

	/**
	 * 取得 LINE user profile
	 *
	 * @param string $access_token LINE access token
	 * @return array|\WP_Error 成功時返回 profile 資料，失敗時返回 WP_Error
	 */
	private function get_profile( string $access_token ) {
		$response = wp_remote_get(
			self::LINE_API_BASE_URL . '/v2/profile',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			Logger::log_placeholder( 'error', array( 'message' => 'Profile fetch HTTP request failed', 'error' => $response->get_error_message() ) );
			return new \WP_Error( 'profile_fetch_failed', 'Failed to fetch profile: ' . $response->get_error_message() );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['userId'] ) ) {
			Logger::log_placeholder( 'error', array( 'message' => 'Profile fetch failed', 'response' => $body ) );
			return new \WP_Error( 'profile_fetch_failed', 'Profile fetch failed: Invalid response' );
		}

		Logger::log_placeholder(
			'info',
			array(
				'message'     => 'Profile fetch successful',
				'userId'      => $data['userId'],
				'displayName' => $data['displayName'] ?? 'unknown',
			)
		);

		return $data;
	}

	/**
	 * 解析 LINE id_token（JWT 格式）
	 *
	 * LINE id_token 是 JWT，包含 email 等資訊
	 * 格式：header.payload.signature
	 * 我們只需要 payload 部分（Base64 URL 編碼）
	 *
	 * @param string $id_token LINE id_token（JWT 格式）
	 * @return array 解析後的 payload 資料
	 */
	private function decode_id_token( string $id_token ): array {
		$parts = explode( '.', $id_token );

		// JWT 應該有 3 個部分
		if ( count( $parts ) !== 3 ) {
			Logger::log_placeholder( 'warning', array( 'message' => 'Invalid id_token format' ) );
			return array();
		}

		// 解析 payload（第二部分）
		$payload = $parts[1];

		// Base64 URL 解碼（替換 - 為 +，_ 為 /）
		$payload = str_replace( array( '-', '_' ), array( '+', '/' ), $payload );

		// 補齊 padding
		$padding = strlen( $payload ) % 4;
		if ( $padding > 0 ) {
			$payload .= str_repeat( '=', 4 - $padding );
		}

		$decoded = base64_decode( $payload );
		if ( $decoded === false ) {
			Logger::log_placeholder( 'warning', array( 'message' => 'Failed to decode id_token payload' ) );
			return array();
		}

		$data = json_decode( $decoded, true );
		if ( ! is_array( $data ) ) {
			Logger::log_placeholder( 'warning', array( 'message' => 'Invalid id_token payload JSON' ) );
			return array();
		}

		Logger::log_placeholder(
			'info',
			array(
				'message'   => 'id_token decoded successfully',
				'has_email' => isset( $data['email'] ),
			)
		);

		return $data;
	}

	/**
	 * 檢查是否應該使用 NSL (Nextend Social Login)
	 *
	 * @return bool true 如果應該使用 NSL，否則 false
	 */
	private function should_use_nsl(): bool {
		// 檢查 NSL 外掛是否啟用
		if ( ! class_exists( 'NextendSocialLogin' ) ) {
			return false;
		}

		// 檢查 LINE provider 是否啟用
		// NSL 的 LINE provider class 通常是 NextendSocialProviderLINE
		if ( ! class_exists( 'NextendSocialProviderLINE' ) ) {
			return false;
		}

		// 檢查是否在設定中明確停用 NSL fallback
		$disable_nsl = SettingsService::get( 'disable_nsl_fallback', false );
		if ( $disable_nsl ) {
			return false;
		}

		return true;
	}

	/**
	 * 產生 NSL LINE authorize URL
	 *
	 * @param string $redirect_url 授權完成後的導向 URL
	 * @return string NSL LINE authorize URL
	 */
	private function get_nsl_authorize_url( string $redirect_url ): string {
		// 使用 NSL 的 API 來生成正確的 URL
		// NSL Pro 和 Free 版本都支援這個方法

		// 檢查用戶是否已登入
		$is_logged_in = is_user_logged_in();
		$mode = $is_logged_in ? 'connect' : 'login';

		try {
			// 嘗試使用 NSL 的 Provider API
			if ( class_exists( 'NextendSocialLogin' ) && method_exists( 'NextendSocialLogin', 'getProviderByType' ) ) {
				$provider = \NextendSocialLogin::getProviderByType( 'line' );

				if ( $provider ) {
					// 設定 redirect_to 參數
					if ( ! empty( $redirect_url ) ) {
						$_REQUEST['redirect_to'] = $redirect_url;
					}

					// 根據用戶狀態使用不同的方法
					if ( $is_logged_in && method_exists( $provider, 'getConnectUrl' ) ) {
						// 已登入用戶：使用 Connect URL
						$nsl_url = $provider->getConnectUrl();

						Logger::log_placeholder(
							'info',
							array(
								'message' => 'NSL LINE connect URL generated (logged in user)',
								'url'     => $nsl_url,
								'user_id' => get_current_user_id(),
							)
						);

						return $nsl_url;

					} elseif ( method_exists( $provider, 'getLoginUrl' ) ) {
						// 未登入用戶：使用 Login URL
						$nsl_url = $provider->getLoginUrl();

						Logger::log_placeholder(
							'info',
							array(
								'message' => 'NSL LINE login URL generated via API',
								'url'     => $nsl_url,
							)
						);

						return $nsl_url;
					}
				}
			}
		} catch ( \Throwable $e ) {
			// PHP 7.0+ 使用 Throwable 同時捕捉 Exception 和 Error
			// 這包括 Fatal Error（方法不存在、類別載入失敗等）
			Logger::log_placeholder(
				'warning',
				array(
					'message' => 'Failed to use NSL API, falling back to manual URL',
					'error'   => $e->getMessage(),
					'file'    => $e->getFile(),
					'line'    => $e->getLine(),
					'mode'    => $mode,
				)
			);
		}

		// Fallback：手動構建 URL
		// NSL 的 connect 和 login 使用相同的 URL 格式，由 session 狀態決定
		$params = array(
			'loginSocial' => 'line',
		);

		if ( ! empty( $redirect_url ) ) {
			$params['redirect_to'] = $redirect_url;
		}

		$nsl_url = add_query_arg( $params, wp_login_url() );

		Logger::log_placeholder(
			'info',
			array(
				'message' => "NSL LINE {$mode} URL generated manually",
				'url'     => $nsl_url,
				'is_logged_in' => $is_logged_in,
			)
		);

		return $nsl_url;
	}
}
