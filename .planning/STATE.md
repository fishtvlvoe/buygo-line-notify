# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-01-28)

**Core value:** 讓任何 WordPress 網站都能輕鬆整合 LINE 功能，無需重複開發 LINE API 通訊邏輯，同時解決 LINE 內建瀏覽器的登入問題。

**Current focus:** Phase 14 - buygo-line-notify Webhook 系統

## Current Position

Phase: 14 of 23 (buygo-line-notify Webhook 系統)
Plan: 01/TBD 完成（14-01 完成）
Status: In progress
Last activity: 2026-01-28 — Completed 14-01-PLAN.md (Webhook API Endpoint 和簽名驗證)

Progress: [████████████░░░░░░░░] 57% overall (13/23 phases from v1.0-v2.0, Phase 14 in progress)

## Performance Metrics

**Velocity:**
- Total plans completed: 1 (Phase 14)
- Average duration: 2 min
- Total execution time: 2 min

**By Milestone:**

| Milestone | Phases | Plans | Requirements | Completion |
|-----------|--------|-------|--------------|------------|
| v1.0 測試驗證 | 6/6 | 14/14 | 36/36 | 2026-01-28 |
| v2.0 全頁面遷移 | 7/7 | 21/21 | 26/26 | 2026-01-28 |
| v3.0 新功能整合 | 0/10 | 1/TBD | 2/75 | In progress |

**Phase 14 Summary:**
- Webhook API endpoint: ✅ Created (/wp-json/buygo-line-notify/v1/webhook)
- Signature verification: ✅ Implemented (HMAC-SHA256)
- Verify Event handling: ✅ Working (replyToken: 32 個 0)
- Development mode: ✅ Supports testing without Channel Secret

**Recent Activity:**
- 14-01 completed (2 tasks, 2 commits)
- WebhookVerifier: hash_hmac + hash_equals
- Webhook_API: register_rest_route + Verify Event detection

*Updated: 2026-01-28 after Phase 14 Plan 01 completion*

## Accumulated Context

### Decisions

Decisions are logged in PROJECT.md Key Decisions table.
Recent decisions affecting current work:

- 使用混合儲存（user_meta + bindings 表）：快速查詢與完整歷史兼顧
- 採用 Nextend 的持久化儲存架構：處理 LINE 瀏覽器 Cookie 問題
- Webhook 遷移到 buygo-line-notify：基礎設施應在基礎層，業務邏輯透過 Hooks
- **01-01:** UNIQUE KEY 限制 user_id 和 line_uid（確保一對一綁定關係）
- **01-01:** 軟刪除而非硬刪除（保留歷史記錄，便於追蹤和除錯）
- **01-01:** 雙寫策略 - custom table（主要）+ user_meta（快取友好）
- **01-02:** 使用 AES-128-ECB 而非 AES-256-GCM（與舊外掛相同，確保向後相容）
- **01-02:** 解密失敗時返回原值而非拋出錯誤（避免系統中斷）
- **01-02:** 優先讀取 buygo_line_{key}，備用 buygo_core_settings（明確讀取順序）
- **01-03:** 使用 class_exists('BuyGoPlus\Plugin') 偵測父外掛（避免載入 plugin.php）
- **01-03:** 在 admin_menu hook 執行時檢查（確保所有外掛已載入）
- **01-04:** admin_menu hook 優先級 30（在 buygo-plus-one 優先級 20 之後）
- **01-04:** plugins_loaded hook 初始化（確保所有外掛類別已載入）
- **14-01:** permission_callback 使用 __return_true（公開 endpoint），簽名驗證在 callback 中處理（避免 403，LINE 需要 401）
- **14-01:** 開發環境允許跳過簽名驗證（WP_DEBUG 或 local 環境），便於測試
- **14-01:** Verify Event 立即返回 200，不觸發業務邏輯（replyToken: 32 個 0）
- **14-01:** 正常事件處理延遲到 Plan 02（目前只記錄日誌）

### Pending Todos

None.

### Blockers/Concerns

None.

## Session Continuity

Last session: 2026-01-28 17:12
Stopped at: Completed 14-01-PLAN.md (Webhook API Endpoint 和簽名驗證)
Resume file: None
Resume: Ready for Phase 14 Plan 02 planning
