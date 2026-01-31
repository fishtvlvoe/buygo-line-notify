# Roadmap: buygo-line-notify 後台管理介面改造

## Overview

將 buygo-line-notify 外掛從單一設定頁面改造為具有 6 個 Tab 導航的專業管理介面。從後端設定架構開始，建立選單和導航骨架，實作各 Tab 內容，最後進行整合測試。所有 LINE 整合功能的設定都將在後台介面中輕鬆找到並調整，實現零程式碼配置。

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

- [ ] **Phase 1: 後端設定架構** - 建立統一設定管理和 REST API 端點
- [ ] **Phase 2: 選單與導航框架** - 建立頂層選單和 Tab 導航系統
- [ ] **Phase 3: 視覺設計系統** - 統一樣式、配色、響應式佈局
- [ ] **Phase 4: 核心 Tab 實作** - 入門、設定、按鈕三個 Tab
- [ ] **Phase 5: 進階 Tab 實作** - 同步數據、用法、LIFF 三個 Tab
- [ ] **Phase 6: 整合測試與修正** - 完整測試和相容性驗證

## Phase Details

### Phase 1: 後端設定架構
**Goal**: 所有設定值都能透過統一 API 儲存和讀取，前端介面有穩固的資料基礎
**Depends on**: Nothing (first phase)
**Requirements**: BACKEND-01, BACKEND-02, BACKEND-03, BACKEND-04, BACKEND-05, BACKEND-06, BACKEND-07, BACKEND-08, BACKEND-09, BACKEND-10
**Success Criteria** (what must be TRUE):
  1. 設定 REST API 可正確儲存和讀取所有設定值
  2. Email 擷取、按鈕位置、按鈕文字、按鈕樣式設定都有對應的資料結構
  3. 外掛整合開關可控制 FluentCart/WooCommerce 整合行為
  4. 所有設定值有預設值，驗證錯誤時有清楚回應
**Plans**: 2 plans

Plans:
- [ ] 01-01-PLAN.md — 統一設定 Service 和資料結構（Settings_Schema + SettingsService 擴展）
- [ ] 01-02-PLAN.md — 設定 REST API 端點（Settings_API 類別）

### Phase 2: 選單與導航框架
**Goal**: 後台出現「LINE 設定」頂層選單，點擊後看到 6 個 Tab 的導航列
**Depends on**: Phase 1
**Requirements**: MENU-01, MENU-02, MENU-03, MENU-04, NAV-01, NAV-02, NAV-03, NAV-04
**Success Criteria** (what must be TRUE):
  1. WordPress 後台出現「LINE 設定」頂層選單（取代「LINE 通知」）
  2. Tab 導航列顯示 6 個 Tab，點擊可切換
  3. Tab 狀態透過 URL 參數保持（重新整理不會跳回第一個 Tab）
  4. 響應式導航在手機上正常運作
**Plans**: TBD

Plans:
- [ ] 02-01: 頂層選單重構
- [ ] 02-02: Tab 導航系統

### Phase 3: 視覺設計系統
**Goal**: 統一的視覺風格套用到所有介面元素，符合 Nextend 風格但保持獨特設計
**Depends on**: Phase 2
**Requirements**: DESIGN-01, DESIGN-02, DESIGN-03, DESIGN-04, DESIGN-05, DESIGN-06
**Success Criteria** (what must be TRUE):
  1. 藍色標題列風格統一套用
  2. 表單元件（input、select、checkbox）樣式一致
  3. 成功/錯誤訊息有明確的視覺區分
  4. 桌面/平板/手機三種螢幕尺寸下介面正常顯示
**Plans**: TBD

Plans:
- [ ] 03-01: 設計系統 CSS 和元件樣式

### Phase 4: 核心 Tab 實作
**Goal**: 入門、設定、按鈕三個最重要的 Tab 完整可用
**Depends on**: Phase 3
**Requirements**: TAB-INTRO-01, TAB-INTRO-02, TAB-INTRO-03, TAB-INTRO-04, TAB-INTRO-05, TAB-INTRO-06, TAB-SETTINGS-01, TAB-SETTINGS-02, TAB-SETTINGS-03, TAB-SETTINGS-04, TAB-SETTINGS-05, TAB-SETTINGS-06, TAB-BUTTONS-01, TAB-BUTTONS-02, TAB-BUTTONS-03, TAB-BUTTONS-04
**Success Criteria** (what must be TRUE):
  1. 入門 Tab 顯示完整設定步驟指南，Webhook URL 可一鍵複製
  2. 設定 Tab 可編輯 Messaging API、LINE Login、Email、外掛整合設定
  3. 按鈕 Tab 可自訂按鈕位置、文字、樣式，並即時預覽效果
  4. 所有表單驗證和錯誤提示正常運作
  5. 敏感資料（token、secret）可切換顯示/隱藏
**Plans**: TBD

Plans:
- [ ] 04-01: 入門 Tab
- [ ] 04-02: 設定 Tab
- [ ] 04-03: 按鈕 Tab

### Phase 5: 進階 Tab 實作
**Goal**: 同步數據、用法、LIFF 三個 Tab 完整可用
**Depends on**: Phase 4
**Requirements**: TAB-SYNC-01, TAB-SYNC-02, TAB-SYNC-03, TAB-SYNC-04, TAB-SYNC-05, TAB-USAGE-01, TAB-USAGE-02, TAB-USAGE-03, TAB-USAGE-04, TAB-USAGE-05, TAB-LIFF-01, TAB-LIFF-02
**Success Criteria** (what must be TRUE):
  1. 同步數據 Tab 可設定同步開關、欄位選擇、衝突策略
  2. 同步記錄表格顯示歷史記錄，可清除記錄
  3. 用法 Tab 顯示所有 Shortcode 和 API 文檔，程式碼可一鍵複製
  4. LIFF Tab 顯示功能預告和 disabled 狀態的設定欄位
**Plans**: TBD

Plans:
- [ ] 05-01: 同步數據 Tab
- [ ] 05-02: 用法 Tab
- [ ] 05-03: LIFF Tab

### Phase 6: 整合測試與修正
**Goal**: 所有功能整合後正常運作，與現有功能相容
**Depends on**: Phase 5
**Requirements**: INTEGRATION-01, INTEGRATION-02, INTEGRATION-03, INTEGRATION-04, INTEGRATION-05
**Success Criteria** (what must be TRUE):
  1. 設定儲存後重新載入頁面，所有值正確顯示
  2. Tab 切換流暢，狀態保持正確
  3. 表單驗證阻擋無效輸入，顯示清楚錯誤訊息
  4. 非管理員無法存取設定頁面
  5. 現有的 LINE Login、Webhook、Profile Sync 功能不受影響
**Plans**: TBD

Plans:
- [ ] 06-01: 整合測試和修正

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2 → 3 → 4 → 5 → 6

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. 後端設定架構 | 0/2 | Planned | - |
| 2. 選單與導航框架 | 0/2 | Not started | - |
| 3. 視覺設計系統 | 0/1 | Not started | - |
| 4. 核心 Tab 實作 | 0/3 | Not started | - |
| 5. 進階 Tab 實作 | 0/3 | Not started | - |
| 6. 整合測試與修正 | 0/1 | Not started | - |

---
*Roadmap created: 2026-01-31*
*Phase 1 planned: 2026-02-01*
