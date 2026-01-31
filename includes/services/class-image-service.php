<?php
/**
 * Image Service
 *
 * 處理 LINE 圖片下載與儲存到 WordPress Media Library
 *
 * @package BuygoLineNotify
 */

namespace BuygoLineNotify\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ImageService
 *
 * LINE 圖片處理服務（基礎設施層）
 */
class ImageService
{
    /**
     * LINE Content API endpoint
     */
    const CONTENT_API_ENDPOINT = 'https://api-data.line.me/v2/bot/message';

    /**
     * 下載 LINE 圖片並儲存到 WordPress Media Library
     *
     * @param string $message_id LINE Message ID
     * @param int|null $user_id  WordPress User ID (optional, for file naming)
     * @return int|\WP_Error 成功返回 attachment ID，失敗返回 WP_Error
     */
    public static function downloadToMediaLibrary(string $message_id, ?int $user_id = null)
    {
        // 取得 Channel Access Token
        $access_token = SettingsService::get('channel_access_token');
        if (empty($access_token)) {
            return new \WP_Error(
                'missing_access_token',
                'Channel Access Token 未設定'
            );
        }

        // 下載圖片內容
        $url = self::CONTENT_API_ENDPOINT . '/' . $message_id . '/content';
        $response = \wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
            'timeout' => 30,
        ]);

        // 檢查 HTTP 錯誤
        if (\is_wp_error($response)) {
            return new \WP_Error(
                'download_failed',
                'LINE 圖片下載失敗：' . $response->get_error_message()
            );
        }

        $status_code = \wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $response_body = \wp_remote_retrieve_body($response);
            return new \WP_Error(
                'download_failed',
                'LINE 圖片下載失敗：HTTP ' . $status_code,
                ['response' => $response_body]
            );
        }

        // 取得圖片內容
        $image_data = \wp_remote_retrieve_body($response);
        if (empty($image_data)) {
            return new \WP_Error(
                'empty_content',
                'LINE 圖片內容為空'
            );
        }

        // 偵測 MIME type
        $content_type = \wp_remote_retrieve_header($response, 'content-type');
        $extension = self::get_extension_from_mime($content_type);

        // 產生檔案名稱
        $filename = self::generate_filename($message_id, $user_id, $extension);

        // 儲存到 WordPress Media Library
        return self::save_to_media_library($image_data, $filename, $content_type);
    }

    /**
     * 從 MIME type 取得副檔名
     *
     * @param string $mime_type MIME type
     * @return string
     */
    private static function get_extension_from_mime(string $mime_type): string
    {
        $mime_map = [
            'image/jpeg' => 'jpg',
            'image/jpg'  => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];

        return $mime_map[$mime_type] ?? 'jpg';
    }

    /**
     * 產生檔案名稱
     *
     * @param string   $message_id LINE Message ID
     * @param int|null $user_id    WordPress User ID
     * @param string   $extension  副檔名
     * @return string
     */
    private static function generate_filename(string $message_id, ?int $user_id, string $extension): string
    {
        $timestamp = \current_time('YmdHis');
        $user_suffix = $user_id ? '_user' . $user_id : '';
        return 'line_' . $message_id . $user_suffix . '_' . $timestamp . '.' . $extension;
    }

    /**
     * 儲存圖片到 WordPress Media Library
     *
     * @param string $image_data   圖片二進位資料
     * @param string $filename     檔案名稱
     * @param string $content_type MIME type
     * @return int|\WP_Error 成功返回 attachment ID，失敗返回 WP_Error
     */
    private static function save_to_media_library(string $image_data, string $filename, string $content_type)
    {
        // 載入 WordPress 媒體處理函數
        if (!\function_exists('wp_upload_bits')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!\function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }
        if (!\function_exists('media_handle_sideload')) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        // 上傳圖片
        $upload = \wp_upload_bits($filename, null, $image_data);

        if (!empty($upload['error'])) {
            return new \WP_Error(
                'upload_failed',
                'WordPress 上傳失敗：' . $upload['error']
            );
        }

        $file_path = $upload['file'];
        $file_url = $upload['url'];

        // 建立 attachment
        $attachment = [
            'post_mime_type' => $content_type,
            'post_title'     => \sanitize_file_name(\pathinfo($filename, PATHINFO_FILENAME)),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attachment_id = \wp_insert_attachment($attachment, $file_path);

        if (\is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // 產生縮圖
        $attach_data = \wp_generate_attachment_metadata($attachment_id, $file_path);
        \wp_update_attachment_metadata($attachment_id, $attach_data);

        return $attachment_id;
    }

    /**
     * 取得 attachment 的 URL
     *
     * @param int $attachment_id Attachment ID
     * @return string|false
     */
    public static function get_attachment_url(int $attachment_id)
    {
        return \wp_get_attachment_url($attachment_id);
    }
}
