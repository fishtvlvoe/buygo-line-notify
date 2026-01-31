<?php
/**
 * NSL Continue Page Render Exception
 *
 * 用於控制 LINE Login OAuth 流程（非錯誤例外）
 * 拋出此例外時，讓 WordPress 繼續渲染頁面而非重定向
 * 對齊 Nextend Social Login 架構模式
 *
 * @package BuygoLineNotify
 */

namespace BuygoLineNotify\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class NSLContinuePageRenderException
 *
 * 控制流程的特殊例外（非錯誤）
 * 用於區分三種 LINE Login 流程：
 * - FLOW_REGISTER: 新用戶註冊流程
 * - FLOW_LOGIN: 已有用戶登入流程
 * - FLOW_LINK: 已登入用戶綁定流程
 */
class NSLContinuePageRenderException extends \Exception {

	/**
	 * 流程類型常數
	 */
	const FLOW_REGISTER = 'register';
	const FLOW_LOGIN    = 'login';
	const FLOW_LINK     = 'link';

	/**
	 * 流程類型
	 *
	 * @var string
	 */
	private $flow_type;

	/**
	 * 流程資料（例如 LINE profile）
	 *
	 * @var array
	 */
	private $data;

	/**
	 * 建構子
	 *
	 * @param string $flow_type 流程類型（FLOW_REGISTER/FLOW_LOGIN/FLOW_LINK）
	 * @param array  $data 流程資料（LINE profile 等）
	 */
	public function __construct( string $flow_type, array $data = array() ) {
		$this->flow_type = $flow_type;
		$this->data      = $data;
		parent::__construct( 'Continue page render: ' . $flow_type );
	}

	/**
	 * 取得流程類型
	 *
	 * @return string 流程類型
	 */
	public function getFlowType(): string {
		return $this->flow_type;
	}

	/**
	 * 取得流程資料
	 *
	 * @return array 流程資料
	 */
	public function getData(): array {
		return $this->data;
	}
}
