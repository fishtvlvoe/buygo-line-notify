---
phase: 14-buygo-line-notify-webhook-系統
plan: 01
subsystem: api
tags: [line, webhook, rest-api, hmac-sha256, security]

# Dependency graph
requires:
  - phase: 01-基礎設施與設定
    provides: SettingsService（讀取 Channel Secret）、資料庫結構
provides:
  - Webhook REST API endpoint (/wp-json/buygo-line-notify/v1/webhook)
  - HMAC-SHA256 簽名驗證系統
  - Verify Event 處理邏輯
affects: [14-02, 15-LINE Login 系統, buygo-plus-one 商品上架]

# Tech tracking
tech-stack:
  added: []
  patterns: [REST API 註冊模式, 簽名驗證安全模式, 開發/正式環境分離]

key-files:
  created:
    - buygo-line-notify/includes/services/class-webhook-verifier.php
    - buygo-line-notify/includes/api/class-webhook-api.php
  modified: []

key-decisions:
  - "permission_callback 使用 __return_true（公開 endpoint），簽名驗證在 callback 中處理（避免 403，LINE 需要 401）"
  - "開發環境允許跳過簽名驗證（WP_DEBUG 或 local 環境），便於測試"
  - "Verify Event 立即返回 200，不觸發業務邏輯"
  - "正常事件處理延遲到 Plan 02（目前只記錄日誌）"

patterns-established:
  - "簽名驗證模式: hash_hmac + hash_equals（防止時序攻擊）"
  - "多重 header 檢測: 支援多種大小寫變體（x-line-signature, X-LINE-Signature 等）"
  - "環境檢測: WP_DEBUG + wp_get_environment_type + 伺服器名稱三重判斷"

# Metrics
duration: 2min
completed: 2026-01-28
---

# Phase 14 Plan 01: LINE Webhook API Endpoint 和簽名驗證系統 Summary

**REST API endpoint 接收 LINE Webhook 並驗證 HMAC-SHA256 簽名，Verify Event 自動處理返回 200**

## Performance

- **Duration:** 2 min
- **Started:** 2026-01-28T17:10:52Z
- **Completed:** 2026-01-28T17:12:29Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- 建立 WebhookVerifier 類別，使用 hash_hmac + hash_equals 實作安全的簽名驗證
- 建立 Webhook_API 類別，註冊 REST endpoint /wp-json/buygo-line-notify/v1/webhook
- Verify Event（replyToken: 32 個 0）自動檢測並立即返回 200
- 開發環境允許跳過驗證，便於測試（WP_DEBUG 或 local 環境）

## Task Commits

Each task was committed atomically:

1. **Task 1: 建立 Webhook 簽名驗證器** - `3a40672` (feat)
2. **Task 2: 建立 Webhook API Endpoint** - `cc34d82` (feat)

## Files Created/Modified
- `buygo-line-notify/includes/services/class-webhook-verifier.php` - 驗證 LINE Webhook HMAC-SHA256 簽名，支援多種 header 大小寫，開發環境可跳過驗證
- `buygo-line-notify/includes/api/class-webhook-api.php` - 註冊 REST endpoint，處理 Verify Event，驗證簽名失敗返回 401

## Decisions Made

### 1. permission_callback 使用 __return_true
**Rationale:** 簽名驗證必須在 callback 中處理，因為：
- WordPress REST API 的 permission_callback 失敗會返回 403
- LINE Platform 要求簽名驗證失敗返回 401
- 必須在 callback 中手動驗證並返回正確的狀態碼

### 2. 開發環境允許跳過簽名驗證
**Rationale:**
- 本地測試時可能沒有設定 Channel Secret
- 使用 WP_DEBUG、wp_get_environment_type、伺服器名稱三重判斷
- 正式環境（預設）拒絕無簽名或無 Channel Secret 的請求

### 3. Verify Event 立即返回
**Rationale:**
- LINE Developers Console 點擊「驗證」會發送特殊事件（replyToken: 32 個 0）
- 這不是真實事件，不應觸發業務邏輯
- 立即返回 200 讓 LINE 知道 endpoint 可用

### 4. 正常事件處理延遲到 Plan 02
**Rationale:**
- Plan 01 專注於基礎設施（endpoint + 簽名驗證）
- Plan 02 實作事件去重、背景處理、Hooks 系統
- 目前只記錄日誌，確保 endpoint 可以接收事件

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

（LINE Webhook URL 設定會在 Plan 02 透過 checkpoint 引導使用者設定）

## Next Phase Readiness

**Ready for Plan 02:**
- Webhook endpoint 已建立並可接收請求
- 簽名驗證機制完整且安全
- Verify Event 處理正確

**Next steps (Plan 02):**
- 實作事件去重機制（webhookEventId + transient）
- 實作背景處理支援（FastCGI / WordPress Cron）
- 提供 Hooks 讓其他外掛註冊事件處理器
- 整合到外掛主流程（Plugin::init）
- 在 LINE Developers Console 設定 Webhook URL（checkpoint）

**No blockers or concerns.**

---
*Phase: 14-buygo-line-notify-webhook-系統*
*Plan: 01*
*Completed: 2026-01-28*
