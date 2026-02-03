<?php
/**
 * Migration Service
 *
 * 負責從其他 LINE 外掛同步綁定資料到 buygo-line-notify
 * 確保與其他外掛共存，自動發現並同步外部綁定資料
 *
 * @package BuygoLineNotify
 */

namespace BuygoLineNotify\Services;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Migration Service class
 *
 * 自動從以下來源同步 LINE 綁定資料：
 * 1. wp_buygo_line_bindings（buygo-plus-one 舊表）
 * 2. wp_social_users（Nextend Social Login）
 * 3. user_meta 中的 LINE 資料（各種外掛）
 *
 * 同步策略：
 * - 只同步尚未存在的綁定（避免重複）
 * - 使用 WordPress option 記錄已同步的版本
 * - 外掛啟動時自動執行一次
 * - 可手動觸發同步（透過 admin action）
 */
class MigrationService
{
    /**
     * Option key for tracking migration version
     */
    private const MIGRATION_VERSION_KEY = 'buygo_line_notify_migration_version';

    /**
     * Current migration version
     */
    private const CURRENT_VERSION = '1.0';

    /**
     * Target table name
     */
    private static function get_target_table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'buygo_line_users';
    }

    /**
     * 檢查是否需要執行同步
     *
     * @return bool
     */
    public static function needsMigration(): bool
    {
        $current_version = get_option(self::MIGRATION_VERSION_KEY, '0');
        return version_compare($current_version, self::CURRENT_VERSION, '<');
    }

    /**
     * 執行完整同步
     *
     * @return array 同步結果統計
     */
    public static function runMigration(): array
    {
        $stats = [
            'synced' => 0,
            'skipped' => 0,
            'errors' => 0,
            'sources' => [],
        ];

        // 同步來源 1: wp_buygo_line_bindings
        $result1 = self::syncFromBuygoLineBindings();
        $stats['synced'] += $result1['synced'];
        $stats['skipped'] += $result1['skipped'];
        $stats['errors'] += $result1['errors'];
        $stats['sources']['buygo_line_bindings'] = $result1;

        // 同步來源 2: wp_social_users (Nextend Social Login)
        $result2 = self::syncFromNextendSocialLogin();
        $stats['synced'] += $result2['synced'];
        $stats['skipped'] += $result2['skipped'];
        $stats['errors'] += $result2['errors'];
        $stats['sources']['social_users'] = $result2;

        // 同步來源 3: user_meta
        $result3 = self::syncFromUserMeta();
        $stats['synced'] += $result3['synced'];
        $stats['skipped'] += $result3['skipped'];
        $stats['errors'] += $result3['errors'];
        $stats['sources']['user_meta'] = $result3;

        // 更新遷移版本
        update_option(self::MIGRATION_VERSION_KEY, self::CURRENT_VERSION);

        return $stats;
    }

    /**
     * 從 wp_buygo_line_bindings 同步（buygo-plus-one 舊表）
     *
     * @return array
     */
    private static function syncFromBuygoLineBindings(): array
    {
        global $wpdb;

        $stats = ['synced' => 0, 'skipped' => 0, 'errors' => 0];
        $source_table = $wpdb->prefix . 'buygo_line_bindings';
        $target_table = self::get_target_table();

        // 檢查來源表是否存在
        $exists = $wpdb->get_var("SHOW TABLES LIKE '{$source_table}'");
        if (!$exists) {
            return $stats;
        }

        // 取得已完成的綁定
        $bindings = $wpdb->get_results("
            SELECT user_id, line_uid, created_at
            FROM {$source_table}
            WHERE status = 'completed'
            ORDER BY created_at ASC
        ");

        if (!$bindings) {
            return $stats;
        }

        foreach ($bindings as $binding) {
            $result = self::insertBinding($binding->user_id, $binding->line_uid, $binding->created_at);

            if ($result === 'synced') {
                $stats['synced']++;
            } elseif ($result === 'skipped') {
                $stats['skipped']++;
            } else {
                $stats['errors']++;
            }
        }

        return $stats;
    }

    /**
     * 從 wp_social_users 同步（Nextend Social Login）
     *
     * @return array
     */
    private static function syncFromNextendSocialLogin(): array
    {
        global $wpdb;

        $stats = ['synced' => 0, 'skipped' => 0, 'errors' => 0];
        $source_table = $wpdb->prefix . 'social_users';
        $target_table = self::get_target_table();

        // 檢查來源表是否存在
        $exists = $wpdb->get_var("SHOW TABLES LIKE '{$source_table}'");
        if (!$exists) {
            return $stats;
        }

        // 取得 LINE 登入記錄
        $bindings = $wpdb->get_results("
            SELECT ID as user_id, identifier as line_uid, date_added
            FROM {$source_table}
            WHERE provider = 'line'
            ORDER BY date_added ASC
        ");

        if (!$bindings) {
            return $stats;
        }

        foreach ($bindings as $binding) {
            $result = self::insertBinding($binding->user_id, $binding->line_uid, $binding->date_added);

            if ($result === 'synced') {
                $stats['synced']++;
            } elseif ($result === 'skipped') {
                $stats['skipped']++;
            } else {
                $stats['errors']++;
            }
        }

        return $stats;
    }

    /**
     * 從 user_meta 同步
     *
     * @return array
     */
    private static function syncFromUserMeta(): array
    {
        global $wpdb;

        $stats = ['synced' => 0, 'skipped' => 0, 'errors' => 0];

        // 可能的 meta_key 列表
        $meta_keys = ['line_uid', 'buygo_line_uid'];

        foreach ($meta_keys as $meta_key) {
            $bindings = $wpdb->get_results($wpdb->prepare("
                SELECT user_id, meta_value as line_uid
                FROM {$wpdb->usermeta}
                WHERE meta_key = %s
                AND meta_value != ''
            ", $meta_key));

            if (!$bindings) {
                continue;
            }

            foreach ($bindings as $binding) {
                $result = self::insertBinding($binding->user_id, $binding->line_uid);

                if ($result === 'synced') {
                    $stats['synced']++;
                } elseif ($result === 'skipped') {
                    $stats['skipped']++;
                } else {
                    $stats['errors']++;
                }
            }
        }

        return $stats;
    }

    /**
     * 插入綁定資料（檢查重複）
     *
     * @param int $user_id WordPress User ID
     * @param string $line_uid LINE UID
     * @param string|null $link_date 綁定時間
     * @return string 'synced', 'skipped', 'error'
     */
    private static function insertBinding(int $user_id, string $line_uid, ?string $link_date = null): string
    {
        global $wpdb;
        $target_table = self::get_target_table();

        // 檢查是否已存在（透過 user_id 或 identifier）
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$target_table} WHERE user_id = %d OR identifier = %s",
            $user_id,
            $line_uid
        ));

        if ($exists) {
            return 'skipped';
        }

        // 插入新綁定（對齊 wp_buygo_line_users 表結構）
        // 欄位說明：
        // - type: 固定為 'line'
        // - identifier: LINE UID
        // - user_id: WordPress User ID
        // - register_date: 註冊時間
        // - link_date: 綁定時間
        $result = $wpdb->insert(
            $target_table,
            [
                'type' => 'line',
                'identifier' => $line_uid,
                'user_id' => $user_id,
                'register_date' => $link_date ?: current_time('mysql'),
                'link_date' => $link_date ?: current_time('mysql'),
            ],
            ['%s', '%s', '%d', '%s', '%s']
        );

        return $result ? 'synced' : 'error';
    }

    /**
     * 取得同步狀態報告
     *
     * @return array
     */
    public static function getStatus(): array
    {
        global $wpdb;

        $target_table = self::get_target_table();
        $current_version = get_option(self::MIGRATION_VERSION_KEY, '未同步');

        $total_bindings = $wpdb->get_var("SELECT COUNT(*) FROM {$target_table}");

        // 檢查各個來源表
        $sources = [];

        // buygo_line_bindings
        $source1 = $wpdb->prefix . 'buygo_line_bindings';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$source1}'")) {
            $sources['buygo_line_bindings'] = $wpdb->get_var("SELECT COUNT(*) FROM {$source1} WHERE status = 'completed'");
        }

        // social_users
        $source2 = $wpdb->prefix . 'social_users';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$source2}'")) {
            $sources['social_users'] = $wpdb->get_var("SELECT COUNT(*) FROM {$source2} WHERE provider = 'line'");
        }

        // user_meta
        $sources['user_meta'] = $wpdb->get_var(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta}
             WHERE meta_key IN ('line_uid', 'buygo_line_uid') AND meta_value != ''"
        );

        return [
            'migration_version' => $current_version,
            'needs_migration' => self::needsMigration(),
            'total_bindings' => (int) $total_bindings,
            'sources' => $sources,
        ];
    }
}
