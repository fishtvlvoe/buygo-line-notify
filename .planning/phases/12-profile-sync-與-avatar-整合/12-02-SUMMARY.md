---
phase: 12
plan: 02
subsystem: user-avatar
tags: [avatar, wordpress-filter, line-profile, cache]
requires: [Phase 08, Phase 11]
provides: [AvatarService, get_avatar_url integration]
affects: [Phase 13]
tech-stack:
  added: []
  patterns: [wordpress-filter-hook, user-meta-cache]
key-files:
  created:
    - includes/services/class-avatar-service.php
  modified:
    - includes/class-plugin.php
decisions:
  - id: avatar-cache-duration
    choice: 7 天快取，過期時返回舊 URL
    rationale: 避免阻塞頁面渲染，且不需 access_token 即可顯示頭像
  - id: user-id-parsing
    choice: 支援多種參數類型（ID, email, WP_User, WP_Comment, WP_Post）
    rationale: WordPress get_avatar_url 可能傳入不同類型參數
  - id: cache-clear-strategy
    choice: 只刪除 avatar_updated，保留 avatar_url
    rationale: 快取過期時仍可顯示舊頭像，等下次登入更新
metrics:
  duration: 2m 18s
  completed: 2026-01-29
---

# Phase 12 Plan 02: AvatarService 實作 + get_avatar_url filter hook Summary

## One-liner

實作 WordPress get_avatar_url filter hook，已綁定 LINE 的用戶顯示 LINE 頭像，包含 7 天快取機制

## Tasks Completed

| Task | Commit | Description |
|------|--------|-------------|
| 1. 建立 AvatarService 類別 | ed7d0bc | 實作 init(), filterAvatarUrl(), getUserIdFromMixed(), clearAvatarCache(), clearAllAvatarCache() |
| 2. 整合 AvatarService 到 Plugin | 932eef5 | loadDependencies() 載入，onInit() 初始化 filter hook |

**Total: 2/2 tasks complete**

## Implementation Details

### AvatarService 類別

**核心方法：**

1. **init()** - 註冊 get_avatar_url filter hook（priority 10）
2. **filterAvatarUrl()** - Filter hook 實作：
   - 解析 user_id（支援 ID, email, WP_User, WP_Comment, WP_Post）
   - 檢查綁定狀態（LineUserService::isUserLinked）
   - 讀取快取（buygo_line_avatar_url, buygo_line_avatar_updated）
   - 檢查快取是否過期（7 天）
   - 返回 LINE 頭像或原始 URL
3. **getUserIdFromMixed()** - 從混合類型參數解析出 user_id
4. **clearAvatarCache()** - 清除單一用戶的頭像快取時間戳
5. **clearAllAvatarCache()** - 批次清除所有用戶的頭像快取

**快取策略：**

- **user_meta: buygo_line_avatar_url** - 儲存 LINE pictureUrl
- **user_meta: buygo_line_avatar_updated** - 儲存更新時間（MySQL 格式）
- **有效期：7 天** - 使用 DAY_IN_SECONDS 常數計算
- **過期處理：** 返回舊 URL（不阻塞頁面，等下次登入更新）

**支援的參數類型：**

```php
getUserIdFromMixed() 支援：
- 數字 ID (int)
- Email 字串 (string)
- WP_User 物件
- WP_Comment 物件（user_id 可能為 0）
- WP_Post 物件（解析 post_author）
```

### Plugin 整合

**載入順序：**

```php
1. LineUserService (line 105)
2. ProfileSyncService (line 108) - 平行開發
3. AvatarService (line 114) - 依賴 LineUserService
```

**初始化時機：**

```php
onInit() -> AvatarService::init() (line 55)
註冊在 init action，filter hook 在 WordPress 載入時註冊
```

## Verification Results

**1. AvatarService 類別存在且包含所有必要方法**
```bash
✓ class AvatarService (line 34)
✓ public static function init (line 42)
✓ public static function filterAvatarUrl (line 55)
✓ add_filter('get_avatar_url') (line 43)
```

**2. Plugin 整合正確**
```bash
✓ class-avatar-service.php 載入 (line 114)
✓ AvatarService::init() 呼叫 (line 55)
```

**3. PHP 語法檢查通過**
```bash
✓ includes/services/class-avatar-service.php - No syntax errors
✓ includes/class-plugin.php - No syntax errors
```

## Decisions Made

### 1. Avatar 快取時間設定為 7 天

**問題：** LINE pictureUrl 可能變更，快取應多久更新？

**選項：**
- A: 每次都呼叫 LINE API（效能差，需 access_token）
- B: 永久快取（頭像永不更新）
- C: 7 天快取，過期時返回舊 URL（等下次登入更新）

**決定：** C - 7 天快取，過期時返回舊 URL

**理由：**
- 避免在 get_avatar_url filter 中同步呼叫 LINE API（會阻塞所有頁面）
- 7 天是合理的平衡（用戶不太頻繁更換頭像）
- 過期時仍返回舊 URL，等下次登入時由 ProfileSyncService 更新

### 2. 支援多種參數類型解析

**問題：** get_avatar_url filter 可能傳入不同類型的 $id_or_email 參數

**選項：**
- A: 只支援 int user_id（簡單但不完整）
- B: 支援 ID + email（常見情況）
- C: 支援所有 WordPress 物件類型（完整支援）

**決定：** C - 支援所有類型

**理由：**
- WordPress Core 和各種 plugins 可能傳入不同類型參數
- getUserIdFromMixed() 方法統一處理所有情況
- 參考 WordPress Core 的 get_avatar() 函數實作

**支援的類型：**
```php
is_numeric() -> (int) $id_or_email
WP_User -> $id_or_email->ID
WP_Comment -> $id_or_email->user_id (可能為 0，需處理)
WP_Post -> $id_or_email->post_author
is_email() -> get_user_by('email')->ID
```

### 3. 清除快取策略

**問題：** clearAvatarCache() 應該刪除哪些 user_meta？

**選項：**
- A: 刪除 avatar_url 和 avatar_updated（完全清除）
- B: 只刪除 avatar_updated，保留 avatar_url（軟清除）

**決定：** B - 只刪除 avatar_updated

**理由：**
- 快取過期時 filterAvatarUrl() 仍會返回舊 URL
- 避免頭像突然消失（使用者體驗差）
- 等下次登入時由 ProfileSyncService 更新新頭像

## Deviations from Plan

無 - 計畫執行完全符合預期。

## Next Phase Readiness

**Phase 12-03: 整合 ProfileSyncService 到 UserService 和 Login_Handler**

已準備就緒：
- ✓ AvatarService 已建立，filter hook 已註冊
- ✓ user_meta 快取機制（buygo_line_avatar_url, buygo_line_avatar_updated）
- ✓ clearAvatarCache() 方法可由 ProfileSyncService 呼叫

**Phase 13: 前台整合**

已準備就緒：
- ✓ get_avatar_url filter 自動整合所有頭像顯示場景
- ✓ 支援評論、用戶列表、個人資料等所有 WordPress 頭像顯示

**遺留問題：**
- Access Token 長期儲存問題：暫時只在登入時更新頭像（Phase 12-03 將實作）
- LINE pictureUrl 失效處理：未實作 fallback 機制（可在 Phase 13 或未來版本處理）

## Files Changed

**Created:**
- `includes/services/class-avatar-service.php` (157 lines)
  - AvatarService 類別完整實作
  - get_avatar_url filter hook 整合
  - 7 天快取機制
  - 清除快取方法

**Modified:**
- `includes/class-plugin.php` (+3 lines)
  - loadDependencies(): 載入 AvatarService
  - onInit(): 初始化 AvatarService::init()

## Knowledge Gained

### WordPress get_avatar_url Filter Hook

**參數順序：**
```php
apply_filters('get_avatar_url', $url, $id_or_email, $args)
```

**常見 $id_or_email 類型：**
- 評論列表：WP_Comment 物件（user_id 可能為 0）
- 用戶列表：int user_id 或 WP_User 物件
- 文章作者：WP_Post 物件或 int user_id
- 自訂查詢：email 字串

**Priority 10 的意義：**
- WordPress 預設 priority 10
- 其他 plugins（如 Gravatar enhancers）通常使用 priority 10-20
- 我們使用 priority 10 確保及早處理

### User Meta 快取策略

**為何分開儲存 URL 和時間戳？**
- **buygo_line_avatar_url**: 永久儲存，過期時仍可使用
- **buygo_line_avatar_updated**: 記錄更新時間，用於判斷過期

**優點：**
- 快取過期時不會出現破圖（仍顯示舊頭像）
- 清除快取只需刪除時間戳，不影響 URL
- 等下次登入時自然更新

**缺點：**
- 需要兩次 get_user_meta() 查詢（但 WordPress 有物件快取）

### DAY_IN_SECONDS 常數

WordPress 定義的時間常數：
```php
MINUTE_IN_SECONDS = 60
HOUR_IN_SECONDS = 3600
DAY_IN_SECONDS = 86400
WEEK_IN_SECONDS = 604800
MONTH_IN_SECONDS = 2592000
YEAR_IN_SECONDS = 31536000
```

使用常數的好處：
- 語意清楚（7 * DAY_IN_SECONDS 比 604800 更易讀）
- 避免計算錯誤
- WordPress Core 廣泛使用

## Performance Metrics

- **Duration**: 2m 18s
- **Tasks**: 2/2 (100%)
- **Files Created**: 1
- **Files Modified**: 1
- **Lines Added**: 160
- **Commits**: 2

---

**Completed:** 2026-01-29
**Next Plan:** 12-03 (整合 ProfileSyncService)
