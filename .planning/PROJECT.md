# BuyGo LINE Notify - 通用 LINE 整合外掛

## What This Is

一個通用的 WordPress 外掛，提供完整的 LINE 平台整合功能，包含訊息收發、用戶登入、帳號綁定。此外掛可獨立運作，也可與其他外掛（如 buygo-plus-one-dev）協作，透過 Facade API 和 WordPress Hooks 提供 LINE 相關服務。

## Core Value

讓任何 WordPress 網站都能輕鬆整合 LINE 功能，無需重複開發 LINE API 通訊邏輯，同時解決 LINE 內建瀏覽器的登入問題。

## Requirements

### Validated

<!-- 現有 codebase 已實現的功能 -->

- ✓ LineMessagingService 可發送 LINE 訊息（reply/push）— existing
- ✓ ImageUploader 可下載並上傳圖片到 WordPress Media Library — existing
- ✓ Logger 提供統一的日誌記錄 — existing
- ✓ SettingsService 管理外掛設定 — existing
- ✓ Facade API 提供簡化的呼叫介面 — existing
- ✓ PHPUnit 測試環境已建立 — existing

### Active

<!-- 本次專案要完成的需求 -->

#### Webhook 系統（從 buygo-plus-one-dev 遷移）

- [ ] **WEBHOOK-01**: 實作 REST API endpoint `/wp-json/buygo-line-notify/v1/webhook`
- [ ] **WEBHOOK-02**: 驗證 LINE Webhook 簽名（x-line-signature）
- [ ] **WEBHOOK-03**: 處理 Verify Event（replyToken: 000...000）
- [ ] **WEBHOOK-04**: 事件去重機制（使用 webhookEventId + transient）
- [ ] **WEBHOOK-05**: 背景處理支援（FastCGI / WordPress Cron）
- [ ] **WEBHOOK-06**: 提供 Hooks 讓其他外掛註冊事件處理器

#### LINE Login 系統（新增）

- [ ] **LOGIN-01**: 實作 LINE Login OAuth 流程（authorize → callback → token exchange）
- [ ] **LOGIN-02**: 使用 Nextend 風格的持久化儲存系統（Session + Transient 自動切換）
- [ ] **LOGIN-03**: 強大的 State 驗證（32 字元隨機 + 多重 fallback）
- [ ] **LOGIN-04**: Cookie SameSite 設定（支援 LINE 瀏覽器回調）
- [ ] **LOGIN-05**: 從 LINE Profile 建立 WordPress 用戶（name, picture, email）
- [ ] **LOGIN-06**: 已有用戶可綁定 LINE（登入後綁定功能）
- [ ] **LOGIN-07**: 混合儲存 LINE UID（user_meta + bindings 表）
- [ ] **LOGIN-08**: 強制引導用戶加入 LINE 官方帳號（bot_prompt=aggressive）

#### LIFF 系統（LINE 內建瀏覽器支援）

- [ ] **LIFF-01**: 實作 LIFF 頁面（使用 LIFF SDK）
- [ ] **LIFF-02**: 偵測 LINE 瀏覽器並自動導向 LIFF 頁面
- [ ] **LIFF-03**: LIFF 自動登入（無需 OAuth redirect）
- [ ] **LIFF-04**: 設定 WordPress Auth Cookie 在 LINE 環境中正常運作
- [ ] **LIFF-05**: 登入後導回原始頁面

#### 前台登入按鈕

- [ ] **BUTTON-01**: 登入/註冊頁面顯示「LINE 登入」按鈕
- [ ] **BUTTON-02**: 我的帳號頁面顯示「綁定 LINE」按鈕
- [ ] **BUTTON-03**: 結帳頁面顯示「LINE 快速結帳」按鈕
- [ ] **BUTTON-04**: 提供 Shortcode `[buygo_line_login]` 可放任意位置
- [ ] **BUTTON-05**: 按鈕樣式可自訂（顏色、大小、文字）

#### 後台管理整合

- [ ] **ADMIN-01**: 偵測 buygo-plus-one-dev 是否存在
- [ ] **ADMIN-02**: 如果存在：掛載到 `buygo-plus-one` 父選單下（子選單：LINE 串接通知）
- [ ] **ADMIN-03**: 如果不存在：建立自己的一級選單
- [ ] **ADMIN-04**: 設定頁面包含所有必要欄位（見下方）
- [ ] **ADMIN-05**: Webhook URL 唯讀顯示（方便複製）

#### 設定管理

- [ ] **SETTING-01**: Channel Access Token（Messaging API）
- [ ] **SETTING-02**: Channel Secret（Messaging API）
- [ ] **SETTING-03**: LINE Login Channel ID
- [ ] **SETTING-04**: LINE Login Channel Secret
- [ ] **SETTING-05**: LIFF ID
- [ ] **SETTING-06**: LIFF Endpoint URL
- [ ] **SETTING-07**: 設定加密儲存（敏感資料）
- [ ] **SETTING-08**: 向後相容（讀取 buygo_core_settings）

#### 通用通知系統

- [ ] **NOTIFY-01**: 提供 API 讓其他外掛發送 LINE 通知
- [ ] **NOTIFY-02**: 自動偵測用戶是否綁定 LINE
- [ ] **NOTIFY-03**: WooCommerce 整合（訂單狀態通知）
- [ ] **NOTIFY-04**: FluentCart 整合（訂單狀態通知）
- [ ] **NOTIFY-05**: 提供 Hooks 讓其他外掛註冊通知觸發器

#### 資料庫結構

- [ ] **DB-01**: 建立/更新 `wp_buygo_line_bindings` 資料表
- [ ] **DB-02**: 綁定時同時寫入 user_meta 和 bindings 表
- [ ] **DB-03**: 提供查詢 API（根據 user_id 或 line_uid 查詢）

#### 測試與文件

- [ ] **TEST-01**: Webhook 簽名驗證測試
- [ ] **TEST-02**: LINE Login OAuth 流程測試
- [ ] **TEST-03**: LIFF 登入測試
- [ ] **TEST-04**: 用戶建立/綁定測試
- [ ] **DOC-01**: 使用文件（如何設定、如何整合）
- [ ] **DOC-02**: API 文件（Facade 方法、Hooks 列表）
- [ ] **DOC-03**: 遷移指南（從 buygo-plus-one-dev 遷移 Webhook）

### Out of Scope

- 訊息模板系統 — 保留在 buygo-plus-one-dev，透過 Hooks 使用
- 商品上架邏輯 — 保留在 buygo-plus-one-dev
- FluentCart 商品建立 — 保留在 buygo-plus-one-dev
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

已探索兩個外掛的實作方式：
- **WooCommerce Notify**: 雙重儲存（Session + Transient）、HTTP 快取標頭
- **Nextend Social Login Pro**: 智慧型持久化系統、強大的 State 驗證、WP Engine 相容

採用 Nextend 的架構 + WooCommerce Notify 的 workarounds。

### 已知問題（LINE 內建瀏覽器）

1. **Cookie 限制** - LINE 瀏覽器可能清除 third-party cookies
2. **Session 失效** - 標準 WordPress session 在 LINE 環境中可能不穩定
3. **Redirect 阻擋** - OAuth redirect 可能被攔截

**解決方案：**
- 使用 LIFF SDK（無需 redirect）
- Cookie SameSite=Lax（支援 GET 回調）
- 持久化儲存系統（Session + Transient fallback）

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
| 使用混合儲存（user_meta + bindings 表） | user_meta 快速查詢，bindings 表完整歷史 | — Pending |
| 採用 Nextend 的持久化儲存架構 | 已驗證的解決方案，處理 LINE 瀏覽器問題 | — Pending |
| Webhook 遷移到 buygo-line-notify | 基礎設施應在基礎層，業務邏輯透過 Hooks | — Pending |
| 強制引導加入 LINE 官方帳號 | 確保可以發送 Push Message 通知 | — Pending |
| 使用 LIFF 解決 LINE 瀏覽器登入 | 避免 OAuth redirect 被阻擋 | — Pending |
| 後台選單整合到 buygo-plus-one | 統一管理介面，提升用戶體驗 | — Pending |
| 提供通用通知 API | 讓其他外掛也能使用 LINE 通知功能 | — Pending |

---
*Last updated: 2026-01-28 after initialization*
