# v0.2 Milestone Goals

## 核心價值

採用 **Nextend Social Login 標準架構**，建立一個符合 WordPress 生態系統標準的 LINE Login 系統，確保長期可維護性和擴展性。

## 為什麼要完全重構？

1. **架構純粹性**：Nextend 架構是經過實戰驗證的標準，避免混合架構帶來的維護負擔
2. **長期可維護**：標準 WordPress URL 機制比 REST API 更穩定
3. **功能完整**：wp_buygo_line_users 專用表 + 完整的 profile sync + avatar 整合
4. **未來重要性**：「我們未來都要用到這個外掛，只要是需要用到 line 登入抓用戶資料的」

## 目標功能（基於 Nextend 分析）

### 1. 專用資料表架構
- **wp_buygo_line_users 表**
  - 欄位：ID, type (line), identifier (LINE UID), user_id (WP user), register_date, link_date
  - 取代混合儲存（user_meta + bindings），採用單一真實來源
  - 支援查詢優化和完整的歷史追蹤

### 2. 標準 WordPress URL 機制
- **取代 REST API**：`wp-login.php?loginSocial=buygo-line`
- **標準 Callback**：`wp-login.php?loginSocial=buygo-line` (相同 URL)
- **符合 WordPress 生態**：與其他登入方式一致的 URL 結構
- **SEO 友好**：標準 URL，無 CORS 問題

### 3. NSLContinuePageRenderException 模式
- **核心創新**：OAuth callback 後拋出特殊例外（不是錯誤）
- **目的**：讓 WordPress 繼續正常頁面渲染流程
- **效果**：可在任何頁面透過 shortcode 顯示註冊表單
- **優勢**：完美處理 LINE 瀏覽器 Cookie 問題

### 4. Register Flow Page + Shortcode
- **Admin 設定**：後台設定「註冊流程頁面」（可選任意頁面）
- **Shortcode**：`[buygo_line_register_flow]`
- **動態註冊**：OAuth callback 時動態註冊 shortcode
- **靈活整合**：可放在任何主題/任何頁面

### 5. 完整註冊流程
- **新用戶註冊**：
  1. LINE OAuth → 取得 profile (name, email, picture)
  2. 檢查 email 是否已存在於 WordPress
  3. 若不存在：建立新 WP 用戶 → 寫入 wp_buygo_line_users
  4. 若存在：auto-link（自動關聯到現有帳號）
- **已登入用戶綁定**：
  1. 已登入狀態點擊「綁定 LINE」
  2. LINE OAuth → 取得 LINE UID
  3. 檢查 LINE UID 是否已綁定其他帳號
  4. 若未綁定：寫入 wp_buygo_line_users，link_date = now

### 6. Profile Sync 系統
- **同步時機**：register / login / link 三個事件
- **同步欄位**：
  - display_name（LINE displayName）
  - user_email（LINE email，若有）
  - user_url（可選）
  - 頭像（透過 LINE pictureUrl）
- **衝突處理**：Admin 設定優先權（LINE 優先 / WordPress 優先）

### 7. Avatar 整合
- **Hook**：`get_avatar_url` filter
- **邏輯**：
  1. 檢查該 user_id 是否有綁定 LINE（查 wp_buygo_line_users）
  2. 若有：返回 LINE pictureUrl
  3. 若無：返回 WordPress 預設頭像
- **快取**：頭像 URL 快取在 user_meta（避免每次查表）

### 8. 持久化儲存系統（保留現有實作）
- **三層 Fallback**：Session → Transient → Option
- **處理 LINE 瀏覽器問題**：Session 被清除時自動切換到 Transient
- **State 驗證**：32 字元隨機 + hash_equals 防時序攻擊
- **有效期**：10 分鐘（平衡安全性與用戶體驗）

### 9. 前台整合
- **登入/註冊頁面**：「LINE 登入」按鈕
- **我的帳號頁面**：「綁定 LINE」按鈕（僅未綁定時顯示）
- **Shortcode**：`[buygo_line_login]` 可放任意位置
- **樣式自訂**：顏色、大小、文字可透過 shortcode 屬性

### 10. 後台管理
- **設定頁面**：
  - LINE Login Channel ID/Secret
  - 註冊流程頁面選擇器
  - Profile Sync 設定
  - Webhook URL 顯示（唯讀）
- **用戶列表**：顯示哪些用戶已綁定 LINE
- **除錯工具**：State 驗證日誌、OAuth 流程追蹤

## 不在此 Milestone 的功能

### LIFF 整合（延後到 v0.3）
- **原因**：Nextend 架構已完美處理 LINE 瀏覽器問題（NSLContinuePageRenderException + 持久化儲存）
- **評估**：先驗證標準流程是否足夠，若仍有問題再加 LIFF
- **優先級**：低（標準流程可能已足夠）

### Rich Menu / LINE Pay / Flex Message
- **原因**：屬於進階功能，不在 LINE Login 核心範圍
- **規劃**：未來 v2.x Milestone

## 成功標準

1. **架構純粹**：完全採用 Nextend 模式，無混合架構
2. **功能完整**：所有 10 項核心功能實作完成
3. **向後相容**：現有 Phase 1 (基礎設施) 和 Phase 14 (Webhook) 繼續運作
4. **測試通過**：LINE Login 完整流程（註冊/登入/綁定）在 LINE 瀏覽器和一般瀏覽器都正常
5. **文件齊全**：使用文件、API 文件、架構文件

## Milestone 版本號

**建議：v0.2**
- v0.1：Phase 1 + Phase 14 完成（基礎設施 + Webhook）
- v0.2：LINE Login 完整重構（本 Milestone）
- v0.3：LIFF 整合（若需要）
- v1.0：穩定版發布

---
*Created: 2026-01-29*
*Based on: NEXTEND-SOCIAL-LOGIN-ANALYSIS.md*
