<?php

namespace BuygoLineNotify;

use BuygoLineNotify\Services\ImageUploader;
use BuygoLineNotify\Services\LineMessagingService;
use BuygoLineNotify\Services\SettingsService;
use BuygoLineNotify\Services\Logger;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * BuygoLineNotify Facade
 *
 * 提供統一的 API 供其他外掛使用
 * 例如：BuygoLineNotify\ImageUploader::download_and_upload()
 */
class BuygoLineNotify
{
    /**
     * 取得 ImageUploader 實例
     *
     * @param string|null $channel_access_token Channel Access Token（選填，若未填則自動取得）
     * @return ImageUploader
     */
    public static function image_uploader($channel_access_token = null)
    {
        if ($channel_access_token === null) {
            $channel_access_token = SettingsService::get('channel_access_token', '');
        }

        return new ImageUploader($channel_access_token);
    }

    /**
     * 取得 LineMessagingService 實例
     *
     * @param string|null $channel_access_token Channel Access Token（選填，若未填則自動取得）
     * @return LineMessagingService
     */
    public static function messaging($channel_access_token = null)
    {
        if ($channel_access_token === null) {
            $channel_access_token = SettingsService::get('channel_access_token', '');
        }

        return new LineMessagingService($channel_access_token);
    }

    /**
     * 取得 SettingsService 類別（靜態方法）
     *
     * @return string SettingsService 類別名稱
     */
    public static function settings()
    {
        return SettingsService::class;
    }

    /**
     * 取得 Logger 實例
     *
     * @deprecated Logger::get_instance() 不存在，請使用 Logger::log_placeholder() 代替
     * @return null
     */
    public static function logger()
    {
        return null; // Deprecated: Logger::get_instance() does not exist
    }

    /**
     * 檢查外掛是否已啟用
     *
     * @return bool
     */
    public static function is_active(): bool
    {
        return class_exists('\BuygoLineNotify\Plugin');
    }
}
