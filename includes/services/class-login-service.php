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
	 * @param string   $redirect_url 授權完成後的導向 URL
	 * @param int|null $user_id WordPress 使用者 ID（可選）
	 * @return string LINE authorize URL
	 */
	public function get_authorize_url( string $redirect_url, ?int $user_id = null ): string {
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
		Logger::get_instance()->log(
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

		Logger::get_instance()->log(
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
			Logger::get_instance()->log( 'error', array( 'message' => 'Invalid or expired state', 'state' => $state ) );
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

		Logger::get_instance()->log(
			'info',
			array(
				'message'  => 'LINE Login callback handled successfully',
				'line_uid' => $profile['userId'] ?? 'unknown',
				'state'    => $state,
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
			Logger::get_instance()->log( 'error', array( 'message' => 'Token exchange HTTP request failed', 'error' => $response->get_error_message() ) );
			return new \WP_Error( 'token_exchange_failed', 'Failed to exchange token: ' . $response->get_error_message() );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['access_token'] ) ) {
			Logger::get_instance()->log( 'error', array( 'message' => 'Token exchange failed', 'response' => $body ) );
			return new \WP_Error( 'token_exchange_failed', 'Token exchange failed: ' . ( $data['error_description'] ?? 'Unknown error' ) );
		}

		Logger::get_instance()->log( 'info', array( 'message' => 'Token exchange successful', 'has_token' => true ) );

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
			Logger::get_instance()->log( 'error', array( 'message' => 'Profile fetch HTTP request failed', 'error' => $response->get_error_message() ) );
			return new \WP_Error( 'profile_fetch_failed', 'Failed to fetch profile: ' . $response->get_error_message() );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['userId'] ) ) {
			Logger::get_instance()->log( 'error', array( 'message' => 'Profile fetch failed', 'response' => $body ) );
			return new \WP_Error( 'profile_fetch_failed', 'Profile fetch failed: Invalid response' );
		}

		Logger::get_instance()->log(
			'info',
			array(
				'message'     => 'Profile fetch successful',
				'userId'      => $data['userId'],
				'displayName' => $data['displayName'] ?? 'unknown',
			)
		);

		return $data;
	}
}
