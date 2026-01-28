---
phase: 09-標準-wordpress-url-機制
plan: 01
subsystem: auth
tags: [LINE Login, OAuth, WordPress hooks, Nextend Social Login, state management]

# Dependency graph
requires:
  - phase: 08-資料表架構與查詢-api
    provides: LineUserService with getUserByLineUid() for user lookup
provides:
  - NSLContinuePageRenderException for flow control
  - Login_Handler with login_init hook for OAuth interception
  - Standard WordPress URL mechanism (wp-login.php?loginSocial=buygo-line)
affects: [10-Register-Flow-Page, 11-完整註冊登入綁定流程]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "NSLContinuePageRenderException pattern (non-error exception for flow control)"
    - "login_init hook interception (WordPress standard URL)"
    - "StateManager integration (verify_state before processing)"

key-files:
  created:
    - includes/exceptions/class-nsl-continue-page-render-exception.php
    - includes/handlers/class-login-handler.php
  modified:
    - includes/services/class-login-service.php

key-decisions:
  - "Use NSLContinuePageRenderException (non-error exception) for flow control instead of redirects"
  - "Integrate StateManager verification at OAuth callback entry point"
  - "Replace REST API callback URL with standard WordPress URL mechanism"

patterns-established:
  - "login_init hook pattern: check loginSocial parameter, route to authorize/callback handlers"
  - "State verification pattern: verify_state() → process → consume_state() (防重放攻擊)"
  - "OAuth callback flow: verify state → exchange token → lookup user → login or throw exception"

# Metrics
duration: 3min
completed: 2026-01-29
---

# Phase 09 Plan 01: Login Handler 基礎架構 Summary

**Standard WordPress URL 機制取代 REST API，使用 login_init hook 處理 LINE OAuth 流程**

## Performance

- **Duration:** 3 min
- **Started:** 2026-01-28T21:27:49Z
- **Completed:** 2026-01-28T21:30:47Z
- **Tasks:** 3
- **Files modified:** 3

## Accomplishments
- 建立 NSLContinuePageRenderException 例外類別（對齊 Nextend Social Login 架構）
- 實作 Login_Handler 處理 login_init hook，攔截 LINE Login 流程
- 整合 StateManager 進行 state 驗證（防 CSRF 和重放攻擊）
- 將 LoginService callback URL 從 REST API 改為標準 WordPress URL

## Task Commits

Each task was committed atomically:

1. **Task 1: 建立 NSLContinuePageRenderException 例外類別** - `8b37e58` (feat)
2. **Task 2: 建立 Login_Handler 類別（login_init hook）** - `5ba7e9f` (feat)
3. **Task 3: 更新 LoginService callback URL** - `e4e8ee0` (feat)

## Files Created/Modified
- `includes/exceptions/class-nsl-continue-page-render-exception.php` - Flow control exception with FLOW_REGISTER/FLOW_LOGIN/FLOW_LINK constants
- `includes/handlers/class-login-handler.php` - login_init hook handler for OAuth flow (authorize + callback)
- `includes/services/class-login-service.php` - Updated callback URL from REST API to wp-login.php?loginSocial=buygo-line

## Decisions Made

**1. NSLContinuePageRenderException 用於流程控制而非錯誤處理**
- 對齊 Nextend Social Login 架構：OAuth callback 拋出例外讓 WordPress 繼續渲染頁面
- 三種流程類型：FLOW_REGISTER (新用戶註冊) / FLOW_LOGIN (已有用戶登入) / FLOW_LINK (已登入用戶綁定)
- 攜帶 LINE profile 和 state_data，供後續流程使用

**2. StateManager 整合位置**
- authorize 階段：LoginService->get_authorize_url() 內部產生並儲存 state（避免重複產生）
- callback 階段：Login_Handler->handle_callback() 首先驗證 state，失敗則拒絕請求並記錄日誌
- 注意：LoginService->handle_callback() 內部已消費 state，避免重複消費

**3. 標準 WordPress URL 機制取代 REST API**
- wp-login.php?loginSocial=buygo-line 取代 /wp-json/buygo-line-notify/v1/login/callback
- 解決 REST API HTML 輸出問題（REST API 強制設定 JSON content-type）
- 對齊 Nextend Social Login 和 WordPress 生態系統慣例

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None

## User Setup Required

None - no external service configuration required.

**Note:** LINE Developers Console 的 Callback URL 需要更新為 `https://your-site.com/wp-login.php?loginSocial=buygo-line`，但這是後續設定，不影響此 plan 執行。

## Next Phase Readiness

**Ready for Phase 10 (Register Flow Page 系統):**
- NSLContinuePageRenderException 已建立，可在 Phase 10 捕捉並顯示註冊表單
- Login_Handler 已整合 StateManager 驗證，安全基礎已完成
- 標準 WordPress URL 機制已就位，Phase 10 可直接實作註冊表單頁面

**No blockers or concerns.**

---
*Phase: 09-標準-wordpress-url-機制*
*Completed: 2026-01-29*
