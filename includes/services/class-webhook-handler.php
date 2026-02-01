<?php
/**
 * Webhook Handler Service
 *
 * 處理 LINE Webhook 事件，實作去重機制，並透過 WordPress Hooks 讓其他外掛註冊事件處理器
 *
 * @package BuygoLineNotify
 */

namespace BuygoLineNotify\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WebhookHandler
 *
 * 負責處理 LINE Webhook 事件：
 * - 事件去重（使用 webhookEventId）
 * - 背景處理（防止超時）
 * - 觸發 WordPress Hooks（讓其他外掛註冊處理器）
 */
class WebhookHandler {

	/**
	 * 處理 Webhook 事件陣列
	 *
	 * @param array $events LINE Webhook 事件陣列
	 * @return void
	 */
	public function process_events( array $events ): void {
		// 防止客戶端斷線中斷處理
		ignore_user_abort( true );
		set_time_limit( 0 );

		foreach ( $events as $event ) {
			// 事件去重檢查（使用 webhookEventId）
			$event_id = $event['webhookEventId'] ?? '';
			if ( ! empty( $event_id ) ) {
				$cache_key = 'buygo_line_event_' . $event_id;

				// 檢查是否已處理
				if ( get_transient( $cache_key ) ) {
					continue; // 跳過重複事件
				}

				// 標記為已處理（60 秒內去重）
				set_transient( $cache_key, true, 60 );
			}

			// 處理事件
			$this->handle_event( $event );
		}
	}

	/**
	 * 處理單一事件並觸發對應的 Hook
	 *
	 * @param array $event LINE Webhook 事件資料
	 * @return void
	 */
	private function handle_event( array $event ): void {
		$event_type = $event['type'] ?? '';
		$line_uid = $event['source']['userId'] ?? '';
		$webhook_event_id = $event['webhookEventId'] ?? null;
		$reply_token = $event['replyToken'] ?? '';

		// 取得對應的 WordPress User ID（如果已綁定）
		$user_id = null;
		if ( ! empty( $line_uid ) ) {
			$user = LineUserService::getUserByLineUid( $line_uid );
			if ( $user ) {
				$user_id = $user->ID;
			}
		}

		// 記錄 Webhook 事件（Debug 工具）
		Logger::logWebhookEvent( $event_type, $line_uid, $user_id, $webhook_event_id );

		// 觸發通用 Hook（所有事件類型）
		// 參數：$event, $event_type, $line_uid, $user_id
		do_action( 'buygo_line_notify/webhook_event', $event, $event_type, $line_uid, $user_id );

		// 根據事件類型觸發特定 Hook
		switch ( $event_type ) {
			case 'message':
				do_action( 'buygo_line_notify/webhook_message', $event, $line_uid, $user_id );
				$this->handle_message( $event, $line_uid, $user_id, $reply_token );
				break;

			case 'follow':
				do_action( 'buygo_line_notify/webhook_follow', $event, $line_uid, $user_id );
				$this->send_response_if_provided( 'follow', $event, $line_uid, $user_id, $reply_token );
				break;

			case 'unfollow':
				do_action( 'buygo_line_notify/webhook_unfollow', $event, $line_uid, $user_id );
				// unfollow 不需要回覆
				break;

			case 'postback':
				do_action( 'buygo_line_notify/webhook_postback', $event, $line_uid, $user_id );
				$this->send_response_if_provided( 'postback', $event, $line_uid, $user_id, $reply_token );
				break;

			default:
				// 未處理的事件類型
				break;
		}
	}

	/**
	 * 處理訊息事件並觸發對應的訊息類型 Hook
	 *
	 * @param array    $event       LINE Webhook 事件資料
	 * @param string   $line_uid    LINE User ID
	 * @param int|null $user_id     WordPress User ID (null if not linked)
	 * @param string   $reply_token Reply Token
	 * @return void
	 */
	private function handle_message( array $event, string $line_uid, ?int $user_id, string $reply_token ): void {
		$message_type = $event['message']['type'] ?? '';
		$message_id = $event['message']['id'] ?? '';

		// 根據訊息類型觸發特定 Hook
		// 參數：$event, $line_uid, $user_id, $message_id
		switch ( $message_type ) {
			case 'text':
				do_action( 'buygo_line_notify/webhook_message_text', $event, $line_uid, $user_id, $message_id );
				$this->send_response_if_provided( 'message_text', $event, $line_uid, $user_id, $reply_token );
				break;

			case 'image':
				do_action( 'buygo_line_notify/webhook_message_image', $event, $line_uid, $user_id, $message_id );
				$this->send_response_if_provided( 'message_image', $event, $line_uid, $user_id, $reply_token );
				break;

			case 'video':
				do_action( 'buygo_line_notify/webhook_message_video', $event, $line_uid, $user_id, $message_id );
				$this->send_response_if_provided( 'message_video', $event, $line_uid, $user_id, $reply_token );
				break;

			case 'audio':
				do_action( 'buygo_line_notify/webhook_message_audio', $event, $line_uid, $user_id, $message_id );
				$this->send_response_if_provided( 'message_audio', $event, $line_uid, $user_id, $reply_token );
				break;

			case 'file':
				do_action( 'buygo_line_notify/webhook_message_file', $event, $line_uid, $user_id, $message_id );
				$this->send_response_if_provided( 'message_file', $event, $line_uid, $user_id, $reply_token );
				break;

			case 'location':
				do_action( 'buygo_line_notify/webhook_message_location', $event, $line_uid, $user_id, $message_id );
				$this->send_response_if_provided( 'message_location', $event, $line_uid, $user_id, $reply_token );
				break;

			case 'sticker':
				do_action( 'buygo_line_notify/webhook_message_sticker', $event, $line_uid, $user_id, $message_id );
				$this->send_response_if_provided( 'message_sticker', $event, $line_uid, $user_id, $reply_token );
				break;

			default:
				// 未處理的訊息類型
				break;
		}
	}

	/**
	 * 透過 Filter 向其他外掛詢問回覆內容，如果有則發送
	 *
	 * 這是 buygo-line-notify 與 buygo-plus-one-dev 的橋接點：
	 * - buygo-plus-one-dev 監聽 'buygo_line_notify/get_response' filter
	 * - 根據事件類型和用戶身份決定要回傳的模板內容
	 * - buygo-line-notify 收到內容後發送 LINE 訊息
	 *
	 * @param string   $action_type 事件類型（message_text, message_image, follow, postback...）
	 * @param array    $event       LINE Webhook 事件資料
	 * @param string   $line_uid    LINE User ID
	 * @param int|null $user_id     WordPress User ID (null if not linked)
	 * @param string   $reply_token Reply Token
	 * @return void
	 */
	private function send_response_if_provided( string $action_type, array $event, string $line_uid, ?int $user_id, string $reply_token ): void {
		// 透過 Filter 向其他外掛詢問回覆內容
		// 回傳格式可以是：
		// 1. null - 不回覆
		// 2. string - 純文字訊息
		// 3. array - LINE Message 格式（text, flex, image...）
		$response = apply_filters(
			'buygo_line_notify/get_response',
			null,
			$action_type,
			$event,
			$line_uid,
			$user_id
		);

		// 如果沒有回覆內容，直接返回
		if ( $response === null ) {
			return;
		}

		// 發送回覆
		$this->send_reply( $reply_token, $response, $line_uid );
	}

	/**
	 * 發送 LINE 回覆訊息
	 *
	 * @param string       $reply_token Reply Token
	 * @param string|array $message     訊息內容（純文字或 LINE Message 格式）
	 * @param string       $line_uid    LINE User ID（Reply 失敗時用於 Push）
	 * @return bool
	 */
	private function send_reply( string $reply_token, $message, string $line_uid ): bool {
		// 取得 MessagingService
		$messaging = \BuygoLineNotify\BuygoLineNotify::messaging();

		// 如果是純文字，轉換為 LINE Message 格式
		if ( is_string( $message ) ) {
			$message = [
				'type' => 'text',
				'text' => $message,
			];
		}

		// 嘗試使用 Reply Token 回覆
		if ( ! empty( $reply_token ) ) {
			$result = $messaging->send_reply( $reply_token, $message, $line_uid );

			if ( ! is_wp_error( $result ) ) {
				return true;
			}

			// Reply 失敗，記錄日誌
			Logger::log( 'reply_failed', [
				'error'    => $result->get_error_message(),
				'line_uid' => $line_uid,
				'fallback' => 'push_message',
			] );
		}

		// Reply 失敗或沒有 Reply Token，改用 Push
		if ( ! empty( $line_uid ) ) {
			$result = $messaging->push_message( $line_uid, $message );

			if ( is_wp_error( $result ) ) {
				Logger::log( 'push_failed', [
					'error'    => $result->get_error_message(),
					'line_uid' => $line_uid,
				] );
				return false;
			}

			return true;
		}

		return false;
	}

	/**
	 * 檢查 LINE 用戶是否有特定權限
	 *
	 * 檢查順序：
	 * 1. 檢查是否為 WordPress 管理員
	 * 2. 檢查是否有 buygo_admin 或 buygo_helper 角色
	 *
	 * @param int|null $user_id WordPress User ID (null if not linked)
	 * @param string   $required_capability 所需權限（預設 'manage_options'）
	 * @return bool
	 */
	public static function user_has_permission( ?int $user_id, string $required_capability = 'manage_options' ): bool {
		// 未綁定用戶沒有權限
		if ( $user_id === null ) {
			return false;
		}

		$user = \get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		// 檢查是否為管理員
		if ( \user_can( $user, 'manage_options' ) ) {
			return true;
		}

		// 檢查是否有 buygo_admin 或 buygo_helper 角色
		if ( \user_can( $user, 'buygo_admin' ) || \user_can( $user, 'buygo_helper' ) ) {
			return true;
		}

		// 檢查自訂權限
		if ( ! empty( $required_capability ) && \user_can( $user, $required_capability ) ) {
			return true;
		}

		return false;
	}
}
