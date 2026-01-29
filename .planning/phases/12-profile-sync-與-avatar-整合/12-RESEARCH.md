# Phase 12: Profile Sync 與 Avatar 整合 - Research

**Researched:** 2026-01-29
**Domain:** WordPress User Profile Synchronization / Avatar Customization / OAuth Profile Merge
**Confidence:** HIGH

## Summary

本研究聚焦於實作 Profile Sync（SYNC-01 到 SYNC-05）和 Avatar 整合（AVATAR-01 到 AVATAR-05）。經過詳細分析現有程式碼和 WordPress/LINE API 文件後發現，Phase 11 已建立用戶建立和綁定的基礎設施，Phase 12 的主要任務是：(1) 在註冊/登入/綁定時同步 LINE profile 到 WordPress 用戶欄位；(2) 實作 WordPress get_avatar_url filter hook 整合 LINE 頭像；(3) 建立衝突處理策略系統；(4) 實作同步日誌記錄機制。

**現有基礎設施分析：**
- UserService::create_user_from_line() 已有基礎 profile 處理（displayName → display_name, email → user_email, pictureUrl → line_picture_url user_meta）
- UserService::bind_line_to_user() 已寫入 line_display_name 和 line_picture_url user_meta
- LineUserService::bind_line_account() 已將 profile 資料寫入 user_meta（buygo_line_display_name, buygo_line_picture_url）
- SettingsService 已支援加密儲存和讀取，可擴展支援 Profile Sync 設定

**主要挑戰：**
1. **衝突處理邏輯**：LINE profile 與 WordPress 現有資料不一致時的處理策略（LINE 優先 vs WordPress 優先 vs 手動處理）
2. **Avatar 快取機制**：LINE pictureUrl 是動態的（用戶可隨時更改），需要合理的快取策略避免過度 API 請求
3. **同步時機控制**：註冊（強制同步）vs 登入（可選同步）vs 綁定（依策略同步）的不同處理邏輯
4. **日誌儲存限制**：wp_options 儲存同步日誌需要避免無限增長，需要自動清理機制

**Primary recommendation:** Phase 12 應建立統一的 ProfileSyncService 服務類別，整合註冊/登入/綁定時的 profile 同步邏輯，並實作 AvatarService 處理 get_avatar_url filter hook 和快取管理。所有設定（sync_on_login, conflict_strategy）透過 SettingsService 管理，日誌記錄使用 JSON 格式儲存到 wp_options 並限制最近 10 筆。

## Standard Stack

本階段完全使用 WordPress Core API 和 LINE Login API。

### Core

| Component | Version | Purpose | Why Standard |
|-----------|---------|---------|--------------|
| WordPress wp_update_user() | 6.x | 更新用戶資料（display_name, user_email） | WordPress 原生 API，支援完整的 hooks 和驗證 |
| WordPress update_user_meta() | 6.x | 儲存 LINE 頭像 URL 和同步時間戳 | WordPress 原生 user metadata 系統，支援物件快取 |
| WordPress get_avatar_url filter | 6.x | 自訂頭像 URL | WordPress 標準 filter hook，所有頭像顯示都會觸發 |
| WordPress Transient API | 6.x | Avatar URL 更新去重（防止短時間內重複請求） | 避免多個並行請求同時更新同一用戶頭像 |
| LINE Profile API | v2 | 取得用戶最新 profile 資料 | LINE 官方 API，需 access_token（可透過 profile refresh 或重新登入取得） |

### Supporting

| Component | Version | Purpose | When to Use |
|-----------|---------|---------|-------------|
| wp_remote_get() | 6.x | 呼叫 LINE Profile API | WordPress HTTP API，支援錯誤處理和 timeout |
| get_user_meta() | 6.x | 讀取快取的 LINE 頭像 URL | 避免每次都呼叫 LINE API |
| update_option() | 6.x | 儲存同步日誌 | 日誌儲存到 wp_options（key: buygo_line_sync_log_{user_id}） |
| current_time('mysql') | 6.x | 產生 MySQL 格式時間戳 | 記錄同步時間和快取更新時間 |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| wp_options (sync log) | Custom table | wp_options 簡單但每個用戶一筆 option，Custom table 更有效率但需要額外 schema 維護 |
| user_meta (avatar cache) | WordPress Transient | user_meta 永久儲存，Transient 自動過期但不綁定用戶 |
| get_avatar_url filter | pre_get_avatar_data filter | pre_get_avatar_data 可提早短路但較複雜，get_avatar_url 更直觀 |

**Installation:**
無需額外安裝，全部使用 WordPress Core API。

## Architecture Patterns

### Recommended Project Structure

```
includes/
├── services/
│   ├── class-profile-sync-service.php   # 新增：Profile 同步邏輯
│   ├── class-avatar-service.php         # 新增：Avatar 整合和快取管理
│   ├── class-settings-service.php       # 擴展：加入 sync_on_login, conflict_strategy 設定
│   ├── class-user-service.php           # 重構：整合 ProfileSyncService
│   └── class-line-user-service.php      # 維持：綁定 API 不變
└── admin/
    └── views/
        └── settings-sync.php            # 新增：Profile Sync 設定頁面 tab
```

### Pattern 1: 統一的 Profile Sync 方法

**What:** 建立單一的 syncProfile() 方法，處理所有場景（註冊/登入/綁定）的 profile 同步。

**When to use:** UserService::create_user_from_line()、Login_Handler::perform_login()、Link 流程完成時呼叫。

**Example:**
```php
// Source: 設計建議（參考 Nextend Social Login 架構）
class ProfileSyncService {

    /**
     * 同步 LINE profile 到 WordPress 用戶
     *
     * @param int $user_id WordPress 用戶 ID
     * @param array $line_profile LINE profile 資料（displayName, email, pictureUrl）
     * @param string $action 同步動作（register/login/link）
     * @return bool|WP_Error 成功返回 true，失敗返回 WP_Error
     */
    public static function syncProfile(int $user_id, array $line_profile, string $action) {
        // 取得用戶現有資料
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return new \WP_Error('user_not_found', 'User not found');
        }

        // 取得衝突處理策略
        $strategy = SettingsService::get('conflict_strategy', 'line_priority');

        // 記錄變更前的值（用於日誌）
        $old_values = [
            'display_name' => $user->display_name,
            'user_email'   => $user->user_email,
            'avatar_url'   => get_user_meta($user_id, 'buygo_line_avatar_url', true),
        ];

        $changed_fields = [];
        $update_data = ['ID' => $user_id];

        // 處理 display_name
        if (self::shouldUpdateField('display_name', $user->display_name, $line_profile['displayName'] ?? '', $strategy, $action)) {
            $update_data['display_name'] = sanitize_text_field($line_profile['displayName']);
            $changed_fields[] = 'display_name';
        }

        // 處理 user_email
        if (!empty($line_profile['email']) && self::shouldUpdateField('user_email', $user->user_email, $line_profile['email'], $strategy, $action)) {
            // 檢查 email 是否已被其他用戶使用
            $existing = email_exists($line_profile['email']);
            if (!$existing || $existing == $user_id) {
                $update_data['user_email'] = sanitize_email($line_profile['email']);
                $changed_fields[] = 'user_email';
            }
        }

        // 更新用戶資料
        if (count($update_data) > 1) {
            $result = wp_update_user($update_data);
            if (is_wp_error($result)) {
                return $result;
            }
        }

        // 處理頭像
        if (!empty($line_profile['pictureUrl'])) {
            update_user_meta($user_id, 'buygo_line_avatar_url', esc_url_raw($line_profile['pictureUrl']));
            update_user_meta($user_id, 'buygo_line_avatar_updated', current_time('mysql'));
            $changed_fields[] = 'avatar_url';
        }

        // 記錄同步日誌
        self::logSync($user_id, $action, $changed_fields, $old_values, $line_profile);

        return true;
    }

    /**
     * 判斷是否應更新欄位（依據衝突策略）
     */
    private static function shouldUpdateField(string $field, $current_value, $new_value, string $strategy, string $action): bool {
        // 註冊時強制同步（無衝突）
        if ($action === 'register') {
            return !empty($new_value);
        }

        // 登入時依據設定
        if ($action === 'login') {
            $sync_on_login = SettingsService::get('sync_on_login', false);
            if (!$sync_on_login) {
                return false; // 未啟用登入時同步
            }
        }

        // 若當前值為空，始終更新
        if (empty($current_value)) {
            return !empty($new_value);
        }

        // 若當前值與新值相同，跳過
        if ($current_value === $new_value) {
            return false;
        }

        // 依據策略決定
        switch ($strategy) {
            case 'line_priority':
                return !empty($new_value); // LINE 優先：有新值就覆蓋
            case 'wordpress_priority':
                return false; // WordPress 優先：保留現有值
            case 'manual':
                // 手動處理：記錄差異但不自動更新
                self::logConflict($field, $current_value, $new_value);
                return false;
            default:
                return false;
        }
    }

    /**
     * 記錄同步日誌
     */
    private static function logSync(int $user_id, string $action, array $changed_fields, array $old_values, array $line_profile) {
        $log_key = "buygo_line_sync_log_{$user_id}";
        $logs = get_option($log_key, []);

        // 新增日誌記錄
        $logs[] = [
            'timestamp'      => current_time('mysql'),
            'action'         => $action,
            'changed_fields' => $changed_fields,
            'old_values'     => array_intersect_key($old_values, array_flip($changed_fields)),
            'new_values'     => [
                'display_name' => $line_profile['displayName'] ?? '',
                'user_email'   => $line_profile['email'] ?? '',
                'avatar_url'   => $line_profile['pictureUrl'] ?? '',
            ],
        ];

        // 保留最近 10 筆
        if (count($logs) > 10) {
            $logs = array_slice($logs, -10);
        }

        update_option($log_key, $logs, false); // autoload=false
    }
}
```

### Pattern 2: get_avatar_url Filter Hook 實作

**What:** 實作 WordPress get_avatar_url filter hook，返回 LINE 頭像 URL。

**When to use:** 所有頭像顯示場景（評論、用戶列表、個人資料等）。

**Example:**
```php
// Source: WordPress Codex + 專案需求
class AvatarService {

    public static function init() {
        add_filter('get_avatar_url', [__CLASS__, 'filterAvatarUrl'], 10, 3);
    }

    /**
     * Filter hook: 返回 LINE 頭像 URL
     *
     * @param string $url 原始頭像 URL
     * @param mixed $id_or_email 用戶 ID、email 或 WP_User/WP_Comment 物件
     * @param array $args 參數陣列
     * @return string 過濾後的頭像 URL
     */
    public static function filterAvatarUrl($url, $id_or_email, $args) {
        // 解析出 user_id
        $user_id = self::getUserIdFromMixed($id_or_email);
        if (!$user_id) {
            return $url; // 無法識別用戶，返回原始 URL
        }

        // 檢查用戶是否綁定 LINE
        if (!LineUserService::isUserLinked($user_id)) {
            return $url; // 未綁定，返回原始 URL
        }

        // 讀取快取的 LINE 頭像 URL
        $line_avatar_url = get_user_meta($user_id, 'buygo_line_avatar_url', true);
        $avatar_updated = get_user_meta($user_id, 'buygo_line_avatar_updated', true);

        // 檢查快取是否過期（7 天）
        if (!empty($line_avatar_url) && !empty($avatar_updated)) {
            $updated_time = strtotime($avatar_updated);
            $cache_duration = 7 * DAY_IN_SECONDS;

            if ((time() - $updated_time) < $cache_duration) {
                return $line_avatar_url; // 快取有效，直接返回
            }
        }

        // 快取過期，非同步更新（避免阻塞頁面渲染）
        self::asyncUpdateAvatar($user_id);

        // 先返回快取的 URL（即使過期，也比 Gravatar 更符合用戶預期）
        return !empty($line_avatar_url) ? $line_avatar_url : $url;
    }

    /**
     * 從混合類型參數解析出 user_id
     */
    private static function getUserIdFromMixed($id_or_email): ?int {
        if (is_numeric($id_or_email)) {
            return (int) $id_or_email;
        }

        if (is_object($id_or_email)) {
            if ($id_or_email instanceof \WP_User) {
                return $id_or_email->ID;
            }
            if ($id_or_email instanceof \WP_Comment) {
                return (int) $id_or_email->user_id;
            }
            if ($id_or_email instanceof \WP_Post) {
                return (int) $id_or_email->post_author;
            }
        }

        if (is_string($id_or_email) && is_email($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
            return $user ? $user->ID : null;
        }

        return null;
    }

    /**
     * 非同步更新頭像（使用 Transient 防止重複請求）
     */
    private static function asyncUpdateAvatar(int $user_id) {
        // 使用 Transient 防止 5 分鐘內重複更新
        $lock_key = "buygo_line_avatar_updating_{$user_id}";
        if (get_transient($lock_key)) {
            return; // 已有更新任務進行中
        }

        set_transient($lock_key, true, 5 * MINUTE_IN_SECONDS);

        // 取得 LINE UID
        $line_uid = LineUserService::getLineUidByUserId($user_id);
        if (!$line_uid) {
            delete_transient($lock_key);
            return;
        }

        // TODO: 呼叫 LINE Profile API 更新頭像
        // 問題：需要 access_token，但我們只有 LINE UID
        // 解決方案：
        // 1. 在登入時儲存 access_token（但 access_token 會過期）
        // 2. 使用 refresh_token 刷新（但需要額外儲存 refresh_token）
        // 3. 只在登入/綁定時更新頭像，平時使用快取（推薦）

        // 暫時方案：只在 Profile Sync 時更新頭像，此處不主動請求
        delete_transient($lock_key);
    }
}
```

### Pattern 3: 衝突處理策略設定

**What:** 提供後台設定介面，讓管理員選擇衝突處理策略。

**When to use:** 後台設定頁面（Settings Page）。

**Example:**
```php
// Source: WordPress Settings API
// File: admin/views/settings-sync.php

<tr>
    <th scope="row">
        <label for="buygo_line_conflict_strategy">衝突處理策略</label>
    </th>
    <td>
        <fieldset>
            <legend class="screen-reader-text"><span>衝突處理策略</span></legend>

            <label>
                <input type="radio" name="buygo_line_conflict_strategy" value="line_priority"
                    <?php checked($conflict_strategy, 'line_priority'); ?>>
                <strong>LINE 優先</strong> - LINE profile 覆蓋 WordPress 資料
            </label>
            <br>

            <label>
                <input type="radio" name="buygo_line_conflict_strategy" value="wordpress_priority"
                    <?php checked($conflict_strategy, 'wordpress_priority'); ?>>
                <strong>WordPress 優先</strong> - 保留 WordPress 現有資料，只寫入空白欄位
            </label>
            <br>

            <label>
                <input type="radio" name="buygo_line_conflict_strategy" value="manual"
                    <?php checked($conflict_strategy, 'manual'); ?>>
                <strong>手動處理</strong> - 不自動同步，記錄差異讓管理員決定
            </label>

            <p class="description">
                當 LINE profile 與 WordPress 用戶資料不一致時的處理方式。<br>
                預設「LINE 優先」適合大多數情況，「手動處理」適合需要審核用戶資料變更的場景。
            </p>
        </fieldset>
    </td>
</tr>

<tr>
    <th scope="row">
        <label for="buygo_line_sync_on_login">登入時更新 Profile</label>
    </th>
    <td>
        <label>
            <input type="checkbox" name="buygo_line_sync_on_login" id="buygo_line_sync_on_login"
                value="1" <?php checked($sync_on_login, true); ?>>
            啟用登入時自動同步 Profile
        </label>
        <p class="description">
            從 LINE 同步最新的名稱、Email、頭像。<br>
            <strong>注意：</strong>可能覆蓋用戶手動修改的資料，建議僅在初期使用。
        </p>
    </td>
</tr>
```

### Anti-Patterns to Avoid

- **忽略衝突策略:** 永遠不要在 login/link 時直接覆蓋 WordPress 資料，必須依據 conflict_strategy 設定
- **同步日誌無限增長:** wp_options 不適合儲存無限增長的日誌，必須限制最近 N 筆（建議 10 筆）
- **頭像快取永不過期:** LINE 用戶可隨時更改頭像，必須設定合理的快取時間（建議 7 天）
- **阻塞式 API 請求:** 在 get_avatar_url filter 中同步呼叫 LINE API 會拖慢所有頁面，必須使用快取 + 非同步更新
- **儲存 access_token 到 user_meta:** access_token 敏感且短期有效，不應長期儲存，應使用 refresh_token 或在登入時更新

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Avatar URL 快取 | 自製快取機制 | user_meta + Transient lock | WordPress user_meta 已有物件快取整合，Transient 防止並行更新 |
| 日誌記錄格式 | 自製 log table | wp_options (JSON array) | wp_options 簡單且支援 autoload=false，適合小量日誌 |
| Email 驗證 | 自製 email validator | sanitize_email() + is_email() | WordPress 已處理各種邊界情況 |
| 用戶資料更新 | 直接 UPDATE wp_users | wp_update_user() | WordPress 函數觸發 hooks、驗證權限、清除快取 |

**Key insight:** Profile Sync 的複雜度在於「衝突處理策略」和「時機控制」，不是資料操作本身。使用 WordPress Core API 處理資料操作，專注於業務邏輯（何時同步、如何處理衝突、如何記錄日誌）。

## Common Pitfalls

### Pitfall 1: 頭像更新需要 Access Token 但只有 LINE UID

**What goes wrong:** get_avatar_url filter 觸發時，我們有 user_id 和 LINE UID，但沒有 access_token 去呼叫 LINE Profile API 取得最新頭像。

**Why it happens:** access_token 只在 OAuth 流程時取得，且會過期（通常 30 天），不應長期儲存。

**How to avoid:**
1. **推薦方案：** 只在 Profile Sync 時（註冊/登入/綁定）更新頭像 URL，平時使用快取（7 天）。快取過期時不主動請求，等下次登入時更新。
2. **進階方案（若需要）：** 儲存 refresh_token（加密），用 refresh_token 取得新的 access_token 再呼叫 Profile API。但這增加複雜度和安全風險。

**Warning signs:**
- get_avatar_url filter 中嘗試呼叫需要 access_token 的 API
- 將 access_token 長期儲存到 user_meta（安全風險）

### Pitfall 2: wp_options 日誌無限增長

**What goes wrong:** 每次同步都新增一筆日誌到 wp_options，導致 option_value 越來越大，查詢變慢。

**Why it happens:** wp_options 是 key-value 儲存，每個 key 只有一個值，若值是 JSON array 會隨著時間增長。

**How to avoid:**
1. 限制日誌筆數（例如最近 10 筆），使用 array_slice() 自動清理舊日誌
2. 設定 autoload=false（避免每次頁面載入都載入日誌）
3. 若需要完整歷史日誌，考慮使用 Custom table

**Warning signs:**
- option_value 欄位超過 10KB
- 日誌查詢變慢
- wp_options 表過大

### Pitfall 3: 衝突策略未考慮「註冊時無衝突」情境

**What goes wrong:** 註冊時也套用「WordPress 優先」策略，導致 LINE profile 資料沒有寫入（因為 WordPress 用戶資料是空的，不算衝突）。

**Why it happens:** shouldUpdateField() 方法沒有區分 register/login/link 情境。

**How to avoid:**
1. register 動作強制同步（無視衝突策略），因為註冊時 WordPress 資料是空的或預設值
2. login 動作依據 sync_on_login 設定決定是否同步
3. link 動作依據衝突策略處理

**Warning signs:**
- 註冊後用戶 display_name 仍是空白或 WordPress 預設值
- LINE email 沒有寫入 user_email

### Pitfall 4: Email 更新未檢查「是否已被其他用戶使用」

**What goes wrong:** LINE email 同步到 WordPress 時，沒有檢查該 email 是否已被其他用戶使用，導致 wp_update_user() 失敗或資料庫衝突。

**Why it happens:** WordPress 不允許重複 email，但 syncProfile() 方法直接更新 user_email。

**How to avoid:**
1. 使用 email_exists() 檢查 email 是否已存在
2. 若已存在且不是當前用戶，跳過 email 更新並記錄日誌
3. 若 conflict_strategy = 'manual'，記錄衝突讓管理員處理

**Warning signs:**
- wp_update_user() 返回 WP_Error: 'existing_user_email'
- 同步日誌中 user_email 始終沒有變更

### Pitfall 5: LINE pictureUrl 是動態 URL 可能失效

**What goes wrong:** LINE pictureUrl 是 CDN URL（https://profile.line-scdn.net/...），可能因為用戶更換頭像而失效（返回 404），導致頭像顯示破圖。

**Why it happens:** LINE 用戶更換頭像時，pictureUrl 會變成新的 URL，舊 URL 可能失效。

**How to avoid:**
1. 設定合理的快取時間（7 天），定期更新
2. 在 get_avatar_url filter 中處理 fallback：若 LINE 頭像載入失敗，返回 WordPress 預設頭像
3. （進階）使用 WordPress Media Library 下載並儲存 LINE 頭像到本地（但增加儲存空間和同步複雜度）

**Warning signs:**
- 頭像顯示 404 破圖
- LINE pictureUrl 返回 403 Forbidden（用戶刪除頭像或改變隱私設定）

## Code Examples

Verified patterns from WordPress Codex and LINE API documentation:

### Example 1: 完整的 ProfileSyncService::syncProfile() 方法

```php
// Source: 專案需求 + WordPress Core API
// File: includes/services/class-profile-sync-service.php

class ProfileSyncService {

    /**
     * 同步 LINE profile 到 WordPress 用戶
     */
    public static function syncProfile(int $user_id, array $line_profile, string $action) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return new \WP_Error('user_not_found', 'User not found');
        }

        $strategy = SettingsService::get('conflict_strategy', 'line_priority');
        $old_values = [
            'display_name' => $user->display_name,
            'user_email'   => $user->user_email,
            'avatar_url'   => get_user_meta($user_id, 'buygo_line_avatar_url', true),
        ];

        $changed_fields = [];
        $update_data = ['ID' => $user_id];

        // 同步 display_name
        if (self::shouldUpdateField('display_name', $user->display_name, $line_profile['displayName'] ?? '', $strategy, $action)) {
            $update_data['display_name'] = sanitize_text_field($line_profile['displayName']);
            $changed_fields[] = 'display_name';
        }

        // 同步 user_email（檢查重複）
        if (!empty($line_profile['email'])) {
            $existing_email_user = email_exists($line_profile['email']);
            $can_update = (!$existing_email_user || $existing_email_user == $user_id);

            if ($can_update && self::shouldUpdateField('user_email', $user->user_email, $line_profile['email'], $strategy, $action)) {
                $update_data['user_email'] = sanitize_email($line_profile['email']);
                $changed_fields[] = 'user_email';
            }
        }

        // 更新用戶資料
        if (count($update_data) > 1) {
            $result = wp_update_user($update_data);
            if (is_wp_error($result)) {
                return $result;
            }
        }

        // 同步頭像
        if (!empty($line_profile['pictureUrl'])) {
            update_user_meta($user_id, 'buygo_line_avatar_url', esc_url_raw($line_profile['pictureUrl']));
            update_user_meta($user_id, 'buygo_line_avatar_updated', current_time('mysql'));
            $changed_fields[] = 'avatar_url';
        }

        // 記錄日誌
        self::logSync($user_id, $action, $changed_fields, $old_values, $line_profile);

        return true;
    }

    private static function shouldUpdateField(string $field, $current_value, $new_value, string $strategy, string $action): bool {
        // 註冊時強制同步
        if ($action === 'register') {
            return !empty($new_value);
        }

        // 登入時檢查設定
        if ($action === 'login' && !SettingsService::get('sync_on_login', false)) {
            return false;
        }

        // 當前值為空，始終更新
        if (empty($current_value)) {
            return !empty($new_value);
        }

        // 值相同，跳過
        if ($current_value === $new_value) {
            return false;
        }

        // 依策略決定
        switch ($strategy) {
            case 'line_priority':
                return !empty($new_value);
            case 'wordpress_priority':
                return false;
            case 'manual':
                self::logConflict($user_id, $field, $current_value, $new_value);
                return false;
            default:
                return false;
        }
    }

    private static function logSync(int $user_id, string $action, array $changed_fields, array $old_values, array $line_profile) {
        $log_key = "buygo_line_sync_log_{$user_id}";
        $logs = get_option($log_key, []);

        $logs[] = [
            'timestamp'      => current_time('mysql'),
            'action'         => $action,
            'changed_fields' => $changed_fields,
            'old_values'     => array_intersect_key($old_values, array_flip($changed_fields)),
            'new_values'     => array_intersect_key([
                'display_name' => $line_profile['displayName'] ?? '',
                'user_email'   => $line_profile['email'] ?? '',
                'avatar_url'   => $line_profile['pictureUrl'] ?? '',
            ], array_flip($changed_fields)),
        ];

        // 保留最近 10 筆
        if (count($logs) > 10) {
            $logs = array_slice($logs, -10);
        }

        update_option($log_key, $logs, false); // autoload=false
    }

    private static function logConflict(int $user_id, string $field, $current_value, $new_value) {
        // 記錄到衝突日誌（供後台顯示）
        $conflict_key = "buygo_line_conflict_log_{$user_id}";
        $conflicts = get_option($conflict_key, []);

        $conflicts[] = [
            'timestamp'     => current_time('mysql'),
            'field'         => $field,
            'current_value' => $current_value,
            'new_value'     => $new_value,
        ];

        // 保留最近 10 筆
        if (count($conflicts) > 10) {
            $conflicts = array_slice($conflicts, -10);
        }

        update_option($conflict_key, $conflicts, false);
    }
}
```

### Example 2: AvatarService::filterAvatarUrl() 完整實作

```php
// Source: WordPress Codex get_avatar_url filter
// File: includes/services/class-avatar-service.php

class AvatarService {

    public static function init() {
        add_filter('get_avatar_url', [__CLASS__, 'filterAvatarUrl'], 10, 3);
    }

    public static function filterAvatarUrl($url, $id_or_email, $args) {
        $user_id = self::getUserIdFromMixed($id_or_email);
        if (!$user_id || !LineUserService::isUserLinked($user_id)) {
            return $url;
        }

        $line_avatar_url = get_user_meta($user_id, 'buygo_line_avatar_url', true);
        $avatar_updated = get_user_meta($user_id, 'buygo_line_avatar_updated', true);

        // 檢查快取（7 天）
        if (!empty($line_avatar_url) && !empty($avatar_updated)) {
            $updated_time = strtotime($avatar_updated);
            if ((time() - $updated_time) < 7 * DAY_IN_SECONDS) {
                return $line_avatar_url;
            }
        }

        // 快取過期，返回舊 URL（等下次登入更新）
        return !empty($line_avatar_url) ? $line_avatar_url : $url;
    }

    private static function getUserIdFromMixed($id_or_email): ?int {
        if (is_numeric($id_or_email)) {
            return (int) $id_or_email;
        }

        if ($id_or_email instanceof \WP_User) {
            return $id_or_email->ID;
        }

        if ($id_or_email instanceof \WP_Comment) {
            return (int) $id_or_email->user_id;
        }

        if ($id_or_email instanceof \WP_Post) {
            return (int) $id_or_email->post_author;
        }

        if (is_string($id_or_email) && is_email($id_or_email)) {
            $user = get_user_by('email', $id_or_email);
            return $user ? $user->ID : null;
        }

        return null;
    }
}
```

### Example 3: 整合到現有 UserService

```php
// Source: 現有 UserService 重構建議
// File: includes/services/class-user-service.php

class UserService {

    public function create_user_from_line(array $profile) {
        // ... 現有的用戶建立邏輯 ...

        $user_id = wp_create_user($username, wp_generate_password(), $email);
        if (is_wp_error($user_id)) {
            return $user_id;
        }

        // ... 現有的綁定邏輯 ...

        // 新增：Profile Sync（註冊時強制同步）
        ProfileSyncService::syncProfile($user_id, $profile, 'register');

        return $user_id;
    }

    public function bind_line_to_user(int $user_id, array $profile) {
        // ... 現有的綁定邏輯 ...

        // 新增：Profile Sync（綁定時依策略同步）
        ProfileSyncService::syncProfile($user_id, $profile, 'link');

        return true;
    }
}
```

### Example 4: 後台清除頭像快取按鈕

```php
// Source: WordPress Settings API
// File: admin/views/settings-sync.php

<tr>
    <th scope="row">清除頭像快取</th>
    <td>
        <button type="button" class="button" id="clear-avatar-cache">
            清除所有用戶的 LINE 頭像快取
        </button>
        <p class="description">
            清除後，下次顯示頭像時會重新從快取（若未過期）或等待下次登入更新。
        </p>
        <div id="clear-cache-result"></div>
    </td>
</tr>

<script>
jQuery('#clear-avatar-cache').on('click', function() {
    var $button = jQuery(this);
    $button.prop('disabled', true).text('清除中...');

    jQuery.post(ajaxurl, {
        action: 'buygo_line_clear_avatar_cache',
        nonce: '<?php echo wp_create_nonce('clear_avatar_cache'); ?>'
    }, function(response) {
        if (response.success) {
            jQuery('#clear-cache-result').html(
                '<p style="color: green;">已清除 ' + response.data.count + ' 個用戶的頭像快取</p>'
            );
        } else {
            jQuery('#clear-cache-result').html(
                '<p style="color: red;">清除失敗：' + response.data.message + '</p>'
            );
        }
        $button.prop('disabled', false).text('清除所有用戶的 LINE 頭像快取');
    });
});
</script>
```

```php
// Source: WordPress AJAX handler
// File: includes/admin/class-settings-page.php

public function register_ajax_handlers() {
    add_action('wp_ajax_buygo_line_clear_avatar_cache', [$this, 'ajax_clear_avatar_cache']);
}

public function ajax_clear_avatar_cache() {
    check_ajax_referer('clear_avatar_cache', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permission denied']);
    }

    global $wpdb;

    // 清除所有 buygo_line_avatar_updated meta
    $result = $wpdb->query(
        "DELETE FROM {$wpdb->usermeta}
         WHERE meta_key = 'buygo_line_avatar_updated'"
    );

    wp_send_json_success(['count' => $result]);
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| 直接覆蓋 WordPress 用戶資料 | 衝突處理策略（LINE 優先/WordPress 優先/手動） | 2023+ OAuth 整合最佳實踐 | 避免覆蓋用戶手動修改的資料，提升用戶體驗 |
| 儲存 access_token 到 user_meta | 只在登入時更新 profile，平時使用快取 | 2024+ 安全性考量 | 降低 token 洩漏風險，減少 API 請求 |
| get_avatar filter | get_avatar_url filter | WordPress 4.2+ | get_avatar_url 更簡單（只處理 URL），get_avatar 需要處理 HTML |
| 無限增長的日誌表 | wp_options JSON array + 限制筆數 | 2025+ 效能考量 | 減少資料庫空間，加快查詢速度 |

**Deprecated/outdated:**
- **Storing access_token in user_meta:** 安全風險，access_token 會過期且敏感，應只在 OAuth 流程中使用
- **Unlimited sync logs:** wp_options 不適合無限增長資料，應限制筆數或使用 Custom table
- **Synchronous API calls in filters:** 在 get_avatar_url 等 filter 中同步呼叫 API 會拖慢所有頁面，應使用快取

## Open Questions

Things that couldn't be fully resolved:

1. **Access Token 長期儲存問題**
   - What we know: access_token 在 OAuth 流程時取得，會過期（30 天）
   - What's unclear: 是否應儲存 refresh_token 以便主動更新頭像？refresh_token 的安全性如何？
   - Recommendation: Phase 12 先採用「只在登入時更新頭像，平時使用 7 天快取」的簡單方案。若未來需要主動更新頭像，再研究 refresh_token 機制。

2. **LINE pictureUrl 失效處理**
   - What we know: LINE pictureUrl 可能因用戶更換頭像而失效（返回 404）
   - What's unclear: 如何偵測 URL 失效？是否應下載頭像到 WordPress Media Library？
   - Recommendation: 先使用 fallback 機制（URL 失效時返回 WordPress 預設頭像），若問題嚴重再考慮下載到本地。

3. **衝突日誌的後台顯示**
   - What we know: conflict_strategy = 'manual' 時會記錄衝突到 wp_options
   - What's unclear: 後台應如何顯示這些衝突？是否需要「批次處理」功能？
   - Recommendation: Phase 12 先實作日誌記錄，後台顯示留待 Phase 14（後台管理）。

4. **Profile Sync 的 Hooks**
   - What we know: 應提供 hooks 讓其他外掛整合（例如 buygo_line_profile_sync）
   - What's unclear: 應在 syncProfile() 的哪些時間點觸發 hooks？
   - Recommendation: 參考 WordPress wp_update_user() 的 hooks 設計，提供 before/after hooks。

## Sources

### Primary (HIGH confidence)

- [WordPress get_avatar_url Hook | Developer.WordPress.org](https://developer.wordpress.org/reference/hooks/get_avatar_url/) - Official documentation for get_avatar_url filter hook
- [WordPress Transients API | Developer.WordPress.org](https://developer.wordpress.org/apis/transients/) - Official documentation for Transient API usage
- [LINE Login API v2.1 reference | LINE Developers](https://developers.line.biz/en/reference/line-login/) - LINE Profile API documentation
- [Managing users | LINE Developers](https://developers.line.biz/en/docs/line-login/managing-users/) - LINE profile picture URL behavior
- Existing codebase analysis:
  - `includes/services/class-user-service.php` - Current profile handling implementation
  - `includes/services/class-line-user-service.php` - LINE binding API
  - `includes/services/class-settings-service.php` - Settings encryption and storage

### Secondary (MEDIUM confidence)

- [WordPress OAuth 2.0 Authentication Implementation](https://belovdigital.agency/blog/implementing-oauth-2-0-authentication-in-wordpress/) - OAuth profile sync best practices
- [User Sync for Azure AD Plugin](https://wordpress.org/plugins/user-sync-for-azure-office365/) - Profile synchronization patterns including claim mapping

### Tertiary (LOW confidence)

- [WP Remote Users Sync Plugin](https://wordpress.org/plugins/wp-remote-users-sync/) - User metadata synchronization examples (但架構不同)

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - 全部使用 WordPress Core API，有官方文件支援
- Architecture: HIGH - 基於現有 Phase 11 實作和 WordPress Settings API 標準模式
- Pitfalls: HIGH - 基於 LINE API 文件（pictureUrl 動態性）和 WordPress 最佳實踐（wp_options 限制）

**Research date:** 2026-01-29
**Valid until:** 2026-02-28 (30 days - WordPress Core API 穩定)
