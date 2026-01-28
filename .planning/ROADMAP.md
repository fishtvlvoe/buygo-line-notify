# Roadmap: BuyGo LINE Notify

## Overview

這個專案將建立一個通用的 WordPress LINE 整合外掛,從基礎設施開始,逐步實作 Webhook 系統、LINE Login、LIFF 支援、前台整合、通用通知 API,最後完成測試與文件。整個旅程分為七個階段,每個階段都交付一個可驗證的完整能力,讓任何 WordPress 網站都能輕鬆整合 LINE 功能。

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

### v0.1 Milestone (Completed)

- [x] **Phase 1: 基礎設施與設定** - 資料庫結構、後台管理頁面、設定管理系統
- [x] **Phase 2: Webhook 系統** - LINE Webhook 處理、簽名驗證、事件去重、背景處理
- [x] **Phase 3: LINE Login 與用戶綁定** - OAuth 流程、用戶建立、帳號綁定、持久化儲存 (舊版,已廢棄)
- [x] **Phase 4: LIFF 整合** - LIFF 頁面、LINE 瀏覽器偵測、自動登入、Cookie 處理 (延後到 v0.3)
- [x] **Phase 5: 前台整合** - 登入按鈕、綁定按鈕、結帳整合、Shortcode (舊版,已廢棄)
- [x] **Phase 6: 通用通知系統** - 通知 API、WooCommerce 整合、FluentCart 整合 (延後到 v0.3)
- [x] **Phase 7: 測試與文件** - 單元測試、使用文件、API 文件、遷移指南 (延後到 v0.3)

### v0.2 Milestone (LINE Login 完整重構 - Nextend 架構)

- [x] **Phase 8: 資料表架構與查詢 API** - wp_buygo_line_users 專用表、資料遷移、查詢 API
- [ ] **Phase 9: 標準 WordPress URL 機制** - login_init hook、OAuth callback、取代 REST API
- [ ] **Phase 10: Register Flow Page 系統** - NSLContinuePageRenderException、Shortcode、表單處理
- [ ] **Phase 11: 完整註冊/登入/綁定流程** - 新用戶註冊、Auto-link、已登入綁定、登入流程
- [ ] **Phase 12: Profile Sync 與 Avatar 整合** - Profile 同步、Avatar hook、快取機制
- [ ] **Phase 13: 前台整合** - 登入按鈕、綁定按鈕、Shortcode、樣式系統
- [ ] **Phase 14: 後台管理** - LINE Login 設定、Register Flow 設定、用戶列表、除錯工具
- [ ] **Phase 15: 測試與文件** - 單元測試、架構文件、API 文件、使用文件

## Phase Details

### Phase 1: 基礎設施與設定
**Goal**: 建立外掛運作所需的資料庫結構和設定管理系統

**Depends on**: Nothing (first phase)

**Requirements**: DB-01, DB-02, DB-03, ADMIN-01, ADMIN-02, ADMIN-03, ADMIN-04, ADMIN-05, SETTING-01, SETTING-02, SETTING-03, SETTING-04, SETTING-05, SETTING-06, SETTING-07, SETTING-08

**Success Criteria** (what must be TRUE):
  1. `wp_buygo_line_bindings` 資料表已建立且包含所有必要欄位（user_id, line_uid, display_name, picture_url 等）
  2. 管理員可在後台看到 LINE 設定頁面（根據 buygo-plus-one-dev 是否存在,位置在子選單或一級選單）
  3. 管理員可在設定頁面輸入並儲存所有 LINE API 金鑰（Channel Access Token、Channel Secret、Login Channel ID/Secret、LIFF ID/URL）
  4. 敏感設定資料已加密儲存,且能正確讀取舊有的 buygo_core_settings（向後相容）
  5. Webhook URL 以唯讀方式顯示在設定頁面,方便管理員複製到 LINE Developers Console

**Plans**: 4 plans in 3 waves

Plans:
- [x] 01-01-PLAN.md — 建立資料庫結構與 LINE 用戶綁定 API（混合儲存策略）
- [x] 01-02-PLAN.md — 實作設定加解密服務與向後相容讀取
- [x] 01-03-PLAN.md — 條件式後台選單整合（根據父外掛動態掛載）
- [x] 01-04-PLAN.md — 完整設定頁面 UI（表單、驗證、Webhook URL 複製）

### Phase 2: Webhook 系統
**Goal**: 接收並處理來自 LINE 的 Webhook 事件

**Depends on**: Phase 1

**Requirements**: WEBHOOK-01, WEBHOOK-02, WEBHOOK-03, WEBHOOK-04, WEBHOOK-05, WEBHOOK-06

**Success Criteria** (what must be TRUE):
  1. LINE 平台可成功傳送 Webhook 事件到 `/wp-json/buygo-line-notify/v1/webhook` 且收到 200 回應
  2. Webhook endpoint 會驗證 x-line-signature,拒絕簽名無效的請求
  3. Verify Event（replyToken: 000...000）會被正確處理且不觸發實際業務邏輯
  4. 相同的 webhookEventId 只會被處理一次（事件去重機制運作正常）
  5. 其他外掛可透過 Hooks（如 `buygo_line_notify/webhook/message`）註冊並接收事件通知

**Plans**: 2 plans in 2 waves

Plans:
- [x] 02-01-PLAN.md — 建立 Webhook REST API Endpoint 和事件處理器（簽名驗證、去重、Hooks）
- [x] 02-02-PLAN.md — 整合到外掛主流程並進行 LINE Console 驗證（含 checkpoint）

### Phase 3: LINE Login 與用戶綁定 (v0.1 - 已廢棄)
**Goal**: 用戶可透過 LINE 登入或綁定現有帳號

**Status**: 已由 v0.2 Milestone (Phase 8-15) 重構取代

**Depends on**: Phase 1

**Requirements**: LOGIN-01, LOGIN-02, LOGIN-03, LOGIN-04, LOGIN-05, LOGIN-06, LOGIN-07, LOGIN-08

**Success Criteria** (what must be TRUE):
  1. 未登入用戶可透過 LINE Login 流程建立新的 WordPress 帳號（使用 LINE profile 的 name、picture、email）
  2. 已登入用戶可在帳號頁面綁定 LINE 帳號（LINE UID 同時寫入 user_meta 和 bindings 表）
  3. OAuth 流程使用持久化儲存系統（Session + Transient 自動切換）且 State 驗證機制運作正常（32 字元隨機碼 + 多重 fallback）
  4. Cookie 設定為 SameSite=Lax,支援 LINE 瀏覽器的 OAuth 回調
  5. 用戶完成 LINE Login 後會被引導加入 LINE 官方帳號（bot_prompt=aggressive）

**Plans**: TBD

Plans:
- [ ] 03-01: [TBD during planning]

### Phase 4: LIFF 整合 (延後到 v0.3)
**Goal**: 在 LINE 內建瀏覽器中提供無縫的登入體驗

**Status**: 延後到 v0.3（Nextend 架構已處理 LINE 瀏覽器問題）

**Depends on**: Phase 3

**Requirements**: LIFF-01, LIFF-02, LIFF-03, LIFF-04, LIFF-05

**Success Criteria** (what must be TRUE):
  1. LIFF 頁面已建立且包含 LIFF SDK,可在 LINE 內建瀏覽器中載入
  2. 系統可偵測 LINE 瀏覽器（User-Agent 判斷）並自動導向 LIFF 頁面
  3. 用戶在 LINE 瀏覽器中可透過 LIFF SDK 自動登入（無需 OAuth redirect）
  4. WordPress Auth Cookie 在 LINE 環境中正常運作（用戶保持登入狀態）
  5. 登入完成後用戶會被導回原始頁面（保留 returnUrl 參數）

**Plans**: TBD

Plans:
- [ ] 04-01: [TBD during planning]

### Phase 5: 前台整合 (v0.1 - 已廢棄)
**Goal**: 在 WordPress 前台各處提供 LINE 登入和綁定入口

**Status**: 已由 v0.2 Phase 13 重構取代

**Depends on**: Phase 3, Phase 4

**Requirements**: BUTTON-01, BUTTON-02, BUTTON-03, BUTTON-04, BUTTON-05

**Success Criteria** (what must be TRUE):
  1. 登入/註冊頁面顯示「LINE 登入」按鈕,點擊後啟動 LINE Login 流程
  2. 我的帳號頁面顯示「綁定 LINE」按鈕（僅在用戶已登入但未綁定 LINE 時顯示）
  3. 結帳頁面顯示「LINE 快速結帳」按鈕（整合 LINE Login）
  4. 任何頁面或文章可使用 `[buygo_line_login]` Shortcode 顯示 LINE 登入按鈕
  5. 按鈕樣式可透過 Shortcode 屬性自訂（顏色、大小、文字）

**Plans**: TBD

Plans:
- [ ] 05-01: [TBD during planning]

### Phase 6: 通用通知系統 (延後到 v0.3)
**Goal**: 提供通用 API 讓其他外掛可發送 LINE 通知

**Status**: 延後到 v0.3（需要先完成 LINE Login）

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

### Phase 7: 測試與文件 (v0.1 - 延後到 v0.3)
**Goal**: 確保程式碼品質並提供完整的使用文件

**Status**: 延後到 v0.3（配合整體架構完成後撰寫）

**Depends on**: Phase 2, Phase 3, Phase 4

**Requirements**: TEST-01, TEST-02, TEST-03, TEST-04, DOC-01, DOC-02, DOC-03

**Success Criteria** (what must be TRUE):
  1. Webhook 簽名驗證有單元測試覆蓋,測試通過率 100%
  2. LINE Login OAuth 流程有單元測試覆蓋,包含 State 驗證、Token exchange、用戶建立等關鍵路徑
  3. LIFF 登入流程有測試文件說明如何手動測試（因為需要 LINE 環境）
  4. 使用文件（DOC-01）清楚說明如何設定外掛、如何在 LINE Developers Console 設定 Webhook URL 和 Redirect URI
  5. API 文件（DOC-02）列出所有 Facade 方法、可用的 Hooks 列表、以及範例程式碼

**Plans**: TBD

Plans:
- [ ] 07-01: [TBD during planning]

---

## v0.2 Milestone Phase Details

### Phase 8: 資料表架構與查詢 API
**Goal**: 建立 wp_buygo_line_users 專用資料表,取代混合儲存架構

**Depends on**: Phase 1 (v0.1)

**Requirements**: ARCH-01, ARCH-02, ARCH-03

**Success Criteria** (what must be TRUE):
  1. `wp_buygo_line_users` 資料表已建立,包含 ID、type、identifier、user_id、register_date、link_date 欄位
  2. 舊的 `wp_buygo_line_bindings` 資料已成功遷移到新表（register_date、link_date 正確對應）
  3. 查詢 API 可正確運作（getUserByLineUid、getLineUidByUserId、isUserLinked、linkUser、unlinkUser）
  4. 遷移狀態已記錄到 wp_options（buygo_line_migration_status），舊表保留未刪除
  5. 所有查詢使用新表作為單一真實來源（不再混合使用 user_meta）

**Plans**: 2 plans in 2 waves

Plans:
- [x] 08-01-PLAN.md — 建立 wp_buygo_line_users 資料表與資料遷移機制
- [x] 08-02-PLAN.md — 重構 LineUserService 查詢 API（七個核心方法）

### Phase 9: 標準 WordPress URL 機制
**Goal**: 實作標準 WordPress 登入入口,取代 REST API 架構

**Depends on**: Phase 8

**Requirements**: URL-01, URL-02, URL-03, URL-04, NSL-01

**Success Criteria** (what must be TRUE):
  1. `wp-login.php?loginSocial=buygo-line` 可正確啟動 LINE OAuth 流程（建立 State、導向 LINE）
  2. OAuth callback 使用相同 URL 接收,完成 code 換 token、token 換 profile 流程
  3. 舊的 REST API endpoint (`/wp-json/buygo-line-notify/v1/login/*`) 已標記為 deprecated
  4. NSLContinuePageRenderException 例外類別已建立,可正確被捕捉與處理
  5. Login_Handler 已整合到 Plugin,login_init hook 正確註冊

**Plans**: 3 plans in 2 waves

Plans:
- [x] 09-01-PLAN.md — Login Handler 基礎架構（NSLContinuePageRenderException、Login_Handler、LoginService 更新）
- [x] 09-02-PLAN.md — 整合 Login_Handler 到 Plugin 並標記 REST API deprecated
- [x] 09-03-PLAN.md — URL Filter Service（login_url/logout_url filters）

### Phase 10: Register Flow Page 系統
**Goal**: 實作 Register Flow Page 機制,讓 OAuth callback 後可在任意頁面顯示註冊表單

**Depends on**: Phase 9

**Requirements**: NSL-02, NSL-03, NSL-04, RFP-01, RFP-02, RFP-03, RFP-04, RFP-05

**Success Criteria** (what must be TRUE):
  1. 管理員可在後台選擇「註冊流程頁面」,系統建議建立包含 `[buygo_line_register_flow]` shortcode 的頁面
  2. OAuth callback 完成後,系統拋出 NSLContinuePageRenderException,動態註冊 shortcode
  3. Shortcode 可正確渲染註冊表單,顯示 LINE profile（頭像、名稱、email）
  4. 表單提交處理正確（驗證 State、建立 WP 用戶、寫入 wp_buygo_line_users、自動登入）
  5. 註冊成功後用戶被導回原始頁面（從 StateManager 讀取 returnUrl）

**Plans**: TBD

Plans:
- [ ] 10-01: [TBD during planning]

### Phase 11: 完整註冊/登入/綁定流程
**Goal**: 實作完整的新用戶註冊、Auto-link、已登入綁定、登入流程

**Depends on**: Phase 10

**Requirements**: FLOW-01, FLOW-02, FLOW-03, FLOW-04, FLOW-05, STORAGE-04

**Success Criteria** (what must be TRUE):
  1. 新用戶可完成完整註冊流程（LINE OAuth → Register Flow Page → 建立 WP 用戶 → 登入）
  2. Email 已存在時,系統自動執行 Auto-link（關聯現有帳號,不建立新用戶）
  3. 已登入用戶可在「我的帳號」綁定 LINE（檢查 LINE UID 未重複,寫入 link_date）
  4. 已註冊用戶可透過 LINE Login 直接登入（識別 identifier,讀取 user_id,自動登入）
  5. State 驗證機制運作正常（32 字元隨機、hash_equals、10 分鐘有效期、三層儲存）

**Plans**: TBD

Plans:
- [ ] 11-01: [TBD during planning]

### Phase 12: Profile Sync 與 Avatar 整合
**Goal**: 實作 Profile 同步機制與 Avatar 整合

**Depends on**: Phase 11

**Requirements**: SYNC-01, SYNC-02, SYNC-03, SYNC-04, SYNC-05, AVATAR-01, AVATAR-02, AVATAR-03, AVATAR-04, AVATAR-05

**Success Criteria** (what must be TRUE):
  1. 註冊時自動同步 LINE profile（display_name、email、pictureUrl 寫入 WP 用戶與 user_meta）
  2. 登入時可選擇是否更新 profile（後台設定 `buygo_line_sync_on_login`）
  3. 衝突處理策略可在後台設定（LINE 優先/WordPress 優先/手動處理）
  4. 同步日誌記錄到 wp_options（最近 10 筆,JSON 格式）
  5. get_avatar_url filter hook 已實作,已綁定 LINE 的用戶顯示 LINE 頭像,快取 7 天

**Plans**: TBD

Plans:
- [ ] 12-01: [TBD during planning]

### Phase 13: 前台整合
**Goal**: 在前台提供 LINE 登入和綁定入口

**Depends on**: Phase 11

**Requirements**: FRONTEND-01, FRONTEND-02, FRONTEND-03, FRONTEND-04, FRONTEND-05

**Success Criteria** (what must be TRUE):
  1. `wp-login.php` 頁面顯示「使用 LINE 登入」按鈕,點擊後啟動 OAuth 流程
  2. 我的帳號頁面顯示 LINE 綁定狀態（未綁定顯示「綁定 LINE」按鈕,已綁定顯示頭像+名稱+解除綁定）
  3. `[buygo_line_login]` shortcode 可在任何位置使用,支援自訂屬性（button_text、button_color、button_size、redirect_to）
  4. 按鈕樣式符合 LINE 官方規範（#00B900 綠色、LINE logo、響應式、hover 效果）
  5. 登入/綁定成功後正確導向（讀取 returnUrl,顯示 WordPress admin_notices）

**Plans**: TBD

Plans:
- [ ] 13-01: [TBD during planning]

### Phase 14: 後台管理
**Goal**: 提供完整的後台設定與管理介面

**Depends on**: Phase 11

**Requirements**: BACKEND-01, BACKEND-02, BACKEND-03, BACKEND-04, BACKEND-05

**Success Criteria** (what must be TRUE):
  1. 設定頁面包含 LINE Login Channel ID/Secret 欄位,Redirect URI 以唯讀方式顯示（含複製按鈕）
  2. Register Flow Page 選擇器已整合,可快速建立包含 shortcode 的頁面（AJAX 建立按鈕）
  3. Profile Sync 設定完整（登入時更新 checkbox、衝突策略 radio、清除快取按鈕）
  4. 用戶列表顯示「LINE 綁定」欄位（已綁定顯示頭像+名稱,可排序可篩選）
  5. 除錯工具頁面可查看 State 驗證日誌、OAuth 流程追蹤、清除快取（最近 50 筆記錄）

**Plans**: TBD

Plans:
- [ ] 14-01: [TBD during planning]

### Phase 15: 測試與文件
**Goal**: 建立完整的測試與文件

**Depends on**: Phase 12, Phase 13, Phase 14

**Requirements**: TEST-01, TEST-02, TEST-03, TEST-04, DOC-01, DOC-02, DOC-03

**Success Criteria** (what must be TRUE):
  1. 註冊流程有單元測試覆蓋（新用戶、Auto-link、Profile Sync、State 驗證、API 失敗）
  2. 綁定流程有單元測試覆蓋（成功綁定、UID 衝突、解除綁定）
  3. Profile Sync 測試完整（LINE 優先、WP 優先、衝突處理、日誌記錄）
  4. Avatar 整合測試完整（hook、快取、過期、API 失敗）
  5. 文件完整（使用文件、架構文件、API 文件,包含 Nextend 架構說明與 Hooks 列表）

**Plans**: TBD

Plans:
- [ ] 15-01: [TBD during planning]

---

## Progress

**Execution Order:**
- v0.1: 1 → 2 → 3 → 4 → 5 → 6 → 7 (Phase 3-7 部分完成或延後)
- v0.2: 8 → 9 → 10 → 11 → 12 → 13 → 14 → 15

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| **v0.1 Milestone** | | | |
| 1. 基礎設施與設定 | 4/4 | ✅ Completed | 2026-01-28 |
| 2. Webhook 系統 | 2/2 | ✅ Completed | 2026-01-28 |
| 3. LINE Login（舊版） | 0/TBD | 🚫 Deprecated | - |
| 4. LIFF 整合 | 0/TBD | ⏸️ Deferred to v0.3 | - |
| 5. 前台整合（舊版） | 0/TBD | 🚫 Deprecated | - |
| 6. 通用通知系統 | 0/TBD | ⏸️ Deferred to v0.3 | - |
| 7. 測試與文件（舊版） | 0/TBD | ⏸️ Deferred to v0.3 | - |
| **v0.2 Milestone (Nextend 架構重構)** | | | |
| 8. 資料表架構與查詢 API | 2/2 | ✅ Completed | 2026-01-29 |
| 9. 標準 WordPress URL 機制 | 3/3 | ✅ Completed | 2026-01-29 |
| 10. Register Flow Page 系統 | 0/TBD | Not started | - |
| 11. 完整註冊/登入/綁定流程 | 0/TBD | Not started | - |
| 12. Profile Sync 與 Avatar 整合 | 0/TBD | Not started | - |
| 13. 前台整合 | 0/TBD | Not started | - |
| 14. 後台管理 | 0/TBD | Not started | - |
| 15. 測試與文件 | 0/TBD | Not started | - |
