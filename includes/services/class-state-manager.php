<?php
/**
 * State Manager Service
 *
 * 管理 LINE Login OAuth 2.0 state 參數的儲存與驗證
 * 使用三層儲存機制 (Session → Transient → Option) 處理 LINE 瀏覽器環境的 Cookie 清除問題
 *
 * @package BuygoLineNotify
 */

namespace BuygoLineNotify\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class StateManager
 *
 * 負責 OAuth state 參數的生命週期管理：
 * - 產生隨機 state（32 字元）
 * - 三層儲存 fallback（Session → Transient → Option）
 * - 驗證 state（時效性檢查）
 * - 一次性使用（防重放攻擊）
 */
class StateManager {

	/**
	 * State 有效期（秒）
	 */
	const STATE_EXPIRY = 600; // 10 分鐘

	/**
	 * 產生隨機 state
	 *
	 * 使用 random_bytes 產生 32 字元十六進位字串
	 *
	 * @return string 32 字元的隨機 state
	 */
	public function generate_state(): string {
		return bin2hex( random_bytes( 16 ) );
	}

	/**
	 * 儲存 state 到三層儲存
	 *
	 * Layer 1: Session（優先，適用於正常瀏覽器）
	 * Layer 2: Transient（備用，適用於 Session 失效）
	 * Layer 3: Option（最後手段，適用於極端情況）
	 *
	 * @param string $state 要儲存的 state
	 * @param array  $data 要儲存的資料（包含 redirect_url, user_id 等）
	 * @return bool 是否成功儲存至少一層
	 */
	public function store_state( string $state, array $data ): bool {
		// 加入時間戳記
		$data['created_at'] = time();

		$success = false;

		// Layer 1: Session
		if ( ! session_id() ) {
			session_start();
		}
		if ( isset( $_SESSION ) ) {
			if ( ! isset( $_SESSION['buygo_line_state'] ) ) {
				$_SESSION['buygo_line_state'] = array();
			}
			$_SESSION['buygo_line_state'][ $state ] = $data;
			$success                                 = true;
		}

		// Layer 2: Transient (10 分鐘)
		if ( set_transient( "buygo_line_state_{$state}", $data, self::STATE_EXPIRY ) ) {
			$success = true;
		}

		// Layer 3: Option (備用)
		if ( update_option( "buygo_line_state_{$state}", $data, false ) ) {
			$success = true;
		}

		return $success;
	}

	/**
	 * 驗證 state（三層 fallback 查詢）
	 *
	 * 依序檢查 Session → Transient → Option
	 * 驗證 state 的時效性（不超過 10 分鐘）
	 *
	 * @param string $state 要驗證的 state
	 * @return array|false 成功時返回儲存的資料，失敗時返回 false
	 */
	public function verify_state( string $state ) {
		$data = null;

		// Layer 1: Session
		if ( ! session_id() ) {
			session_start();
		}
		if ( isset( $_SESSION['buygo_line_state'][ $state ] ) ) {
			$data = $_SESSION['buygo_line_state'][ $state ];
		}

		// Layer 2: Transient
		if ( $data === null ) {
			$transient_data = get_transient( "buygo_line_state_{$state}" );
			if ( $transient_data !== false ) {
				$data = $transient_data;
			}
		}

		// Layer 3: Option
		if ( $data === null ) {
			$option_data = get_option( "buygo_line_state_{$state}", null );
			if ( $option_data !== null && $option_data !== false ) {
				$data = $option_data;
			}
		}

		// 未找到 state
		if ( $data === null ) {
			return false;
		}

		// 驗證時效性
		$created_at = $data['created_at'] ?? 0;
		if ( time() - $created_at > self::STATE_EXPIRY ) {
			// 過期，清除並返回 false
			$this->consume_state( $state );
			return false;
		}

		// 使用 hash_equals 防時序攻擊
		if ( ! hash_equals( $state, $state ) ) {
			return false;
		}

		return $data;
	}

	/**
	 * 消費 state（一次性使用，防重放攻擊）
	 *
	 * 從三層儲存中全部刪除
	 *
	 * @param string $state 要消費的 state
	 * @return void
	 */
	public function consume_state( string $state ): void {
		// Layer 1: Session
		if ( ! session_id() ) {
			session_start();
		}
		if ( isset( $_SESSION['buygo_line_state'][ $state ] ) ) {
			unset( $_SESSION['buygo_line_state'][ $state ] );
		}

		// Layer 2: Transient
		delete_transient( "buygo_line_state_{$state}" );

		// Layer 3: Option
		delete_option( "buygo_line_state_{$state}" );
	}
}
