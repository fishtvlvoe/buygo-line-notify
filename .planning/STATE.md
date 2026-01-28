# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-01-29)

**Core value:** 讓任何 WordPress 網站都能輕鬆整合 LINE 功能,無需重複開發 LINE API 通訊邏輯,同時解決 LINE 內建瀏覽器的登入問題。

**Current focus:** v0.2 Milestone - LINE Login 完整重構（Nextend 架構）

## Current Position

Milestone: v0.2 (LINE Login 完整重構)
Phase: 8 of 15 (資料表架構與查詢 API)
Plan: Not started
Status: Ready to start Phase 8
Last activity: 2026-01-29 — ROADMAP.md created for v0.2 Milestone

Progress: [████████░░░░░░░░░░░░] 40% overall (2/7 v0.1 phases completed, 0/8 v0.2 phases started)

## Performance Metrics

**Velocity:**
- Total plans completed: 6 (Phase 1: 4 plans, Phase 2: 2 plans)
- Average duration: ~3 min per plan
- Total execution time: ~18 min (v0.1)

**By Milestone:**

| Milestone | Phases | Plans | Requirements | Completion |
|-----------|--------|-------|--------------|------------|
| v0.1 基礎架構 | 2/7 | 6/TBD | 24/~40 | Partial (Phase 1-2 完成) |
| v0.2 LINE Login 重構 | 0/8 | 0/TBD | 0/49 | Not started |
| v0.3 進階功能 | 0/TBD | 0/TBD | 0/TBD | Not planned |

**v0.1 Milestone Summary (Partial Complete):**
- Phase 1: ✅ 基礎設施與設定（資料庫、後台、設定加密）
- Phase 2: ✅ Webhook 系統（endpoint、簽名驗證、去重、背景處理）
- Phase 3-7: 🚫 Deprecated or ⏸️ Deferred（由 v0.2 重構取代）

**v0.2 Milestone Overview (Not started):**
- Phase 8: 資料表架構與查詢 API（ARCH: 3 需求）
- Phase 9: 標準 WordPress URL 機制（URL + NSL-01: 5 需求）
- Phase 10: Register Flow Page 系統（NSL + RFP: 8 需求）
- Phase 11: 完整註冊/登入/綁定流程（FLOW + STORAGE: 6 需求）
- Phase 12: Profile Sync 與 Avatar 整合（SYNC + AVATAR: 10 需求）
- Phase 13: 前台整合（FRONTEND: 5 需求）
- Phase 14: 後台管理（BACKEND: 5 需求）
- Phase 15: 測試與文件（TEST + DOC: 7 需求）

**Total v0.2 Requirements: 49**

**Recent Activity:**
- 2026-01-29: ROADMAP.md created for v0.2 Milestone（8 phases, 49 requirements）
- 2026-01-28: Phase 2 completed（Webhook 系統）
- 2026-01-28: Phase 1 completed（基礎設施與設定）

*Updated: 2026-01-29 after v0.2 ROADMAP.md creation*

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

**v0.1 Implementation Decisions:**
- 使用混合儲存（user_meta + bindings 表）：快速查詢與完整歷史兼顧 — v0.2 將取代為專用表
- 採用 Nextend 的持久化儲存架構：處理 LINE 瀏覽器 Cookie 問題 — 保留使用
- Webhook 遷移到 buygo-line-notify：基礎設施應在基礎層 — 已完成
- **01-01:** UNIQUE KEY 限制 user_id 和 line_uid（確保一對一綁定關係）
- **01-02:** 使用 AES-128-ECB（與舊外掛相同,確保向後相容）
- **01-03:** 使用 class_exists('BuyGoPlus\Plugin') 偵測父外掛
- **14-01:** permission_callback 使用 __return_true（公開 endpoint）
- **14-02:** 使用 webhookEventId + Transients API 實作去重
- **14-03:** FastCGI 環境使用 fastcgi_finish_request 立即返回 200

### v0.2 Architecture Reference

**核心文件:** `.planning/NEXTEND-SOCIAL-LOGIN-ANALYSIS.md`

**核心機制:**
1. **NSLContinuePageRenderException 模式**: OAuth callback 拋出特殊例外,讓 WordPress 繼續渲染
2. **Register Flow Page + Shortcode**: 動態註冊 shortcode,在任何頁面顯示註冊表單
3. **wp_social_users 專用表**: 單一真實來源（對應我們的 wp_buygo_line_users）
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

Last session: 2026-01-29 12:00
Stopped at: ROADMAP.md created for v0.2 Milestone
Resume file: None
Resume: Ready to start Phase 8 (資料表架構與查詢 API)

**Next steps:**
1. Run `/gsd:plan-phase 8` to create execution plans
2. Phase 8 will establish wp_buygo_line_users table and migration
3. Phase 9 will implement standard WordPress URL mechanism
