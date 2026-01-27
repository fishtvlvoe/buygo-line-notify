# Roadmap: BuyGo LINE Notify

## Overview

這個專案將建立一個通用的 WordPress LINE 整合外掛，從基礎設施開始，逐步實作 Webhook 系統、LINE Login、LIFF 支援、前台整合、通用通知 API，最後完成測試與文件。整個旅程分為七個階段，每個階段都交付一個可驗證的完整能力，讓任何 WordPress 網站都能輕鬆整合 LINE 功能。

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [ ] **Phase 1: 基礎設施與設定** - 資料庫結構、後台管理頁面、設定管理系統
- [ ] **Phase 2: Webhook 系統** - LINE Webhook 處理、簽名驗證、事件去重、背景處理
- [ ] **Phase 3: LINE Login 與用戶綁定** - OAuth 流程、用戶建立、帳號綁定、持久化儲存
- [ ] **Phase 4: LIFF 整合** - LIFF 頁面、LINE 瀏覽器偵測、自動登入、Cookie 處理
- [ ] **Phase 5: 前台整合** - 登入按鈕、綁定按鈕、結帳整合、Shortcode
- [ ] **Phase 6: 通用通知系統** - 通知 API、WooCommerce 整合、FluentCart 整合
- [ ] **Phase 7: 測試與文件** - 單元測試、使用文件、API 文件、遷移指南

## Phase Details

### Phase 1: 基礎設施與設定
**Goal**: 建立外掛運作所需的資料庫結構和設定管理系統

**Depends on**: Nothing (first phase)

**Requirements**: DB-01, DB-02, DB-03, ADMIN-01, ADMIN-02, ADMIN-03, ADMIN-04, ADMIN-05, SETTING-01, SETTING-02, SETTING-03, SETTING-04, SETTING-05, SETTING-06, SETTING-07, SETTING-08

**Success Criteria** (what must be TRUE):
  1. `wp_buygo_line_bindings` 資料表已建立且包含所有必要欄位（user_id, line_uid, display_name, picture_url 等）
  2. 管理員可在後台看到 LINE 設定頁面（根據 buygo-plus-one-dev 是否存在，位置在子選單或一級選單）
  3. 管理員可在設定頁面輸入並儲存所有 LINE API 金鑰（Channel Access Token、Channel Secret、Login Channel ID/Secret、LIFF ID/URL）
  4. 敏感設定資料已加密儲存，且能正確讀取舊有的 buygo_core_settings（向後相容）
  5. Webhook URL 以唯讀方式顯示在設定頁面，方便管理員複製到 LINE Developers Console

**Plans**: TBD

Plans:
- [ ] 01-01: [TBD during planning]

### Phase 2: Webhook 系統
**Goal**: 接收並處理來自 LINE 的 Webhook 事件

**Depends on**: Phase 1

**Requirements**: WEBHOOK-01, WEBHOOK-02, WEBHOOK-03, WEBHOOK-04, WEBHOOK-05, WEBHOOK-06

**Success Criteria** (what must be TRUE):
  1. LINE 平台可成功傳送 Webhook 事件到 `/wp-json/buygo-line-notify/v1/webhook` 且收到 200 回應
  2. Webhook endpoint 會驗證 x-line-signature，拒絕簽名無效的請求
  3. Verify Event（replyToken: 000...000）會被正確處理且不觸發實際業務邏輯
  4. 相同的 webhookEventId 只會被處理一次（事件去重機制運作正常）
  5. 其他外掛可透過 Hooks（如 `buygo_line_notify/webhook/message`）註冊並接收事件通知

**Plans**: TBD

Plans:
- [ ] 02-01: [TBD during planning]

### Phase 3: LINE Login 與用戶綁定
**Goal**: 用戶可透過 LINE 登入或綁定現有帳號

**Depends on**: Phase 1

**Requirements**: LOGIN-01, LOGIN-02, LOGIN-03, LOGIN-04, LOGIN-05, LOGIN-06, LOGIN-07, LOGIN-08

**Success Criteria** (what must be TRUE):
  1. 未登入用戶可透過 LINE Login 流程建立新的 WordPress 帳號（使用 LINE profile 的 name、picture、email）
  2. 已登入用戶可在帳號頁面綁定 LINE 帳號（LINE UID 同時寫入 user_meta 和 bindings 表）
  3. OAuth 流程使用持久化儲存系統（Session + Transient 自動切換）且 State 驗證機制運作正常（32 字元隨機碼 + 多重 fallback）
  4. Cookie 設定為 SameSite=Lax，支援 LINE 瀏覽器的 OAuth 回調
  5. 用戶完成 LINE Login 後會被引導加入 LINE 官方帳號（bot_prompt=aggressive）

**Plans**: TBD

Plans:
- [ ] 03-01: [TBD during planning]

### Phase 4: LIFF 整合
**Goal**: 在 LINE 內建瀏覽器中提供無縫的登入體驗

**Depends on**: Phase 3

**Requirements**: LIFF-01, LIFF-02, LIFF-03, LIFF-04, LIFF-05

**Success Criteria** (what must be TRUE):
  1. LIFF 頁面已建立且包含 LIFF SDK，可在 LINE 內建瀏覽器中載入
  2. 系統可偵測 LINE 瀏覽器（User-Agent 判斷）並自動導向 LIFF 頁面
  3. 用戶在 LINE 瀏覽器中可透過 LIFF SDK 自動登入（無需 OAuth redirect）
  4. WordPress Auth Cookie 在 LINE 環境中正常運作（用戶保持登入狀態）
  5. 登入完成後用戶會被導回原始頁面（保留 returnUrl 參數）

**Plans**: TBD

Plans:
- [ ] 04-01: [TBD during planning]

### Phase 5: 前台整合
**Goal**: 在 WordPress 前台各處提供 LINE 登入和綁定入口

**Depends on**: Phase 3, Phase 4

**Requirements**: BUTTON-01, BUTTON-02, BUTTON-03, BUTTON-04, BUTTON-05

**Success Criteria** (what must be TRUE):
  1. 登入/註冊頁面顯示「LINE 登入」按鈕，點擊後啟動 LINE Login 流程
  2. 我的帳號頁面顯示「綁定 LINE」按鈕（僅在用戶已登入但未綁定 LINE 時顯示）
  3. 結帳頁面顯示「LINE 快速結帳」按鈕（整合 LINE Login）
  4. 任何頁面或文章可使用 `[buygo_line_login]` Shortcode 顯示 LINE 登入按鈕
  5. 按鈕樣式可透過 Shortcode 屬性自訂（顏色、大小、文字）

**Plans**: TBD

Plans:
- [ ] 05-01: [TBD during planning]

### Phase 6: 通用通知系統
**Goal**: 提供通用 API 讓其他外掛可發送 LINE 通知

**Depends on**: Phase 1, Phase 2

**Requirements**: NOTIFY-01, NOTIFY-02, NOTIFY-03, NOTIFY-04, NOTIFY-05

**Success Criteria** (what must be TRUE):
  1. 其他外掛可透過 Facade API（如 `BuygoLineNotify::send_notification()`）發送 LINE 通知
  2. 發送通知前會自動偵測用戶是否已綁定 LINE（未綁定則不發送或降級為其他通知方式）
  3. WooCommerce 訂單狀態變更時會自動發送 LINE 通知給買家（透過 Hook 整合）
  4. FluentCart 訂單狀態變更時會自動發送 LINE 通知給買家（透過 Hook 整合）
  5. 其他外掛可透過 Hooks（如 `buygo_line_notify/notification/send`）註冊自訂的通知觸發器

**Plans**: TBD

Plans:
- [ ] 06-01: [TBD during planning]

### Phase 7: 測試與文件
**Goal**: 確保程式碼品質並提供完整的使用文件

**Depends on**: Phase 2, Phase 3, Phase 4

**Requirements**: TEST-01, TEST-02, TEST-03, TEST-04, DOC-01, DOC-02, DOC-03

**Success Criteria** (what must be TRUE):
  1. Webhook 簽名驗證有單元測試覆蓋，測試通過率 100%
  2. LINE Login OAuth 流程有單元測試覆蓋，包含 State 驗證、Token exchange、用戶建立等關鍵路徑
  3. LIFF 登入流程有測試文件說明如何手動測試（因為需要 LINE 環境）
  4. 使用文件（DOC-01）清楚說明如何設定外掛、如何在 LINE Developers Console 設定 Webhook URL 和 Redirect URI
  5. API 文件（DOC-02）列出所有 Facade 方法、可用的 Hooks 列表、以及範例程式碼

**Plans**: TBD

Plans:
- [ ] 07-01: [TBD during planning]

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2 → 3 → 4 → 5 → 6 → 7

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. 基礎設施與設定 | 0/TBD | Not started | - |
| 2. Webhook 系統 | 0/TBD | Not started | - |
| 3. LINE Login 與用戶綁定 | 0/TBD | Not started | - |
| 4. LIFF 整合 | 0/TBD | Not started | - |
| 5. 前台整合 | 0/TBD | Not started | - |
| 6. 通用通知系統 | 0/TBD | Not started | - |
| 7. 測試與文件 | 0/TBD | Not started | - |
