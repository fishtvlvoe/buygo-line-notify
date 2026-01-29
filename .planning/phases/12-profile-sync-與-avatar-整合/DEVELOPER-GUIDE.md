# Phase 12: Profile Sync 與 Avatar 整合 - 開發者指南

## 概述

Phase 12 實作了 LINE profile 同步機制和 WordPress 頭像整合，包含兩個核心服務：

- **ProfileSyncService** - 同步 LINE profile 到 WordPress 用戶
- **AvatarService** - 整合 LINE 頭像到 WordPress

## ProfileSyncService 使用指南

### 基本用法

```php
use BuygoLineNotify\Services\ProfileSyncService;

// 同步 LINE profile 到 WordPress 用戶
$result = ProfileSyncService::syncProfile(
    $user_id,           // WordPress 用戶 ID
    [
        'displayName' => 'Fish 老魚',
        'email'       => 'fish@example.com',
        'pictureUrl'  => 'https://profile.line-scdn.net/...'
    ],
    'register'          // 觸發場景：register / login / link
);

if (is_wp_error($result)) {
    // 處理錯誤
    error_log('Profile sync failed: ' . $result->get_error_message());
} else {
    // 同步成功
    error_log('Profile synced successfully');
}
```

### 三種觸發場景

#### 1. register（註冊）
- **行為**：強制同步，無視衝突策略
- **使用時機**：新用戶透過 LINE 註冊時
- **邏輯**：新用戶應該使用 LINE profile，不會有衝突

```php
// 在 UserService::create_user_from_line() 中使用
ProfileSyncService::syncProfile($user_id, $line_profile, 'register');
```

#### 2. login（登入）
- **行為**：依據後台設定決定是否同步
- **使用時機**：既有用戶透過 LINE 登入時
- **邏輯**：
  - 若 `sync_on_login` 未啟用，不執行同步
  - 若啟用，依據 `conflict_strategy` 處理衝突

```php
// 在 Login_Handler::perform_login() 中使用
ProfileSyncService::syncProfile($user_id, $line_profile, 'login');
```

#### 3. link（綁定）
- **行為**：與 login 相同邏輯
- **使用時機**：既有用戶綁定 LINE 帳號時
- **邏輯**：與 login 相同，依據設定和策略決定

```php
// 在 Login_Handler::handle_link_submission() 中使用
ProfileSyncService::syncProfile($user_id, $line_profile, 'link');
```

### 三種衝突處理策略

#### 1. line_priority（LINE 優先）
- **預設策略**
- WordPress 資料與 LINE profile 不同時，使用 LINE profile 覆蓋
- **適用場景**：信任 LINE profile，希望資料始終與 LINE 同步

```php
// 後台設定
SettingsService::set('conflict_strategy', 'line_priority');

// 行為範例
// WordPress display_name: "測試名稱"
// LINE displayName: "Fish 老魚"
// 結果: display_name 被更新為 "Fish 老魚"
```

#### 2. wordpress_priority（WordPress 優先）
- 保留 WordPress 現有資料，只在欄位為空時才更新
- **適用場景**：尊重用戶在 WordPress 的自訂資料

```php
// 後台設定
SettingsService::set('conflict_strategy', 'wordpress_priority');

// 行為範例
// WordPress display_name: "自訂名稱"
// LINE displayName: "Fish 老魚"
// 結果: display_name 保持 "自訂名稱"（不更新）

// 但若 display_name 為空：
// WordPress display_name: ""
// LINE displayName: "Fish 老魚"
// 結果: display_name 更新為 "Fish 老魚"
```

#### 3. manual（手動處理）
- 不自動更新，將衝突記錄到 wp_options
- **適用場景**：需要管理員審核後再決定如何處理

```php
// 後台設定
SettingsService::set('conflict_strategy', 'manual');

// 行為範例
// WordPress display_name: "自訂名稱"
// LINE displayName: "Fish 老魚"
// 結果: display_name 保持 "自訂名稱"
// 衝突記錄到: buygo_line_conflict_log_{user_id}
```

### 查看同步日誌

```php
// 取得同步日誌（最多 10 筆）
$logs = ProfileSyncService::getSyncLog($user_id);

// 日誌格式
[
    [
        'timestamp'      => '2026-01-29 17:30:00',
        'action'         => 'register',  // register / login / link
        'changed_fields' => ['display_name', 'user_email', 'avatar_url'],
        'old_values'     => ['', '', ''],
        'new_values'     => ['Fish 老魚', 'fish@example.com', 'https://...']
    ]
]

// 清除同步日誌
ProfileSyncService::clearSyncLog($user_id);
```

### 查看衝突日誌（manual 策略使用）

```php
// 取得衝突日誌（最多 10 筆）
$conflicts = ProfileSyncService::getConflictLog($user_id);

// 日誌格式
[
    [
        'timestamp'     => '2026-01-29 17:30:00',
        'field'         => 'display_name',
        'current_value' => '自訂名稱',
        'new_value'     => 'Fish 老魚'
    ]
]

// 清除衝突日誌
ProfileSyncService::clearConflictLog($user_id);
```

## AvatarService 使用指南

### 自動整合

AvatarService 透過 WordPress `get_avatar_url` filter 自動整合，不需要手動呼叫。

```php
// 在 Plugin::onInit() 中初始化
Services\AvatarService::init();

// 之後所有 get_avatar_url() 呼叫都會自動使用 LINE 頭像
$avatar_url = get_avatar_url($user_id);
// 若用戶已綁定 LINE，返回 LINE 頭像 URL
// 若用戶未綁定，返回原始頭像（Gravatar 或預設）
```

### 頭像快取機制

- **快取時間**：7 天
- **快取位置**：
  - `buygo_line_avatar_url` - 頭像 URL
  - `buygo_line_avatar_updated` - 更新時間（MySQL datetime）
- **過期處理**：返回舊 URL（不阻塞頁面，等下次登入更新）

```php
// 頭像在 ProfileSyncService::syncProfile() 中自動更新
// 不需要手動處理

// 檢查頭像快取狀態
$avatar_url = get_user_meta($user_id, 'buygo_line_avatar_url', true);
$updated = get_user_meta($user_id, 'buygo_line_avatar_updated', true);

if ($updated) {
    $cache_age = time() - strtotime($updated);
    $is_expired = $cache_age > (7 * DAY_IN_SECONDS);

    if ($is_expired) {
        // 快取已過期，但仍會顯示舊頭像
        // 等下次登入時會自動更新
    }
}
```

### 清除頭像快取

```php
// 清除單一用戶
AvatarService::clearAvatarCache($user_id);

// 清除所有用戶（返回清除數量）
$count = AvatarService::clearAllAvatarCache();
echo "已清除 {$count} 個用戶的頭像快取";
```

### 支援的參數類型

AvatarService 支援多種參數類型，與 WordPress 核心相容：

```php
// 1. 用戶 ID（數字）
get_avatar_url(25);

// 2. Email 字串
get_avatar_url('fish@example.com');

// 3. WP_User 物件
$user = get_user_by('id', 25);
get_avatar_url($user);

// 4. WP_Comment 物件
get_avatar_url($comment);

// 5. WP_Post 物件
get_avatar_url($post);  // 使用文章作者的頭像
```

## 後台設定

### 設定位置

WordPress 後台 → LINE 串接通知 → 設定 → Profile 同步設定

### 可用設定

```php
// 1. 登入時更新 Profile
SettingsService::get('sync_on_login', false);  // bool
SettingsService::set('sync_on_login', '1');    // '1' 或 ''

// 2. 衝突處理策略
SettingsService::get('conflict_strategy', 'line_priority');  // string
SettingsService::set('conflict_strategy', 'wordpress_priority');
// 有效值：'line_priority' / 'wordpress_priority' / 'manual'

// 3. 使用 helper 方法
$sync_enabled = SettingsService::get_sync_on_login();  // bool
$strategy = SettingsService::get_conflict_strategy();  // string（已驗證）
```

## 整合檢查清單

如果你要在其他地方整合 Profile Sync 功能，請確認：

- [ ] ProfileSyncService 已在 Plugin 中載入（在 SettingsService 之後）
- [ ] AvatarService 已在 Plugin 中載入（在 LineUserService 之後）
- [ ] AvatarService::init() 已在 Plugin::onInit() 中呼叫
- [ ] 使用正確的 action 參數（register / login / link）
- [ ] 處理 syncProfile() 可能返回的 WP_Error
- [ ] LINE profile 包含必要欄位（displayName, email, pictureUrl）
- [ ] 使用正確的命名空間（Services\ProfileSyncService）

## 常見問題

### Q1: 為什麼登入時 Profile 沒有更新？

**A**: 檢查以下設定：
1. 後台「登入時更新 Profile」是否已勾選
2. 衝突處理策略是否為 `wordpress_priority` 或 `manual`（這兩種策略會保留現有資料）

### Q2: 如何查看同步是否成功？

**A**: 使用除錯腳本：
```bash
php check-conflict-log.php  # 查看衝突日誌和同步日誌
php check-settings.php      # 查看目前設定
```

### Q3: 頭像沒有顯示怎麼辦？

**A**: 檢查：
1. 用戶是否已綁定 LINE（`LineUserService::isUserLinked($user_id)`）
2. user_meta 中是否有 `buygo_line_avatar_url`
3. AvatarService::init() 是否已執行
4. 佈景主題是否使用標準的 `get_avatar_url()` 函數

### Q4: 如何強制更新所有用戶的 Profile？

**A**: 目前沒有批次更新功能，只能在用戶登入時自動更新。如需批次更新，需要自行撰寫腳本：

```php
$users = get_users(['meta_key' => 'line_user_id']);
foreach ($users as $user) {
    // 取得 LINE profile（需要 access_token，較複雜）
    // ProfileSyncService::syncProfile($user->ID, $line_profile, 'login');
}
```

## 技術細節

### 資料庫儲存

**user_meta**:
- `buygo_line_avatar_url` - LINE 頭像 URL（快取）
- `buygo_line_avatar_updated` - 頭像更新時間（MySQL datetime）

**wp_options**:
- `buygo_line_sync_log_{user_id}` - 同步日誌（JSON array，最多 10 筆）
- `buygo_line_conflict_log_{user_id}` - 衝突日誌（JSON array，最多 10 筆）
- `buygo_line_sync_on_login` - 登入時是否同步（'1' 或 ''）
- `buygo_line_conflict_strategy` - 衝突策略（string）

### 效能考量

1. **Transient vs Options**：同步日誌和衝突日誌使用 `update_option(..., false)` 設定 `autoload=false`，避免影響首頁載入速度

2. **頭像快取**：7 天快取避免每次頁面載入都請求 LINE API

3. **Filter Hook 效能**：`get_avatar_url` filter 只執行簡單的資料庫查詢（user_meta），不會阻塞頁面

## 相關檔案

- `includes/services/class-profile-sync-service.php` - Profile Sync 核心邏輯
- `includes/services/class-avatar-service.php` - Avatar 整合
- `includes/services/class-settings-service.php` - 設定存取（擴展）
- `includes/admin/views/settings-page.php` - 後台 UI
- `includes/admin/class-settings-page.php` - AJAX handler
- `.planning/phases/12-profile-sync-與-avatar-整合/12-RESEARCH.md` - 研究文件
