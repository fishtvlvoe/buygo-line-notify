# buygo-line-notify 後台管理介面改造

## What This Is

這是一個 WordPress 外掛的後台管理介面重構專案。將 buygo-line-notify 的後台從單一設定頁面改造為具有清晰 Tab 導航的專業管理介面，讓所有 LINE 整合設定都能透過直覺的後台調整，無需修改程式碼。改造後的介面將借鑑 Nextend Social Login 的優點，但保持獨特的設計風格。

## Core Value

所有 LINE 整合功能（Messaging API、Login、Profile Sync、外掛整合）的設定都能在後台介面輕鬆找到並調整，實現零程式碼配置。

## Requirements

### Validated

（從現有 codebase 推論的已存在功能）

- ✓ LINE Messaging API 整合 - 發送訊息功能 (existing)
- ✓ LINE Login OAuth 整合 - 用戶登入認證 (existing)
- ✓ Profile Sync 功能 - 同步 LINE 用戶資料 (existing)
- ✓ FluentCart 整合 - 客戶檔案顯示 LINE 綁定狀態 (existing)
- ✓ WordPress 用戶綁定 - LINE 帳號與 WP 用戶關聯 (existing)
- ✓ Webhook 處理 - 接收 LINE 平台事件 (existing)
- ✓ REST API 端點 - 提供前後端通訊介面 (existing)
- ✓ Shortcode 支援 - `[buygo_line_login]`、`[buygo_line_binding]` (existing)

### Active

**後端架構改造：**
- [ ] 將硬編碼設定移至資料庫 options
- [ ] Email 擷取設定（是否擷取、必填/選填、來源）
- [ ] 按鈕位置控制設定（位置、文字、樣式）
- [ ] 外掛整合開關（FluentCart、其他登入外掛）
- [ ] LIFF 功能設定結構（未來使用）
- [ ] 設定 API 端點（儲存/讀取所有設定）

**後台介面建立：**
- [ ] 頂層選單「LINE 設定」（取代「LINE 通知」）
- [ ] Tab 導航系統（6 個 Tab）
- [ ] 「入門」Tab - 快速設定指南和教學
- [ ] 「設定」Tab - 核心 API 設定（Messaging、Login、Email、整合）
- [ ] 「按鈕」Tab - 前台按鈕外觀控制
- [ ] 「同步數據」Tab - Profile Sync 設定
- [ ] 「用法」Tab - Shortcode 和 API 文檔
- [ ] 「LIFF」Tab - LIFF 功能設定（預留）
- [ ] Nextend 風格視覺設計（藍色標題列、統一樣式）
- [ ] 設定分類組織（清晰的欄位分組）

**UI/UX 設計：**
- [ ] Pencil 設計稿完成
- [ ] 依照設計稿實作 HTML/CSS
- [ ] 響應式佈局支援

### Out of Scope

- 前台使用者介面改造 - 不改動登入/註冊表單外觀
- LIFF 功能實作 - 僅建立設定介面，功能未來開發
- 多語系支援 - 目前僅繁體中文
- 行動裝置專用介面 - 使用響應式設計即可

## Context

### 技術環境
- WordPress 5.8+ 外掛
- PHP 8.0+
- 現有架構：Service 層模式、REST API、Hook 系統
- 參考對象：Nextend Social Login Pro（僅參考設計模式，不抄襲）

### 現有問題
1. 許多設定硬編碼在後端 Hook 中，無法在後台調整
2. 缺乏直覺的設定分類和導航
3. 新功能沒有對應的後台選項（如 Email 擷取、外掛整合開關）
4. 團隊協作困難，新開發者難以理解系統運作

### 使用者
- 主要：專案開發者（需要快速配置和測試）
- 次要：未來客戶（需要自行配置 LINE 整合）
- 潛在：開發團隊成員（需要維護和擴展）

## Constraints

- **設計獨立性**：可參考 Nextend Social Login 的優點，但不能照搬整個產品設計
- **功能完整性**：所有現有功能必須保留在新介面中
- **開發順序**：先完成後端設定架構，再建立前端介面（UI 依照 Pencil 設計稿）
- **WordPress 相容性**：必須符合 WordPress 外掛開發規範
- **現有程式碼**：需與現有 Service 層、API 層整合，不破壞既有功能

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| 採用 Tab 導航結構（6 個 Tab） | 清晰分類，借鑑 NSL 優點但保持獨立設計 | — Pending |
| 先建立設計稿再開發 UI | 確保最終介面符合預期，避免重工 | — Pending |
| 所有設定暴露到後台 | 實現零程式碼配置，提升可用性 | — Pending |
| 保留 LIFF Tab 但功能未實作 | 預留未來擴展空間 | — Pending |
| UI 設計委託 Pencil | 專業設計確保視覺品質，開發依照設計稿實作 | — Pending |

---
*Last updated: 2026-01-31 after initialization*
