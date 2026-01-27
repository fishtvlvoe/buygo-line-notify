---
phase: 01-基礎設施與設定
plan: 02
subsystem: infra
tags: [encryption, openssl, wordpress, settings, backward-compatibility]

# Dependency graph
requires:
  - phase: 01-01
    provides: 專案基礎結構和核心類別
provides:
  - SettingsService 加解密服務
  - 向後相容讀取（buygo_core_settings）
  - 敏感設定安全儲存（WordPress Options API）
affects: [01-03, 01-04, 02-webhook, 03-line-login, 04-line-messaging]

# Tech tracking
tech-stack:
  added: [OpenSSL AES-128-ECB encryption]
  patterns: [Settings encryption pattern, Backward compatibility pattern]

key-files:
  created:
    - includes/services/class-settings-service.php
  modified: []

key-decisions:
  - "使用 AES-128-ECB 而非 AES-256-GCM（與舊外掛相同，確保向後相容）"
  - "解密失敗時返回原值而非拋出錯誤（避免系統中斷）"
  - "優先讀取 buygo_line_{key}，備用 buygo_core_settings（明確讀取順序）"

patterns-established:
  - "Settings encryption: 敏感欄位列表 + 自動加解密"
  - "Backward compatibility: 新外掛優先，舊外掛備用"
  - "BUYGO_ENCRYPTION_KEY: wp-config.php 常數定義加密金鑰"

# Metrics
duration: 2min
completed: 2026-01-28
---

# Phase 1 Plan 02: 實作設定加解密服務與向後相容 Summary

**OpenSSL AES-128-ECB 加密儲存 LINE API 金鑰，向後相容 buygo_core_settings 舊外掛設定**

## Performance

- **Duration:** 2 min
- **Started:** 2026-01-27T19:24:10Z
- **Completed:** 2026-01-27T19:26:24Z
- **Tasks:** 1
- **Files modified:** 1 (created)

## Accomplishments

- SettingsService 實作完成，提供 6 個核心方法（get、set、delete、get_all、encrypt、decrypt）
- 敏感欄位（channel_access_token、channel_secret、login_channel_id、login_channel_secret）自動加密
- 向後相容讀取舊外掛（buygo-plus-one-dev）的 buygo_core_settings
- 支援 wp-config.php 定義 BUYGO_ENCRYPTION_KEY 常數
- 解密失敗時返回原值，避免系統中斷

## Task Commits

Each task was committed atomically:

1. **Task 1: 實作 SettingsService 加解密與向後相容** - `b5aa767` (feat)

**Plan metadata:** (will be committed separately)

## Files Created/Modified

- `includes/services/class-settings-service.php` (180 lines) - 設定加解密與向後相容讀取服務

## Decisions Made

**1. 使用 AES-128-ECB 而非 AES-256-GCM**
- **理由:** 與 buygo-plus-one-dev 舊外掛使用相同演算法，確保能正確解密舊設定
- **影響:** 未來若要升級到 GCM，需要寫 migration script 重新加密所有資料
- **參考:** RESEARCH.md Open Question 1

**2. 解密失敗時返回原值**
- **理由:** 避免加密金鑰錯誤或資料損壞時完全中斷系統
- **實作:** `openssl_decrypt()` 返回 false 時返回原值
- **參考:** RESEARCH.md Pitfall 2

**3. 明確讀取優先順序**
- **理由:** 避免混合儲存時的資料不一致問題
- **優先順序:** buygo_line_{key} (新) > buygo_core_settings[key] (舊) > default
- **實作:** get() 方法先檢查新 option，失敗才讀舊 option

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None - implementation followed research patterns directly.

## User Setup Required

**Optional: 定義自訂加密金鑰**

預設使用 `'buygo-secret-key-default'`，建議在 wp-config.php 定義：

```php
define('BUYGO_ENCRYPTION_KEY', 'your-secure-random-key-here');
```

**重要:** 一旦定義後不可更改，否則舊資料無法解密。

## Next Phase Readiness

**Ready:**
- SettingsService 可供 01-03 (Database) 和 01-04 (Admin UI) 使用
- 加密機制已就緒，可安全儲存 LINE API 金鑰
- 向後相容機制確保舊使用者無縫升級

**No blockers or concerns.**

---
*Phase: 01-基礎設施與設定*
*Completed: 2026-01-28*
