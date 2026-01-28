---
phase: 09-標準-wordpress-url-機制
plan: 02
subsystem: auth
tags: [LINE Login, WordPress integration, deprecation, REST API, backward compatibility]

# Dependency graph
requires:
  - phase: 09-標準-wordpress-url-機制
    provides: Login_Handler with login_init hook for standard WordPress URL mechanism
provides:
  - Login_Handler integrated into Plugin main flow
  - Login_API marked as deprecated (backward compatible)
  - Standard WordPress URL mechanism fully operational
affects: [10-Register-Flow-Page, 11-完整註冊登入綁定流程]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Plugin integration pattern (loadDependencies + onInit hooks registration)"
    - "Deprecation pattern (docblock @deprecated + response headers + logging)"
    - "Backward compatibility maintenance (keep functionality while warning)"

key-files:
  created: []
  modified:
    - includes/class-plugin.php
    - includes/api/class-login-api.php

key-decisions:
  - "Load Login_Handler and NSLContinuePageRenderException in Plugin loadDependencies()"
  - "Register Login_Handler hooks in Plugin onInit() method"
  - "Mark all Login_API endpoints as @deprecated 2.0.0 (5 locations total)"
  - "Add X-BuyGo-Deprecated headers to authorize() and callback() for runtime warnings"
  - "Maintain full backward compatibility while guiding migration"

patterns-established:
  - "Deprecation strategy: docblock + headers + logging + preserve functionality"
  - "Exception loading pattern: load exception class before handler class"
  - "Hook registration in Plugin: centralized in onInit() after all dependencies loaded"

# Metrics
duration: 2min
completed: 2026-01-29
---

# Phase 09 Plan 02: Plugin 整合與 REST API deprecated 標記 Summary

**Login_Handler 整合到 Plugin 主流程，REST API 標記 deprecated 保持向後相容**

## Performance

- **Duration:** 2 min
- **Started:** 2026-01-29T05:37:46+08:00
- **Completed:** 2026-01-29T05:38:40+08:00
- **Tasks:** 3 (2 auto + 1 checkpoint)
- **Files modified:** 2

## Accomplishments
- 在 Plugin 主類別中整合 Login_Handler（loadDependencies + onInit）
- 標記 Login_API 全部 5 個位置為 @deprecated 2.0.0
- 在 authorize() 和 callback() 方法中發出 X-BuyGo-Deprecated response headers
- 記錄 deprecated 呼叫到 log（warning 級別）
- 保持完整向後相容性（REST API 仍可正常運作）
- 用戶成功驗證 LINE Login 流程（wp-login.php?loginSocial=buygo-line）

## Task Commits

Each task was committed atomically:

1. **Task 1: 整合 Login_Handler 到 Plugin 主流程** - `06ac133` (feat)
2. **Task 2: 標記 Login_API 為 deprecated** - `9c9fdcc` (feat)
3. **Task 3: Checkpoint - Human verification** - User approved (no commit)

## Files Created/Modified
- `includes/class-plugin.php` - Load NSLContinuePageRenderException and Login_Handler, register Login_Handler hooks in onInit()
- `includes/api/class-login-api.php` - Mark class and all methods as @deprecated 2.0.0, add deprecation headers and logging

## Decisions Made

**1. Plugin 整合位置**
- loadDependencies(): 先載入 NSLContinuePageRenderException，再載入 Login_Handler（依賴順序）
- onInit(): 在現有 hooks 後註冊 Login_Handler::register_hooks()
- 理由: 確保依賴正確載入，hooks 在 WordPress init 階段註冊

**2. Deprecation 標記位置（共 5 處）**
- Class docblock: 標記整個類別為 deprecated
- register_routes() method: 標記路由註冊
- authorize() method: 標記 authorize 端點（含 header）
- callback() method: 標記 callback 端點（含 header）
- bind() method: 標記 bind 端點
- 理由: 完整標記所有公開介面，確保開發者收到清楚訊息

**3. 完整向後相容策略**
- 保留所有原有邏輯不變
- 透過 docblock 提示未來遷移需求
- 透過 X-BuyGo-Deprecated header 在 runtime 提示開發者
- 透過 Logger 記錄 warning（可在 log 中追蹤舊 API 使用情況）
- 不中斷任何現有流程
- 理由: 給予開發者充分時間遷移，避免破壞現有整合

**4. LINE Developers Console Callback URL 更新**
- 新 Callback URL: `https://test.buygo.me/wp-login.php?loginSocial=buygo-line`
- 舊 URL 仍可運作（REST API 保持向後相容）
- 理由: 驗證新機制運作正常，同時不強制立即遷移

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None

## User Setup Required

**LINE Developers Console 更新（可選，建議執行）:**
- 前往 LINE Developers Console > Login Channel > LINE Login settings
- 將 Callback URL 更新為: `https://test.buygo.me/wp-login.php?loginSocial=buygo-line`
- 舊的 REST API URL 仍可繼續使用（向後相容）

Note: 這是建議動作，不是必要動作。舊 URL 會繼續運作到未來版本正式移除。

## Next Phase Readiness

**Ready for Phase 10 (Register Flow Page 系統):**
- Login_Handler 已完整整合到 Plugin，標準 WordPress URL 機制正常運作
- 用戶驗證通過（已綁定用戶成功登入）
- NSLContinuePageRenderException 已就位，Phase 10 可實作註冊流程頁面
- REST API 仍可運作，確保平滑遷移

**Checkpoint 驗證結果:**
- ✅ 標準 WordPress URL 流程測試通過（wp-login.php?loginSocial=buygo-line）
- ✅ 已綁定用戶成功登入並導回網站首頁
- ✅ OAuth 流程正常（LINE 授權頁面 → callback → 自動登入）

**No blockers or concerns.**

---
*Phase: 09-標準-wordpress-url-機制*
*Completed: 2026-01-29*
