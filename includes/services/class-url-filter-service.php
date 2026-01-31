<?php
/**
 * URL Filter Service
 *
 * 處理 WordPress login_url 和 logout_url filters
 * 提供選擇性整合 LINE Login 參數
 *
 * @package BuygoLineNotify
 */

namespace BuygoLineNotify\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class UrlFilterService
 *
 * 負責整合 WordPress URL filters：
 * - login_url filter: 可選擇性附加 loginSocial=buygo-line 參數
 * - logout_url filter: 目前僅作為擴展點
 * - wp_logout action: 清除 LINE 相關 Session/Transient 資料
 */
class UrlFilterService {

	/**
	 * 註冊 WordPress hooks
	 *
	 * 靜態方法,由 Plugin 類別呼叫
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		$service = new self();

		// login_url filter - 優先級 20（讓其他外掛先處理）
		add_filter( 'login_url', array( $service, 'filter_login_url' ), 20, 3 );

		// logout_url filter - 優先級 20
		add_filter( 'logout_url', array( $service, 'filter_logout_url' ), 20, 2 );

		// wp_logout action - 清除 LINE 相關資料
		add_action( 'wp_logout', array( $service, 'on_logout' ), 10, 1 );
	}

	/**
	 * Filter login_url
	 *
	 * 可選擇性附加 loginSocial=buygo-line 參數
	 * 預設關閉,需在後台設定啟用
	 *
	 * @param string $login_url 原始登入 URL
	 * @param string $redirect 導向 URL（可能為空）
	 * @param bool   $force_reauth 是否強制重新認證
	 * @return string 修改後的登入 URL
	 */
	public function filter_login_url( string $login_url, string $redirect, bool $force_reauth ): string {
		// 檢查設定是否啟用
		$auto_append = get_option( 'buygo_line_auto_append_login_social', false );
		if ( ! $auto_append ) {
			return $login_url;
		}

		// 附加 loginSocial 參數
		$login_url = add_query_arg( 'loginSocial', 'buygo-line', $login_url );

		Logger::log_placeholder(
			'debug',
			array(
				'message'       => 'login_url filter applied',
				'original_url'  => $login_url,
				'loginSocial'   => 'buygo-line',
			)
		);

		return $login_url;
	}

	/**
	 * Filter logout_url
	 *
	 * 目前不修改登出 URL,僅作為未來擴展點
	 *
	 * @param string $logout_url 原始登出 URL
	 * @param string $redirect 導向 URL（可能為空）
	 * @return string 原始登出 URL
	 */
	public function filter_logout_url( string $logout_url, string $redirect ): string {
		// 目前不修改登出 URL,僅作為擴展點
		return $logout_url;
	}

	/**
	 * Handle wp_logout action
	 *
	 * 登出時清除 LINE 相關資料
	 * - 清除 session 中的 LINE profile 和 state
	 * - 記錄登出日誌
	 *
	 * @param int $user_id 登出的用戶 ID
	 * @return void
	 */
	public function on_logout( int $user_id ): void {
		// 清除 session 中的 LINE 相關資料（若使用 session）
		if ( session_status() === PHP_SESSION_ACTIVE ) {
			unset( $_SESSION['buygo_line_profile'] );
			unset( $_SESSION['buygo_line_state'] );
		}

		Logger::log_placeholder(
			'debug',
			array(
				'message' => 'User logged out, LINE session data cleared',
				'user_id' => $user_id,
			)
		);
	}
}
