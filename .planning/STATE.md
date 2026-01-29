# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-01-29)

**Core value:** 讓任何 WordPress 網站都能輕鬆整合 LINE 功能,無需重複開發 LINE API 通訊邏輯,同時解決 LINE 內建瀏覽器的登入問題。

**Current focus:** v0.2 Milestone - LINE Login 完整重構（Nextend 架構）

## Current Position

Milestone: v0.2 (LINE Login 完整重構)
Phase: 11 of 15 (完整註冊/登入/綁定流程)
Plan: 01 of TBD
Status: In progress (1/TBD plans complete)
Last activity: 2026-01-29 — Completed Phase 11-01: 已登入用戶綁定 LINE 帳號處理

Progress: [████████████░░░░░░░░] 61% overall (2/7 v0.1 phases completed, 4/8 v0.2 phases complete, Phase 11 in progress)

## Performance Metrics

**Velocity:**
- Total plans completed: 17 (Phase 1: 4 plans, Phase 2: 2 plans, Phase 8: 2 plans, Phase 9: 3 plans, Phase 10: 3 plans, Phase 11: 1 plan, Phase 14: 2 plans)
- Average duration: ~2.9 min per plan
- Total execution time: ~52 min (v0.1 + v0.2)

**By Milestone:**

| Milestone | Phases | Plans | Requirements | Completion |
|-----------|--------|-------|--------------|------------|
| v0.1 基礎架構 | 2/7 | 6/TBD | 24/~40 | Partial (Phase 1-2 完成) |
| v0.2 LINE Login 重構 | 4/8 | 8/TBD | 14/49 | Phase 8-10 complete, Phase 11 in progress |
| v0.3 進階功能 | 0/TBD | 0/TBD | 0/TBD | Not planned |

**v0.1 Milestone Summary (Partial Complete):**
- Phase 1: ✅ 基礎設施與設定（資料庫、後台、設定加密）
- Phase 2: ✅ Webhook 系統（endpoint、簽名驗證、去重、背景處理）
- Phase 3-7: 🚫 Deprecated or ⏸️ Deferred（由 v0.2 重構取代）

**v0.2 Milestone Overview:**
- Phase 8: ✅ 資料表架構與查詢 API（ARCH: 3 需求完成）
- Phase 9: ✅ 標準 WordPress URL 機制（URL + NSL-01: 5 需求完成）
- Phase 10: ✅ Register Flow Page 系統（NSL + RFP: 9 需求完成 - 3/3 plans complete）
- Phase 11: 🔄 完整註冊/登入/綁定流程（FLOW + STORAGE: 3/6 需求完成 - 1/TBD plans complete）
- Phase 12: Profile Sync 與 Avatar 整合（SYNC + AVATAR: 10 需求）
- Phase 13: 前台整合（FRONTEND: 5 需求）
- Phase 14: 後台管理（BACKEND: 5 需求）
- Phase 15: 測試與文件（TEST + DOC: 7 需求）

**Total v0.2 Requirements: 49**

**Recent Activity:**
- 2026-01-29: Phase 11-01 completed（已登入用戶綁定 LINE 帳號處理 - FLOW-03: handle_link_submission + FLOW_LINK）
- 2026-01-29: Phase 10 completed（Register Flow Page 系統 - 3 plans, 9 requirements, checkpoint 驗證全部通過）
- 2026-01-29: Phase 10 Plan 03 checkpoint verification（4 test cases: Register Flow Page, Fallback mode, Auto-link, Settings page）
- 2026-01-29: Phase 10 Plan 02 completed（表單提交處理 + Auto-link 機制）
- 2026-01-28: Phase 10 Plan 01 completed（Register Flow Shortcode 核心機制）
- 2026-01-29: Phase 9 completed（標準 WordPress URL 機制 - 3 plans, 5 requirements）
- 2026-01-29: Phase 8 completed（資料表架構與查詢 API - 2 plans, 3 requirements）

*Updated: 2026-01-29 after Phase 11-01 completion*

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.
Recent decisions affecting current work:

**v0.2 Architecture Decisions:**
- **完全採用 Nextend 架構**: 架構純粹性、長期可維護、未來重要性高
- **wp_buygo_line_users 專用表**: 單一真實來源,取代混合儲存
- **標準 WordPress URL 機制**: 比 REST API 更穩定、符合 WordPress 生態
- **NSLContinuePageRenderException**: 完美處理 LINE 瀏覽器問題
- **Register Flow Page + Shortcode**: 靈活整合、可放任何頁面
- **LIFF 延後到 v0.3**: Nextend 架構已足夠,先驗證再決定

**Phase 11 Implementation Decisions:**
- **redirect_with_error() 取代 wp_die()**: 綁定錯誤使用 Transient + redirect 提供使用者友善訊息
- **Link flow detection before login logic**: handle_callback() 中綁定流程判斷在登入判斷之前執行
- **智能 Transient 清除策略（綁定）**: Nonce 失敗不清除（防 CSRF），身份問題清除（不可恢復）
- **FLOW_LINK 例外處理**: 儲存 profile 到 Transient，攜帶 user_id，拋出例外讓頁面繼續渲染
- **buygo_line_after_link hook**: 綁定成功後觸發，供其他外掛整合

**Phase 10 Implementation Decisions:**
- **Transient API 儲存 LINE profile**: 10 分鐘 TTL，key pattern: buygo_line_profile_{state}
- **動態 shortcode 註冊**: OAuth callback 偵測到新用戶時才註冊，避免靜態全域註冊
- **Shortcode 雙參數模式**: 接受 exception_data（動態註冊）或 URL state（頁面重定向）
- **完整例外流程處理**: switch 語句覆蓋 FLOW_REGISTER 和 FLOW_LINK
- **Fallback 表單機制**: 當未設定 Register Flow Page 時在 wp-login.php 顯示
- **Auto-link on Email match**: Email 已存在時自動綁定現有帳號而非建立新用戶
- **智能 Transient 清除策略**: 根據錯誤類型決定是否清除（安全問題不清除，用戶輸入錯誤允許重試，不可恢復錯誤清除）
- **Username collision handling**: 用戶名衝突時自動加數字後綴
- **linkUser is_registration parameter**: 註冊時設定 register_date，auto-link 時只設定 link_date

**Phase 9 Implementation Decisions:**
- **NSLContinuePageRenderException 用於流程控制**: 非錯誤例外,讓 WordPress 繼續渲染頁面,攜帶 LINE profile 和 state_data
- **StateManager 整合位置**: authorize 階段由 LoginService 內部處理,callback 階段在 Login_Handler 首先驗證
- **標準 WordPress URL 取代 REST API**: wp-login.php?loginSocial=buygo-line 解決 REST API HTML 輸出問題
- **login_url filter 預設關閉**: 避免影響標準 WordPress 登入行為,可透過設定啟用
- **wp_logout 清除 session 資料**: 登出時清除 LINE profile 和 state,但不清除 Transient（StateManager 負責）
- **Plugin 整合 Login_Handler**: loadDependencies 載入例外和 handler,onInit 註冊 hooks
- **REST API 完整 deprecation 策略**: 5 處 @deprecated 標記 + runtime headers + logging,保持向後相容
- **Exception 載入順序**: 先載入 NSLContinuePageRenderException,再載入依賴它的 Login_Handler

**Phase 8 Implementation Decisions:**
- **對齊 Nextend wp_social_users 結構**: 完全採用 Nextend 欄位命名，確保架構純粹性
- **舊表保留不刪除**: 遷移後保留 wp_buygo_line_bindings，避免資料遺失
- **遷移狀態記錄到 wp_options**: buygo_line_migration_status 記錄遷移詳情
- **統一版本追蹤為 buygo_line_db_version**: 簡化命名，與外掛名稱一致
- **unlinkUser 使用硬刪除**: DELETE 而非軟刪除，對齊 Nextend 架構
- **linkUser 拒絕重複綁定**: 確保一對一關係（LINE UID ↔ WordPress User）

**v0.1 Implementation Decisions:**
- 使用混合儲存（user_meta + bindings 表）：快速查詢與完整歷史兼顧 — ✅ v0.2 已取代為專用表
- 採用 Nextend 的持久化儲存架構：處理 LINE 瀏覽器 Cookie 問題 — 保留使用
- Webhook 遷移到 buygo-line-notify：基礎設施應在基礎層 — 已完成
- **01-01:** UNIQUE KEY 限制 user_id 和 line_uid（確保一對一綁定關係）
- **01-02:** 使用 AES-128-ECB（與舊外掛相同,確保向後相容）
- **01-03:** 使用 class_exists('BuyGoPlus\Plugin') 偵測父外掛

### v0.2 Architecture Reference

**核心文件:** `.planning/NEXTEND-SOCIAL-LOGIN-ANALYSIS.md`

**核心機制:**
1. **NSLContinuePageRenderException 模式**: OAuth callback 拋出特殊例外,讓 WordPress 繼續渲染
2. **Register Flow Page + Shortcode**: 動態註冊 shortcode,在任何頁面顯示註冊表單
3. **wp_social_users 專用表**: 單一真實來源（對應我們的 wp_buygo_line_users） — ✅ Phase 8 完成
4. **標準 WordPress URL**: `wp-login.php?loginSocial=buygo-line`（取代 REST API）
5. **完整 Profile Sync**: 註冊/登入/綁定時同步 name、email、avatar
6. **Avatar 整合**: `get_avatar_url` filter hook

**參考來源:**
- Nextend Social Login Pro（NSL 架構）
- WooCommerce Notify（三層儲存 fallback — 已保留）

### Pending Todos

None.

### Blockers/Concerns

None.

## Session Continuity

Last session: 2026-01-29 08:32
Stopped at: Phase 11-01 complete (handle_link_submission + FLOW_LINK detection)
Resume file: None
Resume: Continue Phase 11

**Next steps:**
1. Phase 11-02: 實作 Login flow（已登入用戶發起登入的處理）
2. Phase 11-03: 實作 Link Flow Shortcode（綁定確認頁面）
3. Phase 11 將完成所有用戶認證流程（register, login, link, auto-link）