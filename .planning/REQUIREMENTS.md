# Requirements: buygo-line-notify 後台管理介面改造

**Defined:** 2026-01-31
**Core Value:** 所有 LINE 整合功能的設定都能在後台介面輕鬆找到並調整，實現零程式碼配置

## v1 Requirements

### 後端架構 (BACKEND)

- [ ] **BACKEND-01**: 建立統一的設定管理 Service
- [ ] **BACKEND-02**: Email 擷取設定（是否擷取、必填/選填、來源選擇）
- [ ] **BACKEND-03**: 按鈕位置設定（登入/註冊表單位置）
- [ ] **BACKEND-04**: 按鈕文字自訂（登入/註冊/綁定/解綁）
- [ ] **BACKEND-05**: 按鈕樣式設定（LINE 官方/簡約/自訂）
- [ ] **BACKEND-06**: 外掛整合開關（FluentCart/WooCommerce/其他）
- [ ] **BACKEND-07**: Profile Sync 衝突策略設定
- [ ] **BACKEND-08**: LIFF 設定欄位結構（預留）
- [ ] **BACKEND-09**: 設定值驗證和預設值處理
- [ ] **BACKEND-10**: 設定 REST API 端點（GET/POST）

### 選單結構 (MENU)

- [ ] **MENU-01**: 建立頂層選單「LINE 設定」
- [ ] **MENU-02**: 移除舊的「LINE 通知」選單
- [ ] **MENU-03**: 相容 buygo-plus-one 父選單（如果存在）
- [ ] **MENU-04**: 選單權限控制（manage_options）

### Tab 導航系統 (NAV)

- [ ] **NAV-01**: Tab 導航列元件（6 個 Tab）
- [ ] **NAV-02**: Tab 狀態管理（active/inactive）
- [ ] **NAV-03**: Tab URL 路由（?tab=getting-started 等）
- [ ] **NAV-04**: 響應式 Tab 導航（桌面/平板/手機）

### Tab 1：入門 (TAB-INTRO)

- [ ] **TAB-INTRO-01**: 歡迎訊息區塊
- [ ] **TAB-INTRO-02**: 步驟 1 - LINE 開發者帳號引導
- [ ] **TAB-INTRO-03**: 步驟 2 - 取得 Channel 資訊說明
- [ ] **TAB-INTRO-04**: 步驟 3 - Webhook URL 顯示和複製
- [ ] **TAB-INTRO-05**: 步驟 4 - 連線測試功能
- [ ] **TAB-INTRO-06**: 常見問題 FAQ（Accordion）

### Tab 2：設定 (TAB-SETTINGS)

- [ ] **TAB-SETTINGS-01**: Messaging API 設定區塊
- [ ] **TAB-SETTINGS-02**: LINE Login 設定區塊
- [ ] **TAB-SETTINGS-03**: Email 設定區塊（擷取/來源/必填）
- [ ] **TAB-SETTINGS-04**: 外掛整合區塊（FluentCart/WooCommerce/其他）
- [ ] **TAB-SETTINGS-05**: 敏感資料顯示/隱藏切換
- [ ] **TAB-SETTINGS-06**: 表單驗證和錯誤提示

### Tab 3：按鈕 (TAB-BUTTONS)

- [ ] **TAB-BUTTONS-01**: 按鈕位置設定（登入/註冊表單）
- [ ] **TAB-BUTTONS-02**: 按鈕文字自訂（4 種按鈕）
- [ ] **TAB-BUTTONS-03**: 按鈕樣式選擇器
- [ ] **TAB-BUTTONS-04**: 即時按鈕預覽功能

### Tab 4：同步數據 (TAB-SYNC)

- [ ] **TAB-SYNC-01**: 同步開關設定（登入時/定期）
- [ ] **TAB-SYNC-02**: 同步欄位選擇（名稱/頭像/Email）
- [ ] **TAB-SYNC-03**: 衝突處理策略選擇
- [ ] **TAB-SYNC-04**: 同步記錄表格顯示
- [ ] **TAB-SYNC-05**: 清除記錄功能

### Tab 5：用法 (TAB-USAGE)

- [ ] **TAB-USAGE-01**: Shortcode 文檔（buygo_line_login）
- [ ] **TAB-USAGE-02**: Shortcode 文檔（buygo_line_binding）
- [ ] **TAB-USAGE-03**: Shortcode 文檔（buygo_line_register_flow）
- [ ] **TAB-USAGE-04**: REST API 端點文檔
- [ ] **TAB-USAGE-05**: 程式碼複製功能

### Tab 6：LIFF (TAB-LIFF)

- [ ] **TAB-LIFF-01**: 功能預告訊息區塊
- [ ] **TAB-LIFF-02**: LIFF 設定欄位（disabled 狀態）

### 視覺設計 (DESIGN)

- [ ] **DESIGN-01**: Nextend 風格藍色標題列
- [ ] **DESIGN-02**: 統一的配色系統
- [ ] **DESIGN-03**: 統一的表單元件樣式
- [ ] **DESIGN-04**: 成功/錯誤訊息提示樣式
- [ ] **DESIGN-05**: Loading 狀態設計
- [ ] **DESIGN-06**: 響應式佈局（桌面/平板/手機）

### 整合測試 (INTEGRATION)

- [ ] **INTEGRATION-01**: 設定儲存和讀取測試
- [ ] **INTEGRATION-02**: Tab 切換功能測試
- [ ] **INTEGRATION-03**: 表單驗證測試
- [ ] **INTEGRATION-04**: 權限控制測試
- [ ] **INTEGRATION-05**: 相容性測試（與現有功能）

## v2 Requirements

（未來版本）

### 進階功能
- **ADVANCED-01**: 設定匯入/匯出功能
- **ADVANCED-02**: 多站點支援
- **ADVANCED-03**: LIFF 功能完整實作
- **ADVANCED-04**: A/B 測試按鈕樣式
- **ADVANCED-05**: 詳細的分析統計

### 使用者體驗
- **UX-01**: 設定精靈（Wizard）模式
- **UX-02**: 情境式說明（Contextual help）
- **UX-03**: 鍵盤快捷鍵支援

## Out of Scope

| Feature | Reason |
|---------|--------|
| 前台使用者介面改造 | 本次僅改造後台管理介面 |
| LIFF 功能實作 | 僅建立設定介面，功能未來開發 |
| 多語系支援 | 目前僅繁體中文，未來再擴展 |
| 行動裝置專用 App | 響應式網頁即可，無需原生 App |
| 即時通訊功能 | 超出 LINE Notify 範圍 |
| 自動化測試 | 手動測試為主，自動化測試未來加入 |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| BACKEND-01 | Phase 1 | Pending |
| BACKEND-02 | Phase 1 | Pending |
| BACKEND-03 | Phase 1 | Pending |
| BACKEND-04 | Phase 1 | Pending |
| BACKEND-05 | Phase 1 | Pending |
| BACKEND-06 | Phase 1 | Pending |
| BACKEND-07 | Phase 1 | Pending |
| BACKEND-08 | Phase 1 | Pending |
| BACKEND-09 | Phase 1 | Pending |
| BACKEND-10 | Phase 1 | Pending |
| MENU-01 | Phase 2 | Pending |
| MENU-02 | Phase 2 | Pending |
| MENU-03 | Phase 2 | Pending |
| MENU-04 | Phase 2 | Pending |
| NAV-01 | Phase 2 | Pending |
| NAV-02 | Phase 2 | Pending |
| NAV-03 | Phase 2 | Pending |
| NAV-04 | Phase 2 | Pending |
| DESIGN-01 | Phase 3 | Pending |
| DESIGN-02 | Phase 3 | Pending |
| DESIGN-03 | Phase 3 | Pending |
| DESIGN-04 | Phase 3 | Pending |
| DESIGN-05 | Phase 3 | Pending |
| DESIGN-06 | Phase 3 | Pending |
| TAB-INTRO-01 | Phase 4 | Pending |
| TAB-INTRO-02 | Phase 4 | Pending |
| TAB-INTRO-03 | Phase 4 | Pending |
| TAB-INTRO-04 | Phase 4 | Pending |
| TAB-INTRO-05 | Phase 4 | Pending |
| TAB-INTRO-06 | Phase 4 | Pending |
| TAB-SETTINGS-01 | Phase 4 | Pending |
| TAB-SETTINGS-02 | Phase 4 | Pending |
| TAB-SETTINGS-03 | Phase 4 | Pending |
| TAB-SETTINGS-04 | Phase 4 | Pending |
| TAB-SETTINGS-05 | Phase 4 | Pending |
| TAB-SETTINGS-06 | Phase 4 | Pending |
| TAB-BUTTONS-01 | Phase 4 | Pending |
| TAB-BUTTONS-02 | Phase 4 | Pending |
| TAB-BUTTONS-03 | Phase 4 | Pending |
| TAB-BUTTONS-04 | Phase 4 | Pending |
| TAB-SYNC-01 | Phase 5 | Pending |
| TAB-SYNC-02 | Phase 5 | Pending |
| TAB-SYNC-03 | Phase 5 | Pending |
| TAB-SYNC-04 | Phase 5 | Pending |
| TAB-SYNC-05 | Phase 5 | Pending |
| TAB-USAGE-01 | Phase 5 | Pending |
| TAB-USAGE-02 | Phase 5 | Pending |
| TAB-USAGE-03 | Phase 5 | Pending |
| TAB-USAGE-04 | Phase 5 | Pending |
| TAB-USAGE-05 | Phase 5 | Pending |
| TAB-LIFF-01 | Phase 5 | Pending |
| TAB-LIFF-02 | Phase 5 | Pending |
| INTEGRATION-01 | Phase 6 | Pending |
| INTEGRATION-02 | Phase 6 | Pending |
| INTEGRATION-03 | Phase 6 | Pending |
| INTEGRATION-04 | Phase 6 | Pending |
| INTEGRATION-05 | Phase 6 | Pending |

**Coverage:**
- v1 requirements: 49
- Mapped to phases: 49
- Unmapped: 0

---
*Requirements defined: 2026-01-31*
*Last updated: 2026-01-31 after roadmap creation*
