<?php
/**
 * State Manager Service
 *
 * 管理 LINE Login OAuth 2.0 state 參數的儲存與驗證
 * 使用 WordPress Transient API 處理 state 儲存（適用於 REST API 環境）
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
 * - 使用 Transient API 儲存（適用於 REST API 請求）
 * - 驗證 state（時效性檢查）
 * - 一次性使用（防重放攻擊）
 */
class StateManager {

	/**
	 * Transient 前綴
	 */
	const TRANSIENT_PREFIX = 'buygo_line_state_';

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
	 * 儲存 state 到 Transient
	 *
	 * 使用 WordPress Transient API 儲存 state 資料
	 * 適用於 REST API 環境（不依賴 PHP Session）
	 *
	 * @param string $state 要儲存的 state
	 * @param array  $data 要儲存的資料（包含 redirect_url, user_id 等）
	 * @return bool 是否成功儲存
	 */
	public function store_state( string $state, array $data ): bool {
		// 加入時間戳記
		$data['created_at'] = time();

		// 使用 Transient API（有效期 10 分鐘）
		$result = set_transient( self::TRANSIENT_PREFIX . $state, $data, self::STATE_EXPIRY );

		// Debug: 立即驗證是否真的儲存成功
		$verify = get_transient( self::TRANSIENT_PREFIX . $state );
		Logger::log_placeholder(
			'debug',
			array(
				'message'        => 'Transient store debug',
				'state'          => $state,
				'set_result'     => $result ? 'true' : 'false',
				'verify_result'  => $verify !== false ? 'found' : 'not_found',
				'verify_data'    => $verify !== false ? $verify : null,
			)
		);

		return $result;
	}

	/**
	 * 驗證 state
	 *
	 * 從 Transient 讀取 state 資料並驗證時效性
	 *
	 * @param string $state 要驗證的 state
	 * @return array|false 成功時返回儲存的資料，失敗時返回 false
	 */
	public function verify_state( string $state ) {
		// 從 Transient 讀取
		$data = get_transient( self::TRANSIENT_PREFIX . $state );

		// Debug: 記錄驗證過程
		Logger::log_placeholder(
			'debug',
			array(
				'message'       => 'Verify state attempt',
				'state'         => $state,
				'transient_key' => self::TRANSIENT_PREFIX . $state,
				'data_found'    => $data !== false,
				'data'          => $data !== false ? $data : null,
			)
		);

		// 未找到 state
		if ( $data === false ) {
			Logger::log_placeholder(
				'error',
				array(
					'message' => 'State not found in Transient',
					'state'   => $state,
				)
			);
			return false;
		}

		// 驗證時效性（額外保險，Transient 本身已有過期機制）
		$created_at = $data['created_at'] ?? 0;
		$age = time() - $created_at;
		if ( $age > self::STATE_EXPIRY ) {
			// 過期，清除並返回 false
			Logger::log_placeholder(
				'error',
				array(
					'message'    => 'State expired',
					'state'      => $state,
					'age'        => $age,
					'expiry'     => self::STATE_EXPIRY,
				)
			);
			$this->consume_state( $state );
			return false;
		}

		Logger::log_placeholder(
			'info',
			array(
				'message' => 'State verified successfully',
				'state'   => $state,
				'age'     => $age,
			)
		);

		return $data;
	}

	/**
	 * 消費 state（一次性使用，防重放攻擊）
	 *
	 * 從 Transient 中刪除 state
	 *
	 * @param string $state 要消費的 state
	 * @return void
	 */
	public function consume_state( string $state ): void {
		delete_transient( self::TRANSIENT_PREFIX . $state );
	}
}
