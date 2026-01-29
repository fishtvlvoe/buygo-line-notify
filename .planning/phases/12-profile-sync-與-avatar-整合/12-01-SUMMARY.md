---
phase: 12-profile-sync-與-avatar-整合
plan: 01
subsystem: auth
tags: [profile-sync, wordpress, line-login, user-meta]

# Dependency graph
requires:
  - phase: 08-資料表架構與查詢-api
    provides: LineUserService 和 wp_buygo_line_users 資料表架構
  - phase: 09-標準-wordpress-url-機制
    provides: StateManager 和 Login_Handler 框架
provides:
  - ProfileSyncService 核心服務類別（syncProfile, shouldUpdateField, logSync）
  - SettingsService 擴展支援 sync_on_login 和 conflict_strategy 設定
  - 三種衝突處理策略（line_priority, wordpress_priority, manual）
  - 同步日誌和衝突日誌機制（wp_options, 最多保留 10 筆）
affects: [12-02, 12-03, 11-02, 11-03, frontend, backend]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "ProfileSyncService 靜態服務類別模式"
    - "場景驅動的同步策略（register 強制同步, login 依設定）"
    - "wp_options 日誌儲存（autoload=false, array_slice 限制筆數）"

key-files:
  created:
    - includes/services/class-profile-sync-service.php
  modified:
    - includes/services/class-settings-service.php
    - includes/class-plugin.php

key-decisions:
  - "register 動作強制同步，無視衝突策略（新用戶應使用 LINE profile）"
  - "login 動作依據 sync_on_login 設定決定是否同步（預設關閉）"
  - "Email 更新前檢查 email_exists()，避免衝突"
  - "manual 策略呼叫 logConflict()，不自動更新"
  - "日誌儲存到 wp_options（autoload=false），最多保留 10 筆"

patterns-established:
  - "shouldUpdateField() 統一判斷邏輯：場景 → 空值 → 相同值 → 策略"
  - "sanitize_text_field/sanitize_email/esc_url_raw 清理所有輸入"
  - "ProfileSyncService 在 Plugin 載入順序：SettingsService → ProfileSyncService → UserService"

# Metrics
duration: 2min
completed: 2026-01-29
---

# Phase 12 Plan 01: ProfileSyncService 核心服務類別

**實作 LINE profile 同步核心邏輯，支援三種場景（register/login/link）和三種衝突策略（line_priority/wordpress_priority/manual），包含同步日誌記錄機制**

## Performance

- **Duration:** 2 min
- **Started:** 2026-01-29T09:22:57Z
- **Completed:** 2026-01-29T09:24:54Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments
- 建立 ProfileSyncService 核心服務類別，實作 syncProfile() 和 shouldUpdateField() 邏輯
- 擴展 SettingsService 支援 sync_on_login 和 conflict_strategy 設定
- 實作同步日誌和衝突日誌機制（wp_options，最多保留 10 筆）
- 在 Plugin 中正確載入 ProfileSyncService（順序：SettingsService → ProfileSyncService → UserService）

## Task Commits

Each task was committed atomically:

1. **Task 1: 擴展 SettingsService 並建立 ProfileSyncService** - `ed7d0bc` (feat)
2. **Task 2: 在 Plugin 中載入 ProfileSyncService（驗證載入順序）** - `39dfcce` (feat)

## Files Created/Modified

### Created
- `includes/services/class-profile-sync-service.php` - Profile Sync 核心邏輯（304 行）
  - syncProfile(): 同步 LINE profile 到 WordPress 用戶
  - shouldUpdateField(): 判斷是否應該更新欄位（場景 + 策略驅動）
  - logSync(): 記錄同步日誌到 wp_options
  - logConflict(): 記錄衝突日誌到 wp_options
  - getSyncLog/clearSyncLog/getConflictLog/clearConflictLog: 日誌管理

### Modified
- `includes/services/class-settings-service.php` - 擴展支援 Profile Sync 設定
  - 新增 sync_on_login 和 conflict_strategy keys 到 get_all()
  - 新增 get_sync_on_login(): bool helper 方法
  - 新增 get_conflict_strategy(): string helper 方法（預設 line_priority，驗證輸入）
- `includes/class-plugin.php` - 載入 ProfileSyncService
  - 在 SettingsService 之後（line 102）載入 ProfileSyncService（line 108）
  - 確保 UserService（line 117）和 Login_Handler 可呼叫 ProfileSyncService

## Decisions Made

1. **register 動作強制同步，無視衝突策略**
   - 理由：新用戶註冊時應使用 LINE profile 資料，確保資料完整性
   - 實作：shouldUpdateField() 在 $action === 'register' 時直接返回 true（只要有新值）

2. **login 動作依據 sync_on_login 設定決定是否同步**
   - 理由：登入時同步可能覆蓋用戶自訂資料，應由管理員控制
   - 實作：shouldUpdateField() 檢查 SettingsService::get_sync_on_login()，預設關閉

3. **Email 更新前檢查 email_exists()**
   - 理由：避免 Email 衝突導致 wp_update_user() 失敗
   - 實作：若 Email 已被其他用戶使用，跳過更新並記錄 error_log

4. **manual 策略呼叫 logConflict()，不自動更新**
   - 理由：管理員希望手動審核衝突，需要記錄差異
   - 實作：shouldUpdateField() 在 manual 策略時呼叫 logConflict() 並返回 false

5. **日誌儲存到 wp_options（autoload=false），最多保留 10 筆**
   - 理由：避免 autoload 影響效能，限制日誌筆數避免無限增長
   - 實作：update_option($log_key, $logs, false) + array_slice($logs, -10)

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## Next Phase Readiness

**已就緒：**
- ProfileSyncService::syncProfile() 可在 UserService 和 Login_Handler 中呼叫
- SettingsService 支援 sync_on_login 和 conflict_strategy 設定（後台需新增 UI）
- 同步日誌和衝突日誌機制完整（可在後台顯示）

**待完成（Phase 12 後續計畫）：**
- 12-02: 註冊/登入/綁定時呼叫 ProfileSyncService::syncProfile()
- 12-03: 實作 Avatar 整合（get_avatar_url filter hook）
- 後台 UI：新增 Profile Sync 設定頁面（sync_on_login, conflict_strategy）
- 後台 UI：顯示同步日誌和衝突日誌

**無阻礙或疑慮。**

---
*Phase: 12-profile-sync-與-avatar-整合*
*Completed: 2026-01-29*
