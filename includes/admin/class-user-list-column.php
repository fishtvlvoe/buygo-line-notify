<?php
/**
 * User List Column
 *
 * 在 WordPress 用戶列表加入 LINE 綁定狀態欄位
 *
 * @package BuygoLineNotify
 */

namespace BuygoLineNotify\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class UserListColumn
 *
 * 管理 WordPress 用戶列表的 LINE 綁定欄位
 */
class UserListColumn
{
    /**
     * 初始化 hooks
     */
    public static function init(): void
    {
        // 新增「LINE 綁定」欄位
        \add_filter('manage_users_columns', [self::class, 'add_line_column']);

        // 顯示欄位內容
        \add_filter('manage_users_custom_column', [self::class, 'render_line_column'], 10, 3);

        // 設定欄位可排序
        \add_filter('manage_users_sortable_columns', [self::class, 'make_line_column_sortable']);

        // 處理排序邏輯
        \add_action('pre_get_users', [self::class, 'handle_line_column_sorting']);

        // 新增篩選器
        \add_action('restrict_manage_users', [self::class, 'add_line_filter']);

        // 處理篩選邏輯
        \add_filter('pre_get_users', [self::class, 'handle_line_filter']);

        // 載入樣式
        \add_action('admin_head-users.php', [self::class, 'add_column_styles']);
    }

    /**
     * 新增「LINE 綁定」欄位到用戶列表
     *
     * @param array $columns 現有欄位
     * @return array
     */
    public static function add_line_column(array $columns): array
    {
        // 在 email 欄位後插入 LINE 綁定欄位
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'email') {
                $new_columns['line_binding'] = 'LINE 綁定';
            }
        }
        return $new_columns;
    }

    /**
     * 渲染「LINE 綁定」欄位內容
     *
     * @param string $output 輸出內容
     * @param string $column_name 欄位名稱
     * @param int $user_id 用戶 ID
     * @return string
     */
    public static function render_line_column(string $output, string $column_name, int $user_id): string
    {
        if ($column_name !== 'line_binding') {
            return $output;
        }

        // 檢查是否已綁定 LINE
        $is_linked = \BuygoLineNotify\Services\LineUserService::isUserLinked($user_id);

        if (!$is_linked) {
            return '<span style="color: #999;">—</span>';
        }

        // 取得 LINE 資料
        $line_data = \BuygoLineNotify\Services\LineUserService::getUser($user_id);
        if (!$line_data) {
            return '<span style="color: #999;">—</span>';
        }

        // 取得頭像和名稱
        $display_name = \get_user_meta($user_id, 'buygo_line_display_name', true);
        $avatar_url = \get_user_meta($user_id, 'buygo_line_avatar_url', true);

        $output = '<div style="display: flex; align-items: center; gap: 8px;">';

        // 顯示頭像
        if ($avatar_url) {
            $output .= '<img src="' . esc_url($avatar_url) . '"
                            alt="LINE Avatar"
                            style="width: 32px; height: 32px; border-radius: 50%; border: 2px solid #06C755;">';
        }

        // 顯示名稱
        $output .= '<div style="display: flex; flex-direction: column;">';
        $output .= '<span style="color: #06C755; font-weight: 500;">✓ ' . esc_html($display_name ?: 'LINE 用戶') . '</span>';

        // 顯示綁定日期
        if (!empty($line_data['link_date'])) {
            $link_date = date_i18n('Y-m-d', strtotime($line_data['link_date']));
            $output .= '<span style="font-size: 11px; color: #666;">綁定於 ' . esc_html($link_date) . '</span>';
        }

        $output .= '</div>';
        $output .= '</div>';

        return $output;
    }

    /**
     * 設定「LINE 綁定」欄位可排序
     *
     * @param array $columns 可排序欄位
     * @return array
     */
    public static function make_line_column_sortable(array $columns): array
    {
        $columns['line_binding'] = 'line_binding';
        return $columns;
    }

    /**
     * 處理「LINE 綁定」欄位排序
     *
     * @param \WP_User_Query $query
     */
    public static function handle_line_column_sorting(\WP_User_Query $query): void
    {
        if (!is_admin()) {
            return;
        }

        $orderby = $query->get('orderby');

        if ($orderby !== 'line_binding') {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_line_users';

        // 使用 LEFT JOIN 讓未綁定用戶也能正確排序
        $query->query_from .= " LEFT JOIN {$table_name} AS line_users ON {$wpdb->users}.ID = line_users.user_id";

        // 按綁定狀態排序（已綁定優先或未綁定優先）
        $order = $query->get('order') ?: 'ASC';
        $query->query_orderby = " ORDER BY (line_users.user_id IS NOT NULL) {$order}, {$wpdb->users}.ID {$order}";
    }

    /**
     * 新增「LINE 綁定」篩選器
     */
    public static function add_line_filter(): void
    {
        // 只在用戶列表頁面顯示
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'users') {
            return;
        }

        $selected = isset($_GET['line_binding_filter']) ? sanitize_text_field($_GET['line_binding_filter']) : '';

        echo '<select name="line_binding_filter" style="float: none;">';
        echo '<option value="">所有 LINE 綁定狀態</option>';
        echo '<option value="linked"' . selected($selected, 'linked', false) . '>已綁定 LINE</option>';
        echo '<option value="not_linked"' . selected($selected, 'not_linked', false) . '>未綁定 LINE</option>';
        echo '</select>';
    }

    /**
     * 處理「LINE 綁定」篩選邏輯
     *
     * @param \WP_User_Query $query
     */
    public static function handle_line_filter(\WP_User_Query $query): void
    {
        if (!is_admin()) {
            return;
        }

        $filter = isset($_GET['line_binding_filter']) ? sanitize_text_field($_GET['line_binding_filter']) : '';

        if (empty($filter) || !in_array($filter, ['linked', 'not_linked'])) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_line_users';

        if ($filter === 'linked') {
            // 只顯示已綁定 LINE 的用戶
            $query->query_from .= " INNER JOIN {$table_name} AS line_users ON {$wpdb->users}.ID = line_users.user_id";
        } elseif ($filter === 'not_linked') {
            // 只顯示未綁定 LINE 的用戶
            $query->query_from .= " LEFT JOIN {$table_name} AS line_users ON {$wpdb->users}.ID = line_users.user_id";
            $query->query_where .= " AND line_users.user_id IS NULL";
        }
    }

    /**
     * 載入欄位樣式
     */
    public static function add_column_styles(): void
    {
        echo '<style>
            .column-line_binding {
                width: 180px;
            }
            .column-line_binding img {
                object-fit: cover;
            }
        </style>';
    }
}
