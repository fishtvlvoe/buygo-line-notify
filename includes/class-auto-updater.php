<?php
/**
 * WordPress 外掛自動更新類別
 *
 * 從 Cloudflare Workers API 檢查更新
 *
 * @package BuygoLineNotify
 * @since 0.1.3
 */

namespace BuygoLineNotify;

if (!defined('ABSPATH')) {
    exit;
}

class Auto_Updater {
    /**
     * 外掛 slug
     * @var string
     */
    private $plugin_slug = 'buygo-line-notify';

    /**
     * 外掛檔案路徑
     * @var string
     */
    private $plugin_file = 'buygo-line-notify/buygo-line-notify.php';

    /**
     * Cloudflare Workers API URL
     * @var string
     */
    private $api_url;

    /**
     * 當前版本
     * @var string
     */
    private $version;

    /**
     * Transient 快取時間（秒）
     * @var int
     */
    private $cache_duration = 43200; // 12 小時

    /**
     * 建構函式
     *
     * @param string $version 當前外掛版本
     * @param string $api_url Cloudflare Workers API URL
     */
    public function __construct($version, $api_url = '') {
        $this->version = $version;

        // 預設 API URL
        $this->api_url = !empty($api_url)
            ? $api_url
            : 'https://buygo-plugin-updater.fishandy1213.workers.dev';

        // 允許透過常數覆寫 API URL
        if (defined('BUYGO_UPDATE_API_URL')) {
            $this->api_url = BUYGO_UPDATE_API_URL;
        }

        $this->init_hooks();
    }

    /**
     * 註冊 WordPress hooks
     */
    private function init_hooks() {
        // 檢查外掛更新
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);

        // 提供外掛詳細資訊
        add_filter('plugins_api', [$this, 'plugin_info'], 10, 3);

        // 清除快取（開發用）
        if (defined('WP_DEBUG') && WP_DEBUG && isset($_GET['clear_update_cache'])) {
            delete_transient('buygo_line_notify_update_check');
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success"><p>外掛更新快取已清除</p></div>';
            });
        }
    }

    /**
     * 檢查外掛更新
     *
     * @param object $transient WordPress update transient
     * @return object 更新後的 transient
     */
    public function check_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        // 檢查快取
        $cached = get_transient('buygo_line_notify_update_check');
        if ($cached !== false) {
            if (!empty($cached['has_update'])) {
                $transient->response[$this->plugin_file] = (object) $cached['update_data'];
            }
            return $transient;
        }

        // 呼叫 Cloudflare Workers API
        $response = wp_remote_get(
            add_query_arg([
                'version' => $this->version,
                'action' => 'update-check'
            ], $this->api_url . '/update/' . $this->plugin_slug),
            [
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'application/json'
                ]
            ]
        );

        // 處理錯誤
        if (is_wp_error($response)) {
            error_log('Buygo Line Notify 更新檢查失敗: ' . $response->get_error_message());
            return $transient;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('Buygo Line Notify 更新 API 回應錯誤: HTTP ' . $response_code);
            return $transient;
        }

        // 解析回應
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data)) {
            error_log('Buygo Line Notify 更新 API 回應格式錯誤');
            return $transient;
        }

        // 檢查是否已是最新版本
        if (!empty($data['up_to_date']) && $data['up_to_date'] === true) {
            // 快取「已是最新版本」的結果
            set_transient('buygo_line_notify_update_check', [
                'has_update' => false,
                'checked_at' => current_time('mysql')
            ], $this->cache_duration);

            return $transient;
        }

        // 有新版本可用
        if (!empty($data['version']) && version_compare($this->version, $data['version'], '<')) {
            $update_data = [
                'slug' => $this->plugin_slug,
                'plugin' => $this->plugin_file,
                'new_version' => $data['version'],
                'url' => $data['url'] ?? $data['homepage'] ?? '',
                'package' => $data['download_link'] ?? $data['package'] ?? '',
                'tested' => $data['tested'] ?? '',
                'requires' => $data['requires'] ?? '',
                'requires_php' => $data['requires_php'] ?? '',
            ];

            // 加入到 transient
            $transient->response[$this->plugin_file] = (object) $update_data;

            // 快取結果
            set_transient('buygo_line_notify_update_check', [
                'has_update' => true,
                'update_data' => $update_data,
                'checked_at' => current_time('mysql')
            ], $this->cache_duration);

            // 記錄日誌
            error_log(sprintf(
                'Buygo Line Notify 發現新版本: %s -> %s',
                $this->version,
                $data['version']
            ));
        }

        return $transient;
    }

    /**
     * 提供外掛詳細資訊
     *
     * 當使用者點擊「查看詳情」時呼叫
     *
     * @param false|object|array $result
     * @param string $action
     * @param object $args
     * @return false|object
     */
    public function plugin_info($result, $action, $args) {
        // 只處理 plugin_information 請求
        if ($action !== 'plugin_information') {
            return $result;
        }

        // 只處理我們的外掛
        if (empty($args->slug) || $args->slug !== $this->plugin_slug) {
            return $result;
        }

        // 呼叫 API
        $response = wp_remote_get(
            $this->api_url . '/info/' . $this->plugin_slug,
            [
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'application/json'
                ]
            ]
        );

        if (is_wp_error($response)) {
            return $result;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data)) {
            return $result;
        }

        // 轉換為 WordPress 外掛資訊格式
        return (object) [
            'name' => $data['name'] ?? 'Buygo Line Notify',
            'slug' => $this->plugin_slug,
            'version' => $data['version'] ?? $this->version,
            'author' => $data['author'] ?? 'BuyGo Team',
            'author_profile' => $data['homepage'] ?? '',
            'requires' => $data['requires'] ?? '5.8',
            'tested' => $data['tested'] ?? '6.4',
            'requires_php' => $data['requires_php'] ?? '7.4',
            'last_updated' => $data['last_updated'] ?? '',
            'homepage' => $data['homepage'] ?? '',
            'download_link' => $data['download_url'] ?? '',
            'sections' => [
                'description' => $data['description'] ?? 'BuyGo LINE 通知整合 - 提供 LINE 訊息通知功能',
                'changelog' => $this->get_changelog($data),
            ],
            'banners' => $data['banners'] ?? [],
            'icons' => $data['icons'] ?? [],
        ];
    }

    /**
     * 取得更新日誌
     *
     * @param array $data API 回應資料
     * @return string HTML 格式的更新日誌
     */
    private function get_changelog($data) {
        if (!empty($data['sections']['changelog'])) {
            return $data['sections']['changelog'];
        }

        $version = $data['version'] ?? $this->version;
        $release_url = $data['release_url'] ?? "https://github.com/fishtvlvoe/buygo-line-notify/releases/tag/v{$version}";

        return sprintf(
            '<h4>v%s</h4><p>詳細更新內容請查看 <a href="%s" target="_blank">GitHub Releases</a></p>',
            esc_html($version),
            esc_url($release_url)
        );
    }

    /**
     * 手動檢查更新（開發用）
     *
     * @return array|WP_Error
     */
    public function manual_check() {
        delete_transient('buygo_line_notify_update_check');

        $response = wp_remote_get(
            add_query_arg([
                'version' => $this->version,
                'action' => 'update-check'
            ], $this->api_url . '/update/' . $this->plugin_slug),
            ['timeout' => 10]
        );

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }
}
