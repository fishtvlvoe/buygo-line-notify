<?php
/**
 * 自動更新器類別
 * 透過 GitHub Releases API 檢查並提供外掛更新
 *
 * @package BuygoLineNotify
 */

namespace BuygoLineNotify;

if (!defined('ABSPATH')) {
    exit;
}

class Updater {
    /**
     * GitHub 使用者名稱
     * @var string
     */
    private $username;

    /**
     * GitHub Repository 名稱
     * @var string
     */
    private $repository;

    /**
     * 外掛主檔案路徑
     * @var string
     */
    private $plugin_file;

    /**
     * 外掛 slug（目錄名稱）
     * @var string
     */
    private $plugin_slug;

    /**
     * 快取鍵名
     * @var string
     */
    private $cache_key;

    /**
     * 快取時間（秒）
     * @var int
     */
    private $cache_allowed;

    /**
     * 初始化更新器
     *
     * @param string $plugin_file 外掛主檔案的絕對路徑
     * @param string $username GitHub 使用者名稱
     * @param string $repository GitHub Repository 名稱
     */
    public function __construct($plugin_file, $username, $repository) {
        $this->plugin_file = $plugin_file;
        $this->username = $username;
        $this->repository = $repository;
        $this->plugin_slug = plugin_basename($plugin_file);
        $this->cache_key = 'buygo_line_notify_update_' . md5($this->plugin_slug);
        $this->cache_allowed = 12 * HOUR_IN_SECONDS; // 12 小時

        // 註冊更新檢查 hooks
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        add_filter('upgrader_post_install', [$this, 'after_install'], 10, 3);

        // 清除快取（開發用）
        add_action('admin_init', [$this, 'maybe_clear_cache']);
    }

    /**
     * 檢查更新
     *
     * @param object $transient 外掛更新 transient
     * @return object
     */
    public function check_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        // 取得遠端版本資訊
        $remote = $this->get_remote_info();

        if ($remote && version_compare(BuygoLineNotify_PLUGIN_VERSION, $remote->version, '<')) {
            $plugin = [
                'slug' => dirname($this->plugin_slug),
                'plugin' => $this->plugin_slug,
                'new_version' => $remote->version,
                'url' => $remote->homepage,
                'package' => $remote->download_url,
                'icons' => [],
                'banners' => [],
                'banners_rtl' => [],
                'tested' => $remote->tested,
                'requires_php' => $remote->requires_php,
                'compatibility' => new \stdClass(),
            ];

            $transient->response[$this->plugin_slug] = (object) $plugin;
        }

        return $transient;
    }

    /**
     * 提供外掛詳細資訊
     *
     * @param false|object|array $result 預設結果
     * @param string $action API 動作
     * @param object $args 查詢參數
     * @return false|object
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if ($args->slug !== dirname($this->plugin_slug)) {
            return $result;
        }

        $remote = $this->get_remote_info();

        if (!$remote) {
            return $result;
        }

        $result = (object) [
            'name' => $remote->name,
            'slug' => dirname($this->plugin_slug),
            'version' => $remote->version,
            'tested' => $remote->tested,
            'requires' => $remote->requires,
            'requires_php' => $remote->requires_php,
            'author' => $remote->author,
            'author_profile' => $remote->author_profile,
            'download_link' => $remote->download_url,
            'trunk' => $remote->download_url,
            'last_updated' => $remote->last_updated,
            'sections' => [
                'description' => $remote->sections->description,
                'installation' => $remote->sections->installation,
                'changelog' => $remote->sections->changelog,
            ],
            'banners' => [],
            'icons' => [],
        ];

        return $result;
    }

    /**
     * 安裝後處理
     *
     * @param bool $true 安裝結果
     * @param array $hook_extra 額外資訊
     * @param array $result 安裝結果
     * @return bool
     */
    public function after_install($true, $hook_extra, $result) {
        global $wp_filesystem;

        $plugin_folder = WP_PLUGIN_DIR . '/' . dirname($this->plugin_slug);
        $wp_filesystem->move($result['destination'], $plugin_folder);
        $result['destination'] = $plugin_folder;

        if ($this->plugin_slug === $hook_extra['plugin']) {
            activate_plugin($this->plugin_slug);
        }

        return $true;
    }

    /**
     * 從 GitHub Releases 取得最新版本資訊
     *
     * @return object|false
     */
    private function get_remote_info() {
        // 檢查快取
        $remote = get_transient($this->cache_key);

        if ($remote !== false) {
            return $remote;
        }

        // 從 GitHub API 取得最新 release
        $api_url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $this->username,
            $this->repository
        );

        $response = wp_remote_get($api_url, [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
            ],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (empty($data)) {
            return false;
        }

        // 尋找 .zip 檔案
        $download_url = '';
        if (!empty($data->assets)) {
            foreach ($data->assets as $asset) {
                if (strpos($asset->name, '.zip') !== false) {
                    $download_url = $asset->browser_download_url;
                    break;
                }
            }
        }

        // 如果沒有 .zip asset，使用 zipball_url
        if (empty($download_url) && !empty($data->zipball_url)) {
            $download_url = $data->zipball_url;
        }

        // 解析 changelog
        $changelog = $this->parse_changelog($data->body);

        $remote_data = (object) [
            'version' => ltrim($data->tag_name, 'v'),
            'name' => 'Buygo Line Notify',
            'slug' => dirname($this->plugin_slug),
            'homepage' => "https://github.com/{$this->username}/{$this->repository}",
            'download_url' => $download_url,
            'tested' => '6.4',
            'requires' => '5.8',
            'requires_php' => '7.4',
            'author' => $this->username,
            'author_profile' => "https://github.com/{$this->username}",
            'last_updated' => $data->published_at,
            'sections' => (object) [
                'description' => 'BuyGo Line Notify 外掛 - 整合 LINE 通知功能',
                'installation' => '1. 上傳外掛到 wp-content/plugins/ 目錄<br>2. 在 WordPress 後台啟用外掛<br>3. 設定 LINE Channel 相關參數',
                'changelog' => $changelog,
            ],
        ];

        // 快取 12 小時
        set_transient($this->cache_key, $remote_data, $this->cache_allowed);

        return $remote_data;
    }

    /**
     * 解析 changelog（從 GitHub Release body）
     *
     * @param string $body Release body
     * @return string HTML 格式的 changelog
     */
    private function parse_changelog($body) {
        if (empty($body)) {
            return '<h4>最新版本</h4><p>請查看 GitHub Releases 頁面以了解更新內容。</p>';
        }

        // 將 Markdown 轉換為簡單的 HTML
        $changelog = '<h4>更新內容</h4>';
        $changelog .= '<div style="white-space: pre-wrap;">' . esc_html($body) . '</div>';

        return $changelog;
    }

    /**
     * 清除更新快取（開發用）
     * 在 URL 加上 ?clear_update_cache=1 即可清除
     */
    public function maybe_clear_cache() {
        if (isset($_GET['clear_update_cache']) && current_user_can('manage_options')) {
            // 檢查是否正在進行外掛啟用操作
            // 在外掛啟用期間執行 exit 會中斷啟用流程，導致外掛被自動停用
            if (isset($_GET['action']) && ($_GET['action'] === 'activate' || $_GET['action'] === 'activate-selected')) {
                return; // 跳過 redirect，避免中斷啟用流程
            }

            delete_transient($this->cache_key);
            wp_redirect(remove_query_arg('clear_update_cache'));
            exit;
        }
    }
}
