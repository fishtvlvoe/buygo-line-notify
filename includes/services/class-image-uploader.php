<?php

namespace BuygoLineNotify\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ImageUploader Service
 *
 * 負責從 LINE 下載圖片並上傳到 WordPress
 * 從 buygo-plus-one-dev 遷移而來
 */
class ImageUploader
{
    /**
     * LINE Channel Access Token
     *
     * @var string
     */
    private $channel_access_token;

    /**
     * Logger instance
     *
     * @var Logger|null
     */
    private $logger;

    /**
     * Constructor
     *
     * @param string $channel_access_token Channel Access Token
     */
    public function __construct($channel_access_token)
    {
        $this->channel_access_token = $channel_access_token;
        $this->logger = Logger::get_instance();
    }

    /**
     * 下載並上傳圖片
     *
     * @param string $message_id LINE 訊息 ID
     * @param int $user_id WordPress 使用者 ID
     * @return int|\WP_Error 附件 ID 或錯誤
     */
    public function download_and_upload($message_id, $user_id)
    {
        // Validate user_id
        if ($user_id <= 0) {
            $this->log('error', [
                'message' => 'Invalid user_id provided to download_and_upload',
                'message_id' => $message_id,
                'user_id' => $user_id,
            ]);
            return new \WP_Error('invalid_user_id', 'Invalid user ID');
        }

        $this->log('image_download_start', [
            'message_id' => $message_id,
            'user_id' => $user_id,
        ]);

        // 1. 從 LINE 下載圖片
        $image_data = $this->download_from_line($message_id);

        if (is_wp_error($image_data)) {
            return $image_data;
        }

        // 2. 上傳到 WordPress
        $attachment_id = $this->upload_to_wordpress($image_data, $user_id);

        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }

        // 3. 暫存圖片 ID
        $this->store_temp_image($user_id, $attachment_id);

        $this->log('image_uploaded_success', [
            'attachment_id' => $attachment_id,
        ]);

        return $attachment_id;
    }

    /**
     * 從 LINE 下載圖片
     *
     * @param string $message_id LINE 訊息 ID
     * @return string|\WP_Error 圖片資料或錯誤
     */
    private function download_from_line($message_id)
    {
        if (empty($this->channel_access_token)) {
            return new \WP_Error('no_token', 'Channel Access Token 未設定');
        }

        $url = "https://api-data.line.me/v2/bot/message/{$message_id}/content";

        $this->log('image_download_line', [
            'url' => $url,
            'token_length' => strlen($this->channel_access_token),
        ]);

        $response = wp_remote_get(
            $url,
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->channel_access_token,
                ],
                'timeout' => 30,
            ]
        );

        if (is_wp_error($response)) {
            $this->log('error', [
                'message' => 'Failed to download from LINE',
                'error' => $response->get_error_message(),
            ]);
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $this->log('error', [
                'message' => 'LINE API error',
                'status_code' => $status_code,
                'response' => wp_remote_retrieve_body($response),
            ]);
            return new \WP_Error('line_api_error', 'LINE API 錯誤：' . $status_code);
        }

        $image_data = wp_remote_retrieve_body($response);

        if (empty($image_data)) {
            return new \WP_Error('empty_image', '圖片資料為空');
        }

        $this->log('image_downloaded', [
            'size' => strlen($image_data),
        ]);

        return $image_data;
    }

    /**
     * 上傳到 WordPress
     *
     * @param string $image_data 圖片資料
     * @param int $user_id WordPress 使用者 ID
     * @return int|\WP_Error 附件 ID 或錯誤
     */
    private function upload_to_wordpress($image_data, $user_id)
    {
        // 取得上傳目錄
        $upload_dir = wp_upload_dir();

        if (!empty($upload_dir['error'])) {
            return new \WP_Error('upload_dir_error', $upload_dir['error']);
        }

        // 生成檔案名稱
        $filename = 'line-product-' . time() . '-' . $user_id . '.jpg';
        $file_path = $upload_dir['path'] . '/' . $filename;

        // 儲存檔案
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        $result = file_put_contents($file_path, $image_data);

        if (false === $result) {
            return new \WP_Error('file_write_error', '無法寫入檔案');
        }

        $this->log('image_file_saved', ['path' => $file_path]);

        // 取得檔案類型
        $filetype = wp_check_filetype($filename, null);

        // 驗證 user_id，確保不是 0
        $original_user_id = $user_id;
        if ($user_id <= 0) {
            $admin_users = get_users([
                'role' => 'administrator',
                'number' => 1,
            ]);

            if (!empty($admin_users)) {
                $user_id = $admin_users[0]->ID;
            } else {
                $user_id = 1;
            }

            $this->log('warning', [
                'message' => 'Invalid user_id provided for image upload, using default admin',
                'provided_user_id' => $original_user_id,
                'fallback_user_id' => $user_id,
            ]);
        }

        // 建立附件
        $attachment = [
            'post_mime_type' => $filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_author' => $user_id,
        ];

        $attachment_id = wp_insert_attachment($attachment, $file_path);

        if (is_wp_error($attachment_id)) {
            // 刪除已上傳的檔案
            // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
            unlink($file_path);
            return $attachment_id;
        }

        // 生成縮圖
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
        wp_update_attachment_metadata($attachment_id, $attach_data);

        $this->log('image_attachment_created', [
            'attachment_id' => $attachment_id,
            'metadata' => $attach_data,
        ]);

        return $attachment_id;
    }

    /**
     * 暫存圖片 ID
     *
     * @param int $user_id WordPress 使用者 ID
     * @param int $attachment_id 附件 ID
     */
    private function store_temp_image($user_id, $attachment_id)
    {
        $temp_images = get_user_meta($user_id, '_buygo_temp_images', true);

        if (!is_array($temp_images)) {
            $temp_images = [];
        }

        $temp_images[] = $attachment_id;

        update_user_meta($user_id, '_buygo_temp_images', $temp_images);

        $this->log('image_stored_temp', [
            'attachment_id' => $attachment_id,
            'total_images' => count($temp_images),
        ]);
    }

    /**
     * 取得暫存圖片
     *
     * @param int $user_id WordPress 使用者 ID
     * @return array
     */
    public function get_temp_images($user_id)
    {
        $temp_images = get_user_meta($user_id, '_buygo_temp_images', true);
        return is_array($temp_images) ? $temp_images : [];
    }

    /**
     * 清除暫存圖片
     *
     * @param int $user_id WordPress 使用者 ID
     */
    public function clear_temp_images($user_id)
    {
        delete_user_meta($user_id, '_buygo_temp_images');

        $this->log('temp_images_cleared', [], $user_id);
    }

    /**
     * Log message
     *
     * @param string $level Log level
     * @param array $data Log data
     * @param int|null $user_id User ID (optional)
     */
    private function log($level, $data, $user_id = null)
    {
        if ($this->logger) {
            $this->logger->log($level, $data, $user_id);
        }
    }
}
