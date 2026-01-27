# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-01-28)

**Core value:** 讓任何 WordPress 網站都能輕鬆整合 LINE 功能，無需重複開發 LINE API 通訊邏輯，同時解決 LINE 內建瀏覽器的登入問題。

**Current focus:** Phase 1 - 基礎設施與設定

## Current Position

Phase: 1 of 7 (基礎設施與設定)
Plan: 2 of TBD in current phase
Status: In progress
Last activity: 2026-01-28 — Completed 01-01-PLAN.md (資料庫結構與 LINE 用戶綁定 API)

Progress: [██░░░░░░░░] ~28% (estimated based on Phase 1 plans)

## Performance Metrics

**Velocity:**
- Total plans completed: 2
- Average duration: 3 min
- Total execution time: 0.1 hours

**By Phase:**

| Phase | Plans | Total | Avg/Plan |
|-------|-------|-------|----------|
| 01-基礎設施與設定 | 2 | 6 min | 3 min |

**Recent Trend:**
- Last 5 plans: 01-02 (2min), 01-01 (4min)
- Trend: Building momentum

*Updated after each plan completion*

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.
Recent decisions affecting current work:

- 使用混合儲存（user_meta + bindings 表）：快速查詢與完整歷史兼顧
- 採用 Nextend 的持久化儲存架構：處理 LINE 瀏覽器 Cookie 問題
- Webhook 遷移到 buygo-line-notify：基礎設施應在基礎層，業務邏輯透過 Hooks
- 強制引導加入 LINE 官方帳號：確保可以發送 Push Message 通知
- 使用 LIFF 解決 LINE 瀏覽器登入：避免 OAuth redirect 被阻擋
- **01-01:** UNIQUE KEY 限制 user_id 和 line_uid（確保一對一綁定關係）
- **01-01:** 軟刪除而非硬刪除（保留歷史記錄，便於追蹤和除錯）
- **01-01:** 雙寫策略 - custom table（主要）+ user_meta（快取友好）
- **01-02:** 使用 AES-128-ECB 而非 AES-256-GCM（與舊外掛相同，確保向後相容）
- **01-02:** 解密失敗時返回原值而非拋出錯誤（避免系統中斷）
- **01-02:** 優先讀取 buygo_line_{key}，備用 buygo_core_settings（明確讀取順序）

### Pending Todos

None yet.

### Blockers/Concerns

None yet.

## Session Continuity

Last session: 2026-01-28
Stopped at: Completed 01-01-PLAN.md (Database & LineUserService)
Resume file: None
