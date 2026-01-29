<?php
/**
 * FluentCart Integration API
 *
 * REST API endpoints for FluentCart customer profile integration
 *
 * @package BuygoLineNotify
 */

namespace BuygoLineNotify\Api;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FluentCartIntegrationAPI
 *
 * 提供 FluentCart Vue 元件呼叫的 REST API endpoints
 */
class FluentCartIntegrationAPI {

	/**
	 * 註冊 REST API routes
	 */
	public function register_routes(): void {
		// 取得當前用戶的 LINE 綁定狀態
		\register_rest_route(
			'buygo-line-notify/v1',
			'/fluentcart/binding-status',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_binding_status' ],
				'permission_callback' => [ $this, 'check_user_permission' ],
			]
		);

		// 產生綁定 LINE 的 authorize URL
		\register_rest_route(
			'buygo-line-notify/v1',
			'/fluentcart/bind-url',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_bind_url' ],
				'permission_callback' => [ $this, 'check_user_permission' ],
			]
		);

		// 解除 LINE 綁定
		\register_rest_route(
			'buygo-line-notify/v1',
			'/fluentcart/unbind',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'unbind_line' ],
				'permission_callback' => [ $this, 'check_user_permission' ],
			]
		);
	}

	/**
	 * 權限檢查：必須登入
	 *
	 * @return bool
	 */
	public function check_user_permission(): bool {
		return \is_user_logged_in();
	}

	/**
	 * 取得當前用戶的 LINE 綁定狀態
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function get_binding_status( \WP_REST_Request $request ): \WP_REST_Response {
		$user_id = \get_current_user_id();

		if ( ! $user_id ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => '未登入',
				],
				401
			);
		}

		// 檢查是否已綁定 LINE
		$is_linked = \BuygoLineNotify\Services\LineUserService::isUserLinked( $user_id );

		if ( ! $is_linked ) {
			return new \WP_REST_Response(
				[
					'success'   => true,
					'is_linked' => false,
					'message'   => '未綁定 LINE',
				],
				200
			);
		}

		// 取得綁定資料
		$line_data = \BuygoLineNotify\Services\LineUserService::getUser( $user_id );

		if ( ! $line_data ) {
			return new \WP_REST_Response(
				[
					'success'   => true,
					'is_linked' => false,
					'message'   => '綁定資料不存在',
				],
				200
			);
		}

		// 取得 LINE profile（頭像、名稱）
		$display_name = \get_user_meta( $user_id, 'buygo_line_display_name', true );
		$avatar_url   = \get_user_meta( $user_id, 'buygo_line_avatar_url', true );

		return new \WP_REST_Response(
			[
				'success'      => true,
				'is_linked'    => true,
				'line_uid'     => $line_data['line_uid'] ?? '',
				'display_name' => $display_name ?: '未知',
				'avatar_url'   => $avatar_url ?: '',
				'linked_at'    => $line_data['link_date'] ?? '',
			],
			200
		);
	}

	/**
	 * 產生綁定 LINE 的 authorize URL
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function get_bind_url( \WP_REST_Request $request ): \WP_REST_Response {
		$user_id = \get_current_user_id();

		if ( ! $user_id ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => '未登入',
				],
				401
			);
		}

		// 取得 redirect_url 參數（綁定後導向）
		$redirect_url = $request->get_param( 'redirect_url' );
		if ( empty( $redirect_url ) ) {
			$redirect_url = \home_url( '/my-account/' );
		}

		try {
			// 產生 authorize URL（包含 state）
			$authorize_url = \BuygoLineNotify\Services\LoginService::get_authorize_url(
				$redirect_url,
				$user_id  // 傳入 user_id 表示這是綁定流程
			);

			return new \WP_REST_Response(
				[
					'success'       => true,
					'authorize_url' => $authorize_url,
				],
				200
			);
		} catch ( \Exception $e ) {
			\BuygoLineNotify\Logger::get_instance()->log(
				'產生 authorize URL 失敗: ' . $e->getMessage(),
				'ERROR'
			);

			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => '產生授權 URL 失敗',
				],
				500
			);
		}
	}

	/**
	 * 解除 LINE 綁定
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response
	 */
	public function unbind_line( \WP_REST_Request $request ): \WP_REST_Response {
		$user_id = \get_current_user_id();

		if ( ! $user_id ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => '未登入',
				],
				401
			);
		}

		// 呼叫 LineUserService 解除綁定
		$result = \BuygoLineNotify\Services\LineUserService::unlinkUser( $user_id );

		if ( ! $result ) {
			return new \WP_REST_Response(
				[
					'success' => false,
					'message' => '解除綁定失敗',
				],
				500
			);
		}

		// 清除相關 user_meta
		\delete_user_meta( $user_id, 'buygo_line_avatar_url' );
		\delete_user_meta( $user_id, 'buygo_line_avatar_updated' );
		\delete_user_meta( $user_id, 'buygo_line_display_name' );
		\delete_option( "buygo_line_sync_log_{$user_id}" );
		\delete_option( "buygo_line_conflict_log_{$user_id}" );

		// 記錄日誌
		$user = \get_user_by( 'id', $user_id );
		\BuygoLineNotify\Logger::get_instance()->log(
			"用戶 {$user_id} ({$user->user_login}) 透過 FluentCart 解除 LINE 綁定",
			'INFO',
			[ 'action' => 'unbind_via_fluentcart', 'user_id' => $user_id ]
		);

		return new \WP_REST_Response(
			[
				'success' => true,
				'message' => '已成功解除 LINE 綁定',
			],
			200
		);
	}
}
