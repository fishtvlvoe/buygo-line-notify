---
phase: 09-標準-wordpress-url-機制
plan: 03
subsystem: auth
tags: [wordpress, url-filters, login, logout, session-management]

# Dependency graph
requires:
  - phase: 09-01
    provides: Login Handler 基礎架構和 NSLContinuePageRenderException
provides:
  - UrlFilterService 類別處理 WordPress URL filters
  - login_url filter 可選擇性附加 loginSocial 參數
  - logout_url filter 作為擴展點
  - wp_logout action 清除 LINE session 資料
affects: [10-register-flow-page, 11-完整註冊登入綁定流程, 13-前台整合]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - WordPress URL filter hooks (login_url, logout_url, wp_logout)
    - 可選擇性參數附加機制（透過 get_option 開關）

key-files:
  created:
    - includes/services/class-url-filter-service.php
  modified:
    - includes/class-plugin.php

key-decisions:
  - "login_url filter 預設關閉,需透過 buygo_line_auto_append_login_social 設定啟用"
  - "logout_url filter 目前僅作為擴展點,未修改 URL"
  - "wp_logout 清除 session 資料,但不清除 Transient（StateManager 負責）"

patterns-established:
  - "URL Filter Service 模式: 靜態 register_hooks() 方法註冊 WordPress hooks"
  - "可選擇性功能模式: 透過 get_option 檢查設定,預設不影響標準 WordPress 行為"

# Metrics
duration: 2min
completed: 2026-01-29
---

# Phase 09 Plan 03: URL Filter Service 整合

**WordPress login_url 和 logout_url filters 整合完成,支援可選擇性附加 LINE Login 參數**

## Performance

- **Duration:** 2 min
- **Started:** 2026-01-29T13:23:50Z
- **Completed:** 2026-01-29T13:25:35Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- UrlFilterService 類別實作完成,處理 WordPress URL filters
- login_url filter 可選擇性附加 loginSocial=buygo-line 參數
- wp_logout action 清除 LINE session 資料
- 整合到 Plugin 類別,完成 hooks 註冊

## Task Commits

Each task was committed atomically:

1. **Task 1: 建立 UrlFilterService 類別** - `93607f0` (feat)
2. **Task 2: 整合 UrlFilterService 到 Plugin** - `4972a61` (feat)

## Files Created/Modified
- `includes/services/class-url-filter-service.php` - URL Filter Service 類別,處理 login_url/logout_url/wp_logout
- `includes/class-plugin.php` - 整合 UrlFilterService 到外掛啟動流程

## Decisions Made

**1. login_url filter 預設關閉**
- **理由:** 避免影響標準 WordPress 登入行為,用戶可選擇性啟用
- **設定 key:** `buygo_line_auto_append_login_social`
- **行為:** 啟用後自動附加 `loginSocial=buygo-line` 參數到 wp_login_url()

**2. logout_url filter 僅作為擴展點**
- **理由:** 目前無需修改登出 URL,保留 filter 供未來擴展
- **行為:** 直接返回原始 logout_url

**3. wp_logout 清除 session 資料**
- **理由:** 登出時清除 LINE profile 和 state,避免殘留敏感資料
- **範圍:** 僅清除 session 資料,不清除 Transient（StateManager 負責管理 state transients）

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

**Ready for Phase 10: Register Flow Page 系統**

URL Filter Service 整合完成,為 Register Flow Page 提供：
- 標準 WordPress URL 機制支援
- LINE Login 參數自動附加功能（可選）
- 登出時的資料清除機制

**Phase 9 (標準 WordPress URL 機制) 已完成:**
- ✅ Plan 09-01: Login Handler 基礎架構
- ✅ Plan 09-02: Login Handler + Plugin 整合
- ✅ Plan 09-03: URL Filter Service 整合

**Phase 10 預計實作:**
- Register Flow Page 系統
- Shortcode 機制
- LINE profile 顯示頁面

---
*Phase: 09-標準-wordpress-url-機制*
*Completed: 2026-01-29*
