# Project State

## Project Reference

See: .planning/PROJECT.md (updated 2026-01-28)

**Core value:** 讓任何 WordPress 網站都能輕鬆整合 LINE 功能，無需重複開發 LINE API 通訊邏輯，同時解決 LINE 內建瀏覽器的登入問題。

**Current focus:** Phase 14 - buygo-line-notify Webhook 系統

## Current Position

Phase: 14 of 23 (buygo-line-notify Webhook 系統)
Plan: 03/03 完成（14-01, 14-02, 14-03 完成）
Status: Phase 14 Wave 2 complete
Last activity: 2026-01-29 — Completed 14-03-PLAN.md (Plugin 整合與背景處理)

Progress: [████████████░░░░░░░░] 57% overall (13/23 phases from v1.0-v2.0, Phase 14 Wave 2 complete)

## Performance Metrics

**Velocity:**
- Total plans completed: 3 (Phase 14 Wave 2)
- Average duration: 3 min
- Total execution time: 9 min

**By Milestone:**

| Milestone | Phases | Plans | Requirements | Completion |
|-----------|--------|-------|--------------|------------|
| v1.0 測試驗證 | 6/6 | 14/14 | 36/36 | 2026-01-28 |
| v2.0 全頁面遷移 | 7/7 | 21/21 | 26/26 | 2026-01-28 |
| v3.0 新功能整合 | 0/10 | 3/TBD | 8/75 | In progress |

**Phase 14 Summary (Wave 2 Complete):**
- Webhook API endpoint: ✅ Created (/wp-json/buygo-line-notify/v1/webhook)
- Signature verification: ✅ Implemented (HMAC-SHA256)
- Verify Event handling: ✅ Working (replyToken: 32 個 0)
- Event deduplication: ✅ Implemented (webhookEventId + 60s transient)
- Background processing: ✅ FastCGI + WP_Cron fallback
- Plugin integration: ✅ Hooks registered (rest_api_init, buygo_process_line_webhook)
- LINE Console verified: ✅ Webhook URL test passed

**Recent Activity:**
- 14-01 completed (2 tasks, 2 commits) - Webhook endpoint + signature verification
- 14-02 completed (1 task, 1 commit) - WebhookHandler + event deduplication
- 14-03 completed (2 tasks, 2 commits) - Plugin integration + background processing

*Updated: 2026-01-29 after Phase 14 Plan 03 completion*

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
- **14-02:** 使用 webhookEventId + Transients API 實作去重（60 秒 TTL）
- **14-02:** 觸發 12 個 WordPress Hooks（通用、事件類型、訊息類型）
- **14-03:** FastCGI 環境使用 fastcgi_finish_request 立即返回 200 後背景處理
- **14-03:** 非 FastCGI 環境使用 wp_schedule_single_event 排程背景處理
- **14-03:** 在 Plugin::onInit 註冊 rest_api_init 和 buygo_process_line_webhook hooks

### Pending Todos

None.

### Blockers/Concerns

None.

## Session Continuity

Last session: 2026-01-29 01:23
Stopped at: Completed 14-03-PLAN.md (Plugin 整合與背景處理)
Resume file: None
Resume: Phase 14 Wave 2 complete. Ready for Wave 3 (LIFF 系統) or Phase 15 (LINE Login 系統)
