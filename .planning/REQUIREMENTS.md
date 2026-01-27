# Requirements: BuyGo LINE Notify

**Defined:** 2026-01-28
**Core Value:** 讓任何 WordPress 網站都能輕鬆整合 LINE 功能，無需重複開發 LINE API 通訊邏輯，同時解決 LINE 內建瀏覽器的登入問題。

## v1 Requirements

### Webhook 系統

- [ ] **WEBHOOK-01**: 實作 REST API endpoint `/wp-json/buygo-line-notify/v1/webhook`
- [ ] **WEBHOOK-02**: 驗證 LINE Webhook 簽名（x-line-signature）
- [ ] **WEBHOOK-03**: 處理 Verify Event（replyToken: 000...000）
- [ ] **WEBHOOK-04**: 事件去重機制（使用 webhookEventId + transient）
- [ ] **WEBHOOK-05**: 背景處理支援（FastCGI / WordPress Cron）
- [ ] **WEBHOOK-06**: 提供 Hooks 讓其他外掛註冊事件處理器

### LINE Login 系統

- [ ] **LOGIN-01**: 實作 LINE Login OAuth 流程（authorize → callback → token exchange）
- [ ] **LOGIN-02**: 使用持久化儲存系統（Session + Transient 自動切換）
- [ ] **LOGIN-03**: 強大的 State 驗證（32 字元隨機 + 多重 fallback）
- [ ] **LOGIN-04**: Cookie SameSite 設定（支援 LINE 瀏覽器回調）
- [ ] **LOGIN-05**: 從 LINE Profile 建立 WordPress 用戶（name, picture, email）
- [ ] **LOGIN-06**: 已有用戶可綁定 LINE（登入後綁定功能）
- [ ] **LOGIN-07**: 混合儲存 LINE UID（user_meta + bindings 表）
- [ ] **LOGIN-08**: 強制引導用戶加入 LINE 官方帳號（bot_prompt=aggressive）

### LIFF 系統

- [ ] **LIFF-01**: 實作 LIFF 頁面（使用 LIFF SDK）
- [ ] **LIFF-02**: 偵測 LINE 瀏覽器並自動導向 LIFF 頁面
- [ ] **LIFF-03**: LIFF 自動登入（無需 OAuth redirect）
- [ ] **LIFF-04**: 設定 WordPress Auth Cookie 在 LINE 環境中正常運作
- [ ] **LIFF-05**: 登入後導回原始頁面

### 前台登入按鈕

- [ ] **BUTTON-01**: 登入/註冊頁面顯示「LINE 登入」按鈕
- [ ] **BUTTON-02**: 我的帳號頁面顯示「綁定 LINE」按鈕
- [ ] **BUTTON-03**: 結帳頁面顯示「LINE 快速結帳」按鈕
- [ ] **BUTTON-04**: 提供 Shortcode `[buygo_line_login]` 可放任意位置
- [ ] **BUTTON-05**: 按鈕樣式可自訂（顏色、大小、文字）

### 後台管理整合

- [ ] **ADMIN-01**: 偵測 buygo-plus-one-dev 是否存在
- [ ] **ADMIN-02**: 如果存在：掛載到 `buygo-plus-one` 父選單下（子選單：LINE 串接通知）
- [ ] **ADMIN-03**: 如果不存在：建立自己的一級選單
- [ ] **ADMIN-04**: 設定頁面包含所有必要欄位
- [ ] **ADMIN-05**: Webhook URL 唯讀顯示（方便複製）

### 設定管理

- [ ] **SETTING-01**: Channel Access Token（Messaging API）
- [ ] **SETTING-02**: Channel Secret（Messaging API）
- [ ] **SETTING-03**: LINE Login Channel ID
- [ ] **SETTING-04**: LINE Login Channel Secret
- [ ] **SETTING-05**: LIFF ID
- [ ] **SETTING-06**: LIFF Endpoint URL
- [ ] **SETTING-07**: 設定加密儲存（敏感資料）
- [ ] **SETTING-08**: 向後相容（讀取 buygo_core_settings）

### 通用通知系統

- [ ] **NOTIFY-01**: 提供 API 讓其他外掛發送 LINE 通知
- [ ] **NOTIFY-02**: 自動偵測用戶是否綁定 LINE
- [ ] **NOTIFY-03**: WooCommerce 整合（訂單狀態通知）
- [ ] **NOTIFY-04**: FluentCart 整合（訂單狀態通知）
- [ ] **NOTIFY-05**: 提供 Hooks 讓其他外掛註冊通知觸發器

### 資料庫結構

- [ ] **DB-01**: 建立/更新 `wp_buygo_line_bindings` 資料表
- [ ] **DB-02**: 綁定時同時寫入 user_meta 和 bindings 表
- [ ] **DB-03**: 提供查詢 API（根據 user_id 或 line_uid 查詢）

### 測試與文件

- [ ] **TEST-01**: Webhook 簽名驗證測試
- [ ] **TEST-02**: LINE Login OAuth 流程測試
- [ ] **TEST-03**: LIFF 登入測試
- [ ] **TEST-04**: 用戶建立/綁定測試
- [ ] **DOC-01**: 使用文件（如何設定、如何整合）
- [ ] **DOC-02**: API 文件（Facade 方法、Hooks 列表）
- [ ] **DOC-03**: 遷移指南（從 buygo-plus-one-dev 遷移 Webhook）

## v2 Requirements

### Rich Menu 管理

- **MENU-01**: 後台建立 Rich Menu
- **MENU-02**: 上傳 Rich Menu 圖片
- **MENU-03**: 設定 Rich Menu 按鈕動作
- **MENU-04**: 預覽 Rich Menu
- **MENU-05**: 啟用/停用 Rich Menu

### LINE Pay 整合

- **PAY-01**: LINE Pay 付款流程
- **PAY-02**: LINE Pay 退款處理
- **PAY-03**: LINE Pay 通知 Webhook

### Flex Message 視覺化編輯器

- **FLEX-01**: 拖拉式 Flex Message 編輯器
- **FLEX-02**: Flex Message 模板庫
- **FLEX-03**: Flex Message 預覽功能

### 進階分析

- **ANALYTICS-01**: LINE 訊息發送統計
- **ANALYTICS-02**: 用戶互動分析
- **ANALYTICS-03**: 轉換率追蹤

## Out of Scope

| Feature | Reason |
|---------|--------|
| 訊息模板系統 | 保留在 buygo-plus-one-dev，透過 Hooks 使用 |
| 商品上架邏輯 | buygo-plus-one-dev 的業務邏輯 |
| FluentCart 商品建立 | buygo-plus-one-dev 的業務邏輯 |
| LINE Beacon | 需要硬體設備，使用場景有限 |
| LINE Things | IoT 整合，超出專案範圍 |
| 多國語系 | v1 先支援繁體中文 |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| DB-01 | Phase 1 | Pending |
| DB-02 | Phase 1 | Pending |
| DB-03 | Phase 1 | Pending |
| ADMIN-01 | Phase 1 | Pending |
| ADMIN-02 | Phase 1 | Pending |
| ADMIN-03 | Phase 1 | Pending |
| ADMIN-04 | Phase 1 | Pending |
| ADMIN-05 | Phase 1 | Pending |
| SETTING-01 | Phase 1 | Pending |
| SETTING-02 | Phase 1 | Pending |
| SETTING-03 | Phase 1 | Pending |
| SETTING-04 | Phase 1 | Pending |
| SETTING-05 | Phase 1 | Pending |
| SETTING-06 | Phase 1 | Pending |
| SETTING-07 | Phase 1 | Pending |
| SETTING-08 | Phase 1 | Pending |
| WEBHOOK-01 | Phase 2 | Pending |
| WEBHOOK-02 | Phase 2 | Pending |
| WEBHOOK-03 | Phase 2 | Pending |
| WEBHOOK-04 | Phase 2 | Pending |
| WEBHOOK-05 | Phase 2 | Pending |
| WEBHOOK-06 | Phase 2 | Pending |
| LOGIN-01 | Phase 3 | Pending |
| LOGIN-02 | Phase 3 | Pending |
| LOGIN-03 | Phase 3 | Pending |
| LOGIN-04 | Phase 3 | Pending |
| LOGIN-05 | Phase 3 | Pending |
| LOGIN-06 | Phase 3 | Pending |
| LOGIN-07 | Phase 3 | Pending |
| LOGIN-08 | Phase 3 | Pending |
| LIFF-01 | Phase 4 | Pending |
| LIFF-02 | Phase 4 | Pending |
| LIFF-03 | Phase 4 | Pending |
| LIFF-04 | Phase 4 | Pending |
| LIFF-05 | Phase 4 | Pending |
| BUTTON-01 | Phase 5 | Pending |
| BUTTON-02 | Phase 5 | Pending |
| BUTTON-03 | Phase 5 | Pending |
| BUTTON-04 | Phase 5 | Pending |
| BUTTON-05 | Phase 5 | Pending |
| NOTIFY-01 | Phase 6 | Pending |
| NOTIFY-02 | Phase 6 | Pending |
| NOTIFY-03 | Phase 6 | Pending |
| NOTIFY-04 | Phase 6 | Pending |
| NOTIFY-05 | Phase 6 | Pending |
| TEST-01 | Phase 7 | Pending |
| TEST-02 | Phase 7 | Pending |
| TEST-03 | Phase 7 | Pending |
| TEST-04 | Phase 7 | Pending |
| DOC-01 | Phase 7 | Pending |
| DOC-02 | Phase 7 | Pending |
| DOC-03 | Phase 7 | Pending |

---
*Requirements defined: 2026-01-28*
*Last updated: 2026-01-28 after roadmap creation*
