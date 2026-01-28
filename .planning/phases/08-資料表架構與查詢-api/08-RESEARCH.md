# Phase 8: 資料表架構與查詢 API - Research

**Researched:** 2026-01-29
**Domain:** WordPress Custom Database Tables, Data Migration, Query API Design
**Confidence:** HIGH

## Summary

本研究調查了建立 `wp_buygo_line_users` 專用資料表的最佳實踐，涵蓋資料表設計、資料遷移機制、查詢 API 實作。研究基於 Nextend Social Login 的 `wp_social_users` 表結構、WordPress 官方 `dbDelta()` 文件、以及現有 `buygo-line-notify` 外掛的資料庫實作。

重點發現：
1. **表結構對齊 Nextend**: 採用 `wp_social_users` 的欄位設計（ID, type, identifier, user_id, register_date, link_date），確保架構純粹性
2. **資料遷移使用版本追蹤**: 透過 `wp_options` 儲存資料庫版本號（`buygo_line_db_version`），比對後執行遷移
3. **dbDelta 限制**: `dbDelta()` 只能新增欄位/索引，無法刪除或重新命名；複雜遷移需使用 `ALTER TABLE`
4. **遷移策略**: 舊表 `wp_buygo_line_bindings` 資料遷移後保留（不刪除），記錄遷移狀態到 `buygo_line_migration_status`
5. **查詢 API 設計**: 使用靜態方法模式（參考 LineUserService），提供 `getUserByLineUid`、`getLineUidByUserId`、`isUserLinked`、`linkUser`、`unlinkUser` 五個核心方法

**Primary recommendation:** 建立 `wp_buygo_line_users` 專用表，對齊 Nextend `wp_social_users` 結構；使用版本追蹤 + `dbDelta()` 建表；實作資料遷移腳本將 `wp_buygo_line_bindings` 資料搬移；提供靜態方法查詢 API；遷移後保留舊表避免資料遺失。

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress dbDelta | 內建 | 資料表建立與升級 | WordPress 官方推薦的資料表建立/修改函數，自動處理 schema 比對 |
| WordPress $wpdb | 內建 | 資料庫查詢 | WordPress 全域資料庫抽象層，支援 prepare() 防止 SQL injection |
| WordPress Options API | 內建 | 版本追蹤與遷移狀態 | 標準設定儲存機制，用於記錄 `buygo_line_db_version` 和 `buygo_line_migration_status` |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| WordPress Transient API | 內建 | 查詢快取 | 高頻查詢結果快取（如 `isUserLinked` 結果） |
| WordPress User API | 內建 | 用戶驗證 | 確認 `user_id` 對應的 WordPress 用戶存在 |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| 專用表 | user_meta 混合儲存 | user_meta 查詢快但無歷史記錄；專用表提供完整綁定/解綁歷史 |
| dbDelta | 原生 CREATE TABLE | dbDelta 自動處理 schema 比對，原生 SQL 需自行判斷表是否存在 |
| 靜態方法 | 單例模式 | 靜態方法簡單直接，單例適合需要狀態的 service |

**Installation:**
```bash
# 無需安裝，全部使用 WordPress 內建功能
# 資料表在外掛啟動時自動建立（Database::init()）
```

## Architecture Patterns

### Recommended Project Structure
```
includes/
├── class-database.php           # 資料表建立與遷移（修改現有檔案）
├── class-plugin.php             # 外掛初始化（呼叫 Database::init()）
└── services/
    └── class-line-user-service.php  # 查詢 API（修改現有檔案）
```

### Pattern 1: 版本追蹤資料庫升級

**What:** 使用 `wp_options` 儲存資料庫版本號，啟動時比對並執行升級
**When to use:** 任何需要建立或修改資料表的外掛
**Example:**
```php
// Source: WordPress Plugin Database Migration Best Practices
// https://wpmayor.com/how-to-write-a-plugin-upgrade-routine/

class Database {
    /**
     * 目前的資料庫 schema 版本
     */
    const DB_VERSION = '2.0.0';

    /**
     * 初始化資料庫
     *
     * 比對版本號，若不同則執行升級
     */
    public static function init(): void {
        $current_version = get_option('buygo_line_db_version', '0.0.0');

        if (version_compare($current_version, self::DB_VERSION, '<')) {
            self::upgrade($current_version);
            update_option('buygo_line_db_version', self::DB_VERSION);
        }
    }

    /**
     * 執行資料庫升級
     *
     * @param string $from_version 現有版本
     */
    private static function upgrade(string $from_version): void {
        // 建立新表（若不存在）
        self::create_tables();

        // 版本特定遷移
        if (version_compare($from_version, '2.0.0', '<')) {
            self::migrate_from_bindings_table();
        }
    }
}
```

**來源：** [How to Write a Plugin Upgrade Routine](https://wpmayor.com/how-to-write-a-plugin-upgrade-routine/), [WordPress Plugin Updates the Right Way](https://www.sitepoint.com/wordpress-plugin-updates-right-way/)

### Pattern 2: dbDelta 資料表建立

**What:** 使用 WordPress `dbDelta()` 函數建立或修改資料表
**When to use:** 建立自訂資料表時
**Example:**
```php
// Source: WordPress Developer Reference - dbDelta
// https://developer.wordpress.org/reference/functions/dbdelta/

private static function create_tables(): void {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'buygo_line_users';

    // dbDelta SQL 格式要求：
    // 1. 每個欄位獨立一行
    // 2. PRIMARY KEY 後必須有兩個空格
    // 3. 使用 KEY 而非 INDEX
    // 4. 不使用 IF NOT EXISTS
    $sql = "CREATE TABLE {$table_name} (
        ID bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        type varchar(20) NOT NULL DEFAULT 'line',
        identifier varchar(255) NOT NULL,
        user_id bigint(20) UNSIGNED NOT NULL,
        register_date datetime DEFAULT NULL,
        link_date datetime DEFAULT NULL,
        PRIMARY KEY  (ID),
        UNIQUE KEY identifier (identifier),
        KEY user_id (user_id),
        KEY type (type)
    ) {$charset_collate};";

    dbDelta($sql);
}
```

**重要格式要求：**
- `PRIMARY KEY  (ID)` - 關鍵字後必須有兩個空格
- 使用 `KEY` 而非 `INDEX`
- 不使用 `IF NOT EXISTS`（dbDelta 會自動處理）
- 每個欄位獨立一行

**來源：** [WordPress dbDelta() Reference](https://developer.wordpress.org/reference/functions/dbdelta/)

### Pattern 3: 資料遷移（舊表到新表）

**What:** 從 `wp_buygo_line_bindings` 遷移資料到 `wp_buygo_line_users`
**When to use:** v2.0.0 升級時，有舊資料需要遷移
**Example:**
```php
// Source: 基於 buygo-plus-one-dev migrate_helpers_data() 模式

private static function migrate_from_bindings_table(): void {
    global $wpdb;

    $old_table = $wpdb->prefix . 'buygo_line_bindings';
    $new_table = $wpdb->prefix . 'buygo_line_users';

    // 檢查遷移狀態
    $migration_status = get_option('buygo_line_migration_status', []);
    if (!empty($migration_status['completed_at'])) {
        return; // 已遷移完成
    }

    // 檢查舊表是否存在
    $old_table_exists = $wpdb->get_var(
        "SHOW TABLES LIKE '{$old_table}'"
    ) === $old_table;

    if (!$old_table_exists) {
        // 舊表不存在，標記為無需遷移
        update_option('buygo_line_migration_status', [
            'status' => 'skipped',
            'reason' => 'old_table_not_found',
            'completed_at' => current_time('mysql'),
        ]);
        return;
    }

    // 讀取舊表資料
    $old_records = $wpdb->get_results(
        "SELECT * FROM {$old_table} WHERE status = 'active'"
    );

    $migrated_count = 0;
    $error_count = 0;
    $errors = [];

    foreach ($old_records as $record) {
        // 檢查是否已遷移（避免重複）
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$new_table} WHERE identifier = %s",
            $record->line_uid
        ));

        if ($exists) {
            continue;
        }

        // 遷移資料
        // 欄位對應：
        // - identifier = line_uid
        // - user_id = user_id
        // - register_date = created_at（首次綁定視為註冊）
        // - link_date = updated_at
        $result = $wpdb->insert(
            $new_table,
            [
                'type'          => 'line',
                'identifier'    => $record->line_uid,
                'user_id'       => $record->user_id,
                'register_date' => $record->created_at,
                'link_date'     => $record->updated_at ?? $record->created_at,
            ],
            ['%s', '%s', '%d', '%s', '%s']
        );

        if ($result) {
            $migrated_count++;
        } else {
            $error_count++;
            $errors[] = [
                'line_uid' => $record->line_uid,
                'error' => $wpdb->last_error,
            ];
        }
    }

    // 記錄遷移狀態（保留舊表，不刪除）
    update_option('buygo_line_migration_status', [
        'status'         => 'completed',
        'migrated_count' => $migrated_count,
        'error_count'    => $error_count,
        'errors'         => $errors,
        'old_table'      => $old_table,
        'new_table'      => $new_table,
        'completed_at'   => current_time('mysql'),
    ]);
}
```

**遷移原則：**
- 舊表保留不刪除（避免資料遺失）
- 記錄遷移狀態到 `wp_options`
- 處理可能的錯誤（記錄但繼續遷移其他資料）
- 使用 `$wpdb->prepare()` 防止 SQL injection

**來源：** 現有 `buygo-plus-one-dev/includes/class-database.php` 的 `migrate_helpers_data()` 模式

### Pattern 4: 查詢 API 靜態方法模式

**What:** 使用靜態方法提供查詢 API，無需實例化
**When to use:** 簡單的 CRUD 操作，無需維護狀態
**Example:**
```php
// Source: 現有 LineUserService 模式

class LineUserService {
    /**
     * 根據 LINE UID 取得 WordPress 用戶 ID
     *
     * @param string $line_uid LINE 用戶 ID
     * @return int|null WordPress User ID，未找到返回 null
     */
    public static function getUserByLineUid(string $line_uid): ?int {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_line_users';

        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$table_name}
             WHERE identifier = %s AND type = 'line'",
            $line_uid
        ));

        return $user_id ? (int) $user_id : null;
    }

    /**
     * 根據 WordPress User ID 取得 LINE UID
     *
     * @param int $user_id WordPress 用戶 ID
     * @return string|null LINE UID，未找到返回 null
     */
    public static function getLineUidByUserId(int $user_id): ?string {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_line_users';

        $line_uid = $wpdb->get_var($wpdb->prepare(
            "SELECT identifier FROM {$table_name}
             WHERE user_id = %d AND type = 'line'",
            $user_id
        ));

        return $line_uid ?: null;
    }

    /**
     * 檢查用戶是否已綁定 LINE
     *
     * @param int $user_id WordPress 用戶 ID
     * @return bool 是否已綁定
     */
    public static function isUserLinked(int $user_id): bool {
        return !is_null(self::getLineUidByUserId($user_id));
    }

    /**
     * 建立用戶與 LINE 的綁定關係
     *
     * @param int    $user_id WordPress 用戶 ID
     * @param string $line_uid LINE 用戶 ID
     * @param bool   $is_registration 是否為註冊（影響 register_date）
     * @return bool 是否成功
     */
    public static function linkUser(int $user_id, string $line_uid, bool $is_registration = false): bool {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_line_users';

        // 檢查 LINE UID 是否已綁定其他用戶
        $existing = self::getUserByLineUid($line_uid);
        if ($existing && $existing !== $user_id) {
            return false; // LINE UID 已綁定其他用戶
        }

        // 檢查用戶是否已綁定其他 LINE
        $existing_line = self::getLineUidByUserId($user_id);
        if ($existing_line && $existing_line !== $line_uid) {
            return false; // 用戶已綁定其他 LINE
        }

        $now = current_time('mysql');
        $data = [
            'type'       => 'line',
            'identifier' => $line_uid,
            'user_id'    => $user_id,
            'link_date'  => $now,
        ];

        if ($is_registration) {
            $data['register_date'] = $now;
        }

        // 使用 REPLACE INTO（若 identifier 重複則更新）
        $result = $wpdb->replace(
            $table_name,
            $data,
            ['%s', '%s', '%d', '%s', '%s']
        );

        return $result !== false;
    }

    /**
     * 解除綁定（軟刪除，保留歷史）
     *
     * @param int $user_id WordPress 用戶 ID
     * @return bool 是否成功
     */
    public static function unlinkUser(int $user_id): bool {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_line_users';

        // 軟刪除：清除 user_id 但保留 identifier 記錄
        // 或：完全刪除該記錄
        //
        // 採用完全刪除，因為：
        // 1. 新表不需要追蹤解綁歷史（與 Nextend wp_social_users 一致）
        // 2. 解綁後 LINE UID 可以綁定到其他用戶
        // 3. 若需要歷史記錄，應建立獨立的 audit log 表
        $result = $wpdb->delete(
            $table_name,
            ['user_id' => $user_id, 'type' => 'line'],
            ['%d', '%s']
        );

        return $result !== false;
    }
}
```

**API 設計原則：**
- 使用靜態方法（無需實例化）
- 參數類型明確（`int $user_id`, `string $line_uid`）
- 返回值清楚（`?int`, `?string`, `bool`）
- 使用 `$wpdb->prepare()` 防止 SQL injection

**來源：** 現有 `buygo-line-notify/includes/services/class-line-user-service.php`

### Anti-Patterns to Avoid

- **使用 IF NOT EXISTS 配合 dbDelta** - dbDelta 會忽略 IF NOT EXISTS，無法正確比對 schema
- **在 dbDelta SQL 中使用 INDEX** - 必須使用 KEY，INDEX 不被識別
- **PRIMARY KEY 後只有一個空格** - 必須是兩個空格，否則 dbDelta 無法識別
- **遷移時刪除舊表** - 應保留舊表避免資料遺失，可在確認無問題後手動刪除
- **不記錄遷移狀態** - 應記錄到 wp_options，方便追蹤和除錯
- **信任 client 提交的 LINE UID** - 必須從 LINE API 驗證後再寫入

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| 資料表建立 | 原生 CREATE TABLE + 手動檢查 | dbDelta() | dbDelta 自動比對 schema，處理升級 |
| SQL 參數化 | 字串拼接 | $wpdb->prepare() | 防止 SQL injection |
| 版本追蹤 | 自訂檔案 | wp_options | 標準 WordPress 設定 API |
| 快取 | 自訂 array 快取 | WordPress Transient API | 支援物件快取擴充 |

**Key insight:** WordPress 提供完整的資料庫抽象層（$wpdb）和資料表管理函數（dbDelta），不需要自己處理 SQL 語法差異或資料庫連接。

## Common Pitfalls

### Pitfall 1: dbDelta SQL 格式錯誤

**What goes wrong:** dbDelta 靜默失敗，資料表未建立或未更新
**Why it happens:** SQL 格式不符合 dbDelta 要求（PRIMARY KEY 空格、KEY vs INDEX）
**How to avoid:**
- PRIMARY KEY 後必須有兩個空格：`PRIMARY KEY  (ID)`
- 使用 `KEY` 而非 `INDEX`
- 每個欄位獨立一行
- 不使用 `IF NOT EXISTS`
- 使用 `$wpdb->get_charset_collate()` 設定字元集
**Warning signs:**
- 資料表未建立但沒有錯誤訊息
- 新欄位未新增
- `DESCRIBE {table}` 結果與預期不符

**來源：** [WordPress dbDelta() Reference](https://developer.wordpress.org/reference/functions/dbdelta/)

### Pitfall 2: 遷移腳本重複執行

**What goes wrong:** 資料被重複插入，或遷移錯誤導致資料損壞
**Why it happens:** 未檢查遷移狀態，或遷移過程中斷後重新執行
**How to avoid:**
- 遷移前檢查 `buygo_line_migration_status`
- 遷移前檢查目標表是否已有資料（UNIQUE KEY 會防止重複）
- 遷移後更新狀態（含 `completed_at` 時間戳）
- 使用 `$wpdb->insert()` 配合 UNIQUE KEY（重複會失敗而非覆蓋）
**Warning signs:**
- 遷移計數與預期不符
- 資料有重複
- `wp_options` 中 `buygo_line_migration_status` 為空

**來源：** 現有 `buygo-plus-one-dev/includes/class-database.php` 的 `migrate_helpers_data()`

### Pitfall 3: 欄位對應錯誤

**What goes wrong:** 遷移後資料不正確（如 register_date 和 link_date 混淆）
**Why it happens:** 舊表和新表欄位名稱不同，對應關係不清楚
**How to avoid:**
- 明確定義欄位對應：
  - `identifier` = `line_uid`
  - `user_id` = `user_id`
  - `register_date` = `created_at`（首次建立時間）
  - `link_date` = `updated_at`（最後更新時間）
- 遷移前列出舊表結構和新表結構
- 遷移後抽樣驗證資料正確性
**Warning signs:**
- `register_date` 和 `link_date` 相同
- 日期格式錯誤
- 部分欄位為 NULL

**來源：** REQUIREMENTS-v0.2.md ARCH-02 需求

### Pitfall 4: UNIQUE KEY 衝突處理不當

**What goes wrong:** 遷移失敗或資料覆蓋
**Why it happens:** 舊表有重複的 LINE UID（不應該發生但可能因 bug）
**How to avoid:**
- 遷移前檢查舊表是否有重複的 `line_uid`
- 使用 `$wpdb->insert()` 而非 `$wpdb->replace()`（insert 會失敗而非覆蓋）
- 記錄遷移錯誤但繼續處理其他資料
- 遷移完成後檢查 `error_count`
**Warning signs:**
- `$wpdb->last_error` 包含 "Duplicate entry"
- `migration_status.error_count` > 0

**來源：** WordPress $wpdb 行為觀察

## Code Examples

### Example 1: 完整的 Database 類別

```php
// Source: 整合現有 class-database.php + Nextend wp_social_users 結構
<?php
namespace BuygoLineNotify;

if (!defined('ABSPATH')) {
    exit;
}

class Database {
    /**
     * 資料庫 schema 版本
     */
    const DB_VERSION = '2.0.0';

    /**
     * 初始化資料庫
     */
    public static function init(): void {
        $current_version = get_option('buygo_line_db_version', '0.0.0');

        if (version_compare($current_version, self::DB_VERSION, '<')) {
            self::create_tables();
            self::upgrade($current_version);
            update_option('buygo_line_db_version', self::DB_VERSION);
        }
    }

    /**
     * 建立資料表
     */
    public static function create_tables(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'buygo_line_users';

        // 對齊 Nextend wp_social_users 結構
        $sql = "CREATE TABLE {$table_name} (
            ID bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            type varchar(20) NOT NULL DEFAULT 'line',
            identifier varchar(255) NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            register_date datetime DEFAULT NULL,
            link_date datetime DEFAULT NULL,
            PRIMARY KEY  (ID),
            UNIQUE KEY identifier (identifier),
            KEY user_id (user_id),
            KEY type (type)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * 執行資料庫升級
     *
     * @param string $from_version 現有版本
     */
    private static function upgrade(string $from_version): void {
        // 從 1.x 升級到 2.x：遷移舊表資料
        if (version_compare($from_version, '2.0.0', '<')) {
            self::migrate_from_bindings_table();
        }
    }

    /**
     * 從 wp_buygo_line_bindings 遷移資料到 wp_buygo_line_users
     */
    private static function migrate_from_bindings_table(): void {
        global $wpdb;

        $old_table = $wpdb->prefix . 'buygo_line_bindings';
        $new_table = $wpdb->prefix . 'buygo_line_users';

        // 檢查遷移狀態
        $migration_status = get_option('buygo_line_migration_status', []);
        if (!empty($migration_status['completed_at'])) {
            return;
        }

        // 檢查舊表是否存在
        $old_table_exists = $wpdb->get_var(
            "SHOW TABLES LIKE '{$old_table}'"
        ) === $old_table;

        if (!$old_table_exists) {
            update_option('buygo_line_migration_status', [
                'status'       => 'skipped',
                'reason'       => 'old_table_not_found',
                'completed_at' => current_time('mysql'),
            ]);
            return;
        }

        // 讀取舊表資料（只遷移 active 狀態的）
        $old_records = $wpdb->get_results(
            "SELECT * FROM {$old_table} WHERE status = 'active'"
        );

        $migrated_count = 0;
        $error_count = 0;
        $errors = [];

        foreach ($old_records as $record) {
            // 檢查是否已遷移
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$new_table} WHERE identifier = %s",
                $record->line_uid
            ));

            if ($exists) {
                continue;
            }

            // 遷移資料
            $result = $wpdb->insert(
                $new_table,
                [
                    'type'          => 'line',
                    'identifier'    => $record->line_uid,
                    'user_id'       => $record->user_id,
                    'register_date' => $record->created_at,
                    'link_date'     => $record->updated_at ?? $record->created_at,
                ],
                ['%s', '%s', '%d', '%s', '%s']
            );

            if ($result) {
                $migrated_count++;
            } else {
                $error_count++;
                $errors[] = [
                    'line_uid' => $record->line_uid,
                    'error'    => $wpdb->last_error,
                ];
            }
        }

        // 記錄遷移狀態（保留舊表）
        update_option('buygo_line_migration_status', [
            'status'         => 'completed',
            'migrated_count' => $migrated_count,
            'error_count'    => $error_count,
            'errors'         => $errors,
            'old_table'      => $old_table,
            'new_table'      => $new_table,
            'completed_at'   => current_time('mysql'),
        ]);
    }

    /**
     * 刪除資料表（外掛移除時使用）
     */
    public static function drop_tables(): void {
        global $wpdb;

        $table_name = $wpdb->prefix . 'buygo_line_users';
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");

        delete_option('buygo_line_db_version');
        delete_option('buygo_line_migration_status');
    }
}
```

### Example 2: 完整的 LineUserService 查詢 API

```php
// Source: 基於現有 LineUserService + Nextend 架構
<?php
namespace BuygoLineNotify\Services;

if (!defined('ABSPATH')) {
    exit;
}

class LineUserService {
    /**
     * 根據 LINE UID 取得 WordPress 用戶 ID
     *
     * @param string $line_uid LINE 用戶 ID
     * @return int|null WordPress User ID
     */
    public static function getUserByLineUid(string $line_uid): ?int {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_line_users';

        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$table_name}
             WHERE identifier = %s AND type = 'line'",
            $line_uid
        ));

        return $user_id ? (int) $user_id : null;
    }

    /**
     * 根據 WordPress User ID 取得 LINE UID
     *
     * @param int $user_id WordPress 用戶 ID
     * @return string|null LINE UID
     */
    public static function getLineUidByUserId(int $user_id): ?string {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_line_users';

        $line_uid = $wpdb->get_var($wpdb->prepare(
            "SELECT identifier FROM {$table_name}
             WHERE user_id = %d AND type = 'line'",
            $user_id
        ));

        return $line_uid ?: null;
    }

    /**
     * 檢查用戶是否已綁定 LINE
     *
     * @param int $user_id WordPress 用戶 ID
     * @return bool
     */
    public static function isUserLinked(int $user_id): bool {
        return !is_null(self::getLineUidByUserId($user_id));
    }

    /**
     * 建立用戶與 LINE 的綁定關係
     *
     * @param int    $user_id WordPress 用戶 ID
     * @param string $line_uid LINE 用戶 ID
     * @param bool   $is_registration 是否為註冊（設定 register_date）
     * @return bool
     */
    public static function linkUser(int $user_id, string $line_uid, bool $is_registration = false): bool {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_line_users';

        // 檢查 LINE UID 是否已綁定其他用戶
        $existing_user = self::getUserByLineUid($line_uid);
        if ($existing_user && $existing_user !== $user_id) {
            return false;
        }

        // 檢查用戶是否已綁定其他 LINE
        $existing_line = self::getLineUidByUserId($user_id);
        if ($existing_line && $existing_line !== $line_uid) {
            return false;
        }

        $now = current_time('mysql');

        // 若已存在則更新，否則插入
        if ($existing_user === $user_id) {
            // 更新 link_date
            $result = $wpdb->update(
                $table_name,
                ['link_date' => $now],
                ['user_id' => $user_id, 'type' => 'line'],
                ['%s'],
                ['%d', '%s']
            );
        } else {
            // 新增
            $data = [
                'type'       => 'line',
                'identifier' => $line_uid,
                'user_id'    => $user_id,
                'link_date'  => $now,
            ];
            $formats = ['%s', '%s', '%d', '%s'];

            if ($is_registration) {
                $data['register_date'] = $now;
                $formats[] = '%s';
            }

            $result = $wpdb->insert($table_name, $data, $formats);
        }

        return $result !== false;
    }

    /**
     * 解除綁定
     *
     * @param int $user_id WordPress 用戶 ID
     * @return bool
     */
    public static function unlinkUser(int $user_id): bool {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_line_users';

        $result = $wpdb->delete(
            $table_name,
            ['user_id' => $user_id, 'type' => 'line'],
            ['%d', '%s']
        );

        return $result !== false;
    }

    /**
     * 取得完整的綁定資料
     *
     * @param int $user_id WordPress 用戶 ID
     * @return object|null
     */
    public static function getBinding(int $user_id): ?object {
        global $wpdb;
        $table_name = $wpdb->prefix . 'buygo_line_users';

        $binding = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name}
             WHERE user_id = %d AND type = 'line'",
            $user_id
        ));

        return $binding ?: null;
    }
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| user_meta 混合儲存 | 專用表 wp_buygo_line_users | v0.2 | 單一真實來源，可追蹤歷史 |
| 手動 CREATE TABLE | dbDelta() | WordPress 2.1+ | 自動 schema 比對，升級更安全 |
| 無版本追蹤 | wp_options 版本號 | 最佳實踐 | 可控制升級流程 |

**Deprecated/outdated:**
- **混合儲存（user_meta + custom table）**: 查詢複雜、資料可能不一致，改用單一專用表
- **舊表 wp_buygo_line_bindings**: 資料遷移後保留但不再使用，查詢統一使用新表

## Open Questions

1. **是否需要軟刪除**
   - What we know: Nextend wp_social_users 使用硬刪除（直接 DELETE）
   - What's unclear: 是否需要保留解綁歷史
   - Recommendation: 採用硬刪除（與 Nextend 一致），若需要歷史記錄另建 audit log 表

2. **type 欄位是否必要**
   - What we know: Nextend 使用 type 支援多種 social provider
   - What's unclear: buygo-line-notify 是否會支援其他 provider
   - Recommendation: 保留 type 欄位（預設 'line'），未來可擴展

3. **遷移後舊表何時刪除**
   - What we know: 遷移後保留舊表避免資料遺失
   - What's unclear: 保留多久、誰來刪除
   - Recommendation: 保留 30 天後可手動刪除，在 CHANGELOG 說明

## Sources

### Primary (HIGH confidence)
- [WordPress dbDelta() Reference](https://developer.wordpress.org/reference/functions/dbdelta/) - dbDelta 函數官方文件
- 現有 `buygo-line-notify/includes/class-database.php` - 現有資料表結構
- 現有 `buygo-line-notify/includes/services/class-line-user-service.php` - 現有查詢 API
- 現有 `buygo-plus-one-dev/includes/class-database.php` - 遷移模式參考
- `.planning/NEXTEND-SOCIAL-LOGIN-ANALYSIS.md` - Nextend wp_social_users 表結構分析

### Secondary (MEDIUM confidence)
- [How to Write a Plugin Upgrade Routine](https://wpmayor.com/how-to-write-a-plugin-upgrade-routine/) - 外掛升級最佳實踐
- [WordPress Plugin Updates the Right Way](https://www.sitepoint.com/wordpress-plugin-updates-right-way/) - 版本追蹤模式
- [wp-migrations Library](https://github.com/deliciousbrains/wp-migrations) - Laravel 風格遷移參考

### Tertiary (LOW confidence)
- WebSearch 結果 - 一般最佳實踐（需與官方文件交叉驗證）

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - WordPress 內建功能，官方文件完整
- Architecture: HIGH - 基於現有 codebase 和 Nextend 分析
- Pitfalls: HIGH - 結合官方文件和現有實作經驗

**Research date:** 2026-01-29
**Valid until:** 2026-04-29 (90 days - WordPress 資料庫 API 穩定)
