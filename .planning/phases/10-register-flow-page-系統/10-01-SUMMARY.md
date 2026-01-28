---
phase: 10-register-flow-page-系統
plan: 01
subsystem: auth
tags: [line-login, oauth, transient, shortcode, registration-flow]

# Dependency graph
requires:
  - phase: 09-標準-wordpress-url-機制
    provides: NSLContinuePageRenderException, Login_Handler, StateManager
provides:
  - RegisterFlowShortcode 類別（渲染註冊表單）
  - Transient 機制（儲存 LINE profile 10 分鐘）
  - 動態 shortcode 註冊機制
  - Fallback 表單（wp-login.php）
  - 完整例外流程處理（FLOW_REGISTER, FLOW_LINK）
affects: [11-完整註冊登入綁定流程, 13-前台整合]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Transient API for temporary profile storage"
    - "Dynamic shortcode registration in OAuth callback"
    - "BEM CSS naming for shortcode styling"
    - "Fallback forms for unconfigured pages"

key-files:
  created:
    - includes/shortcodes/class-register-flow-shortcode.php
  modified:
    - includes/handlers/class-login-handler.php

key-decisions:
  - "使用 Transient API 儲存 LINE profile（10 分鐘 TTL）"
  - "動態註冊 shortcode 而非在 Plugin::onInit 靜態註冊"
  - "提供 fallback 表單機制（當未設定 Register Flow Page 時）"
  - "switch 語句處理所有例外流程類型（FLOW_REGISTER, FLOW_LINK）"
  - "Shortcode 接受 exception_data 參數（動態註冊）或 URL state 參數（頁面重定向）"

patterns-established:
  - "Transient key pattern: PROFILE_TRANSIENT_PREFIX + state"
  - "BEM CSS classes: .buygo-line-register-form__*"
  - "Fallback form pattern: render_fallback_*() methods"
  - "Exception handling pattern: switch ($flow_type) with case FLOW_*"

# Metrics
duration: 3min
completed: 2026-01-28
---

# Phase 10 Plan 01: Register Flow Page 核心機制 Summary

**LINE profile Transient 儲存 + 動態 shortcode 註冊 + 完整例外流程處理（FLOW_REGISTER/FLOW_LINK）**

## Performance

- **Duration:** 3 min
- **Started:** 2026-01-28T22:22:27Z
- **Completed:** 2026-01-28T22:25:52Z
- **Tasks:** 3
- **Files modified:** 2

## Accomplishments

- RegisterFlowShortcode 可從 Transient 讀取 LINE profile 並渲染註冊表單
- Login_Handler 在 OAuth callback 時將 LINE profile 儲存到 Transient（10 分鐘 TTL）
- 動態 shortcode 註冊機制（register_shortcode_dynamically）
- 完整例外流程處理（switch 語句覆蓋 FLOW_REGISTER 和 FLOW_LINK）
- Fallback 表單機制（當未設定 Register Flow Page 時在 wp-login.php 顯示）

## Task Commits

Each task was committed atomically:

1. **Task 1: 建立 RegisterFlowShortcode 類別** - `94d33b9` (feat)
2. **Task 2: 擴展 Login_Handler** - `9039626` (feat)
3. **Task 3: 驗證 Shortcode 和 Transient 機制** - `e33731d` (test)

## Files Created/Modified

- `includes/shortcodes/class-register-flow-shortcode.php` - 渲染 LINE 註冊表單，從 Transient 讀取 profile
- `includes/handlers/class-login-handler.php` - 新增 Transient 儲存、動態 shortcode 註冊、完整例外處理

## Decisions Made

### 1. 使用 Transient API 儲存 LINE profile
- **原因:** WordPress 內建 API，自動處理過期和清理
- **TTL:** 10 分鐘（PROFILE_TRANSIENT_EXPIRY = 600）
- **Key pattern:** `buygo_line_profile_{state}`
- **優勢:** 不需要資料庫表，自動過期清理

### 2. 動態註冊 shortcode
- **原因:** 避免在 Plugin::onInit 靜態註冊（大多數請求不需要）
- **時機:** OAuth callback 偵測到新用戶時才註冊
- **實作:** `register_shortcode_dynamically($state)` 方法
- **優勢:** 效能最佳化，state 參數可在 closure 中傳遞

### 3. Shortcode 雙參數模式
- **參數 1:** `$atts` - WordPress shortcode 標準參數
- **參數 2:** `$exception_data` - 動態註冊時傳入（包含 state）
- **Fallback:** 從 URL `$_GET['state']` 讀取（重定向到 Register Flow Page 時）
- **優勢:** 同時支援動態註冊和頁面重定向兩種情境

### 4. 完整例外流程處理
- **實作:** switch ($flow_type) 語句覆蓋所有流程類型
- **FLOW_REGISTER:** 新用戶註冊（重定向到 Register Flow Page 或顯示 fallback）
- **FLOW_LINK:** 已登入用戶綁定（重定向到 Link Flow Page 或顯示 fallback）
- **Default:** 記錄警告但不中斷（未來擴展性）
- **優勢:** NSL-04 需求完整覆蓋

### 5. Fallback 表單機制
- **時機:** 當管理員未設定 Register Flow Page 或 Link Flow Page 時
- **位置:** wp-login.php（使用 WordPress 標準 login_header/footer）
- **實作:** `render_fallback_registration_form()` 和 `render_fallback_link_confirmation()`
- **優勢:** 外掛開箱即用，不需要強制設定頁面

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None - all implementations proceeded smoothly.

## User Setup Required

None - no external service configuration required.

However, for optimal user experience, administrators should:
- Create a page with `[buygo_line_register_flow]` shortcode
- Set the page ID in `buygo_line_register_flow_page` option
- Without this, fallback form will display on wp-login.php (functional but less customizable)

## Next Phase Readiness

**Ready for Phase 11 (完整註冊/登入/綁定流程):**
- ✅ RegisterFlowShortcode 已建立並可渲染表單
- ✅ Transient 機制已驗證可儲存/讀取 LINE profile
- ✅ 動態 shortcode 註冊機制運作正常
- ✅ 完整例外流程處理已實作（FLOW_REGISTER, FLOW_LINK）
- ✅ Fallback 表單提供基礎功能

**下一步:**
- Phase 11 將實作表單提交處理（buygo_line_register action）
- 整合 UserService 建立新用戶
- 整合 LineUserService 綁定 LINE UID
- 實作登入/綁定流程處理

**無阻礙項目**

---
*Phase: 10-register-flow-page-系統*
*Plan: 01*
*Completed: 2026-01-28*
