# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-01-29)

**Core value:** 讓任何 WordPress 網站都能輕鬆整合 LINE 功能,無需重複開發 LINE API 通訊邏輯,同時解決 LINE 內建瀏覽器的登入問題。

**Current focus:** v0.2 Milestone - LINE Login 完整重構（Nextend 架構）

## Current Position

Milestone: v0.2 (LINE Login 完整重構)
Phase: 9 of 15 (標準 WordPress URL 機制)
Plan: 1 of 1
Status: Phase complete
Last activity: 2026-01-29 — Completed Plan 09-01: Login Handler 基礎架構

Progress: [██████████░░░░░░░░░░] 50% overall (2/7 v0.1 phases completed, 2/8 v0.2 phases started)

## Performance Metrics

**Velocity:**
- Total plans completed: 9 (Phase 1: 4 plans, Phase 2: 2 plans, Phase 8: 2 plans, Phase 9: 1 plan)
- Average duration: ~3 min per plan
- Total execution time: ~33 min (v0.1 + v0.2)

**By Milestone:**

| Milestone | Phases | Plans | Requirements | Completion |
|-----------|--------|-------|--------------|------------|
| v0.1 基礎架構 | 2/7 | 6/TBD | 24/~40 | Partial (Phase 1-2 完成) |
| v0.2 LINE Login 重構 | 2/8 | 3/TBD | 8/49 | Phase 8-9 complete |
| v0.3 進階功能 | 0/TBD | 0/TBD | 0/TBD | Not planned |

**v0.1 Milestone Summary (Partial Complete):**
- Phase 1: ✅ 基礎設施與設定（資料庫、後台、設定加密）
- Phase 2: ✅ Webhook 系統（endpoint、簽名驗證、去重、背景處理）
- Phase 3-7: 🚫 Deprecated or ⏸️ Deferred（由 v0.2 重構取代）

**v0.2 Milestone Overview:**
- Phase 8: ✅ 資料表架構與查詢 API（ARCH: 3 需求完成）
- Phase 9: ✅ 標準 WordPress URL 機制（URL + NSL-01: 5 需求完成）
- Phase 10: Register Flow Page 系統（NSL + RFP: 8 需求）
- Phase 11: 完整註冊/登入/綁定流程（FLOW + STORAGE: 6 需求）
- Phase 12: Profile Sync 與 Avatar 整合（SYNC + AVATAR: 10 需求）
- Phase 13: 前台整合（FRONTEND: 5 需求）
- Phase 14: 後台管理（BACKEND: 5 需求）
- Phase 15: 測試與文件（TEST + DOC: 7 需求）

**Total v0.2 Requirements: 49**

**Recent Activity:**
- 2026-01-29: Phase 9 completed（標準 WordPress URL 機制 - 1 plan, 5 requirements）
- 2026-01-29: Phase 8 completed（資料表架構與查詢 API - 2 plans, 3 requirements）
- 2026-01-29: ROADMAP.md created for v0.2 Milestone（8 phases, 49 requirements）
- 2026-01-28: Phase 2 completed（Webhook 系統）
- 2026-01-28: Phase 1 completed（基礎設施與設定）

*Updated: 2026-01-29 after Phase 9 execution*

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

**Phase 9 Implementation Decisions:**
- **NSLContinuePageRenderException 用於流程控制**: 非錯誤例外,讓 WordPress 繼續渲染頁面,攜帶 LINE profile 和 state_data
- **StateManager 整合位置**: authorize 階段由 LoginService 內部處理,callback 階段在 Login_Handler 首先驗證
- **標準 WordPress URL 取代 REST API**: wp-login.php?loginSocial=buygo-line 解決 REST API HTML 輸出問題

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

Last session: 2026-01-29 05:30
Stopped at: Phase 9 execution complete
Resume file: None
Resume: Ready to start Phase 10 (Register Flow Page 系統)

**Next steps:**
1. Run `/gsd:plan-phase 10` to create execution plans for Phase 10
2. Phase 10 will implement Register Flow Page + shortcode
3. Phase 11 will implement complete registration/login/binding flows