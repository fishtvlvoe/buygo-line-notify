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

		// 觸發通用 Hook（所有事件類型）
		do_action( 'buygo_line_notify/webhook_event', $event, $event_type );

		// 根據事件類型觸發特定 Hook
		switch ( $event_type ) {
			case 'message':
				do_action( 'buygo_line_notify/webhook_message', $event );
				$this->handle_message( $event );
				break;

			case 'follow':
				do_action( 'buygo_line_notify/webhook_follow', $event );
				break;

			case 'unfollow':
				do_action( 'buygo_line_notify/webhook_unfollow', $event );
				break;

			case 'postback':
				do_action( 'buygo_line_notify/webhook_postback', $event );
				break;

			default:
				// 未處理的事件類型
				break;
		}
	}

	/**
	 * 處理訊息事件並觸發對應的訊息類型 Hook
	 *
	 * @param array $event LINE Webhook 事件資料
	 * @return void
	 */
	private function handle_message( array $event ): void {
		$message_type = $event['message']['type'] ?? '';

		// 根據訊息類型觸發特定 Hook
		switch ( $message_type ) {
			case 'text':
				do_action( 'buygo_line_notify/webhook_message_text', $event );
				break;

			case 'image':
				do_action( 'buygo_line_notify/webhook_message_image', $event );
				break;

			case 'video':
				do_action( 'buygo_line_notify/webhook_message_video', $event );
				break;

			case 'audio':
				do_action( 'buygo_line_notify/webhook_message_audio', $event );
				break;

			case 'file':
				do_action( 'buygo_line_notify/webhook_message_file', $event );
				break;

			case 'location':
				do_action( 'buygo_line_notify/webhook_message_location', $event );
				break;

			case 'sticker':
				do_action( 'buygo_line_notify/webhook_message_sticker', $event );
				break;

			default:
				// 未處理的訊息類型
				break;
		}
	}
}
