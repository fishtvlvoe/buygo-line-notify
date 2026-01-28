# BuyGo LINE Notify - 通用 LINE 整合外掛

## What This Is

一個通用的 WordPress 外掛，提供完整的 LINE 平台整合功能，包含訊息收發、用戶登入、帳號綁定。此外掛可獨立運作，也可與其他外掛（如 buygo-plus-one-dev）協作，透過 Facade API 和 WordPress Hooks 提供 LINE 相關服務。

## Core Value

讓任何 WordPress 網站都能輕鬆整合 LINE 功能，無需重複開發 LINE API 通訊邏輯，同時解決 LINE 內建瀏覽器的登入問題。

## Current Milestone: v0.2 LINE Login 完整重構

**Goal:** 採用 Nextend Social Login 標準架構，建立符合 WordPress 生態系統標準的 LINE Login 系統

**Target features:**
- wp_buygo_line_users 專用資料表（取代混合儲存）
- 標準 WordPress URL 機制（取代 REST API）
- NSLContinuePageRenderException 模式
- Register Flow Page + Shortcode 系統
- 完整註冊/登入/綁定流程（含 auto-link）
- Profile Sync 系統（name, email, avatar）
- Avatar 整合（get_avatar_url hook）
- 前台整合（登入按鈕、綁定按鈕、shortcode）
- 後台管理（設定頁面、用戶列表、除錯工具）

**詳細目標文件:** `.planning/MILESTONE-v0.2-GOALS.md`

**LIFF 整合延後到 v0.3:** Nextend 架構已完美處理 LINE 瀏覽器問題，先驗證標準流程是否足夠

## Requirements

### Validated

<!-- 現有 codebase 已實現的功能 -->

- ✓ LineMessagingService 可發送 LINE 訊息（reply/push）— existing
- ✓ ImageUploader 可下載並上傳圖片到 WordPress Media Library — existing
- ✓ Logger 提供統一的日誌記錄 — existing
- ✓ SettingsService 管理外掛設定 — existing
- ✓ Facade API 提供簡化的呼叫介面 — existing
- ✓ PHPUnit 測試環境已建立 — existing
- ✓ Phase 1: 基礎設施與設定（資料庫、後台、設定加密）— v0.1
- ✓ Phase 14: Webhook 系統（endpoint、簽名驗證、去重、背景處理）— v0.1

### Active - v0.2 Milestone

<!-- 本次 Milestone 要完成的需求 — 基於 Nextend 架構重構 -->

#### 核心資料架構

- [ ] **ARCH-01**: 建立 wp_buygo_line_users 專用表（ID, type, identifier, user_id, register_date, link_date）
- [ ] **ARCH-02**: 取代混合儲存架構（移除 wp_buygo_line_bindings 和 user_meta 的混合使用）
- [ ] **ARCH-03**: 提供查詢 API（getUserByLineUid, getLineUidByUserId, isUserLinked）

#### 標準 WordPress URL 機制

- [ ] **URL-01**: 實作標準 WordPress 登入入口（wp-login.php?loginSocial=buygo-line）
- [ ] **URL-02**: OAuth callback 使用相同 URL（wp-login.php?loginSocial=buygo-line）
- [ ] **URL-03**: 取代原有的 REST API endpoint 架構
- [ ] **URL-04**: 整合到 WordPress login_url 和 logout_url hooks

#### NSLContinuePageRenderException 模式

- [ ] **NSL-01**: 實作 NSLContinuePageRenderException 例外類別
- [ ] **NSL-02**: OAuth callback 後拋出例外（允許 WordPress 繼續渲染）
- [ ] **NSL-03**: 動態註冊 shortcode 於例外處理中
- [ ] **NSL-04**: 例外捕捉與處理流程（與一般錯誤區分）

#### Register Flow Page 系統

- [ ] **RFP-01**: 後台設定「註冊流程頁面」選擇器
- [ ] **RFP-02**: 實作 `[buygo_line_register_flow]` shortcode
- [ ] **RFP-03**: Shortcode 渲染註冊表單（顯示 LINE profile 資料）
- [ ] **RFP-04**: 表單提交處理（建立 WP 用戶 + 寫入 wp_buygo_line_users）
- [ ] **RFP-05**: 註冊成功後自動登入並導回原始頁面

#### 完整註冊/登入/綁定流程

- [ ] **FLOW-01**: 新用戶註冊流程（LINE OAuth → profile → 建立 WP 用戶 → 寫表）
- [ ] **FLOW-02**: Auto-link 機制（email 已存在則自動關聯現有帳號）
- [ ] **FLOW-03**: 已登入用戶綁定流程（檢查 LINE UID 未重複 → 寫表）
- [ ] **FLOW-04**: 登入流程（LINE OAuth → 查 wp_buygo_line_users → WP 登入）
- [ ] **FLOW-05**: State 驗證（32 字元隨機 + hash_equals + 10 分鐘有效期）

#### Profile Sync 系統

- [ ] **SYNC-01**: 註冊時同步 LINE profile（display_name, email, picture）
- [ ] **SYNC-02**: 登入時更新 profile（可選，admin 設定）
- [ ] **SYNC-03**: 綁定時同步 profile
- [ ] **SYNC-04**: 衝突處理策略（LINE 優先 / WordPress 優先）
- [ ] **SYNC-05**: 同步日誌記錄（哪些欄位被更新）

#### Avatar 整合

- [ ] **AVATAR-01**: 實作 get_avatar_url filter hook
- [ ] **AVATAR-02**: 檢查用戶是否綁定 LINE（查 wp_buygo_line_users）
- [ ] **AVATAR-03**: 返回 LINE pictureUrl（若有綁定）
- [ ] **AVATAR-04**: Avatar URL 快取在 user_meta（避免重複查表）
- [ ] **AVATAR-05**: 快取失效機制（profile 更新時清除）

#### 持久化儲存系統（保留現有實作）

- [x] **STORAGE-01**: 三層 Fallback（Session → Transient → Option）— existing Phase 15
- [x] **STORAGE-02**: State 驗證（32 字元隨機 + hash_equals）— existing Phase 15
- [x] **STORAGE-03**: 10 分鐘有效期設定 — existing Phase 15
- [ ] **STORAGE-04**: 整合到新的 OAuth 流程（標準 WordPress URL）

#### 前台整合

- [ ] **FRONTEND-01**: 登入/註冊頁面「LINE 登入」按鈕
- [ ] **FRONTEND-02**: 我的帳號頁面「綁定 LINE」按鈕（僅未綁定時顯示）
- [ ] **FRONTEND-03**: Shortcode `[buygo_line_login]` 可放任意位置
- [ ] **FRONTEND-04**: 按鈕樣式自訂（顏色、大小、文字）
- [ ] **FRONTEND-05**: 登入/綁定成功後導回原始頁面

#### 後台管理

- [ ] **BACKEND-01**: 設定頁面：LINE Login Channel ID/Secret
- [ ] **BACKEND-02**: 設定頁面：註冊流程頁面選擇器
- [ ] **BACKEND-03**: 設定頁面：Profile Sync 選項
- [ ] **BACKEND-04**: 用戶列表：顯示 LINE 綁定狀態
- [ ] **BACKEND-05**: 除錯工具：State 驗證日誌、OAuth 流程追蹤

#### 測試與文件

- [ ] **TEST-01**: 註冊流程單元測試（新用戶、auto-link）
- [ ] **TEST-02**: 綁定流程單元測試（已登入用戶）
- [ ] **TEST-03**: Profile Sync 測試（衝突處理）
- [ ] **TEST-04**: Avatar 整合測試（hook、快取）
- [ ] **DOC-01**: 使用文件（設定教學、shortcode 使用）
- [ ] **DOC-02**: 架構文件（Nextend 模式說明、資料表結構）
- [ ] **DOC-03**: API 文件（查詢 API、Hooks 列表）

### Out of Scope (v0.2 Milestone)

- **LIFF 整合** — 延後到 v0.3（Nextend 架構已處理 LINE 瀏覽器問題）
- **通用通知系統** — 延後到 v0.3（需要先完成 LINE Login）
- **WooCommerce / FluentCart 整合** — 延後到 v0.3
- 訊息模板系統 — 保留在 buygo-plus-one-dev，透過 Hooks 使用
- 商品上架邏輯 — 保留在 buygo-plus-one-dev
- Rich Menu 管理 — 未來版本考慮
- LINE Pay 整合 — 未來版本考慮
- Flex Message 視覺化編輯器 — 未來版本考慮

## Context

### 技術環境

- **Framework**: WordPress Plugin
- **PHP Version**: 8.0+
- **Frontend**: LIFF SDK (JavaScript)
- **Testing**: PHPUnit 9.6
- **LINE APIs**:
  - Messaging API（訊息收發）
  - LINE Login（用戶登入）
  - LIFF（LINE 內建瀏覽器應用）

### 現有架構

**已完成的基礎設施：**
- `LineMessagingService` - LINE 訊息發送
- `ImageUploader` - 圖片處理
- `Logger` - 日誌記錄
- `SettingsService` - 設定管理
- `BuygoLineNotify` Facade - 統一 API

**現有整合（buygo-plus-one-dev）：**
- Webhook 處理在 `buygo-plus-one-dev/includes/api/class-line-webhook-api.php`
- 業務邏輯在 `buygo-plus-one-dev/includes/services/class-line-webhook-handler.php`
- 訊息模板在 `buygo-plus-one-dev/includes/services/class-notification-templates.php`

### LINE 登入技術參考

**v0.2 Milestone 完全採用 Nextend Social Login 架構：**

詳細分析文件：`.planning/NEXTEND-SOCIAL-LOGIN-ANALYSIS.md`

核心機制：
- **NSLContinuePageRenderException 模式**：OAuth callback 拋出特殊例外，讓 WordPress 繼續渲染頁面
- **Register Flow Page + Shortcode**：動態註冊 shortcode，在任何頁面顯示註冊表單
- **wp_social_users 專用表**：單一真實來源，取代混合儲存
- **標準 WordPress URL**：`wp-login.php?loginSocial=buygo-line`（取代 REST API）
- **完整 Profile Sync**：註冊/登入/綁定時同步 name、email、avatar
- **Avatar 整合**：`get_avatar_url` filter hook

**參考來源：**
- **Nextend Social Login Pro**: NSLContinuePageRenderException、Register Flow、wp_social_users 表
- **WooCommerce Notify**: 三層儲存 fallback（已保留使用）

### 已知問題與解決方案（LINE 內建瀏覽器）

**問題：**
1. **Cookie 限制** - LINE 瀏覽器可能清除 third-party cookies
2. **Session 失效** - 標準 WordPress session 在 LINE 環境中可能不穩定
3. **Redirect 阻擋** - OAuth redirect 可能被攔截

**v0.2 解決方案（Nextend 架構）：**
- **NSLContinuePageRenderException**：避免 redirect 被阻擋，讓頁面正常渲染
- **Register Flow Page**：在任何頁面顯示表單，無需複雜 redirect
- **三層儲存 fallback**：Session → Transient → Option（已保留）
- **標準 WordPress URL**：`wp-login.php`（不是 REST API，更穩定）

**v0.3 可能方案（若仍有問題）：**
- LIFF SDK 整合（無需 OAuth redirect）
- Cookie SameSite=Lax 強化

### 測試環境

- **WordPress**: Local by Flywheel
- **URL**: http://buygo.local 或 https://test.buygo.me
- **LINE Developers**: 需要兩個 Channel（Messaging API + LINE Login）
- **LIFF**: 需要建立 LIFF App

## Constraints

- **零中斷約束**: 現有的 buygo-plus-one-dev Webhook 必須繼續運作，直到完全遷移 — 向後相容
- **獨立運作約束**: 外掛必須可以在沒有 buygo-plus-one-dev 的情況下運作 — 不強制依賴
- **安全約束**: 所有 LINE API 金鑰必須加密儲存 — 安全性優先
- **效能約束**: Webhook 處理必須在 5 秒內返回 200（避免 LINE 重試）— 使用背景處理
- **相容性約束**: 必須支援 PHP 8.0+ 和 WordPress 6.0+ — 最低版本要求
- **LINE 約束**: 遵循 LINE Platform 使用條款和 API 限制 — 官方規範

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| **v0.2: 完全採用 Nextend 架構** | 架構純粹性、長期可維護、未來重要性高 | ✅ Confirmed 2026-01-29 |
| **v0.2: 使用 wp_buygo_line_users 專用表** | 單一真實來源，取代混合儲存 | ✅ Confirmed 2026-01-29 |
| **v0.2: 標準 WordPress URL 機制** | 比 REST API 更穩定、符合 WordPress 生態 | ✅ Confirmed 2026-01-29 |
| **v0.2: NSLContinuePageRenderException** | 完美處理 LINE 瀏覽器問題 | ✅ Confirmed 2026-01-29 |
| **v0.2: Register Flow Page + Shortcode** | 靈活整合、可放任何頁面 | ✅ Confirmed 2026-01-29 |
| **v0.2: LIFF 延後到 v0.3** | Nextend 架構已足夠，先驗證再決定 | ✅ Confirmed 2026-01-29 |
| 採用 Nextend 的持久化儲存架構 | 已驗證的解決方案，處理 LINE 瀏覽器問題 | ✅ Phase 15 implemented |
| Webhook 遷移到 buygo-line-notify | 基礎設施應在基礎層，業務邏輯透過 Hooks | ✅ Phase 14 implemented |
| 強制引導加入 LINE 官方帳號 | 確保可以發送 Push Message 通知 | — Deferred to v0.3 |
| 後台選單整合到 buygo-plus-one | 統一管理介面，提升用戶體驗 | ✅ Phase 1 implemented |
| 提供通用通知 API | 讓其他外掛也能使用 LINE 通知功能 | — Deferred to v0.3 |

---
*Last updated: 2026-01-29 after v0.2 Milestone definition (Nextend 架構重構)*
