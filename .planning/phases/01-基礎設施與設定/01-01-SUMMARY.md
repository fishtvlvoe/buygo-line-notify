---
phase: 01-基礎設施與設定
plan: 01
subsystem: database
tags: [database, line-binding, mixed-storage, user-meta]
dependency-graph:
  requires: []
  provides:
    - wp_buygo_line_bindings 資料表
    - LineUserService API
    - 混合儲存架構（custom table + user_meta）
  affects:
    - 01-02 (需要 LineUserService 儲存 Channel 設定關聯)
    - 02-01 (LIFF 登入後需要呼叫 bind_line_account)
    - 03-01 (Webhook 需要透過 line_uid 查詢使用者)
tech-stack:
  added:
    - WordPress dbDelta (資料表版本管理)
    - Custom table schema with indexes
  patterns:
    - 混合儲存模式（custom table + user_meta 雙寫）
    - 軟刪除模式（status: active/inactive）
    - 版本控制模式（DB_VERSION + option）
key-files:
  created:
    - includes/class-database.php: 資料表建立與版本管理
    - includes/services/class-line-user-service.php: LINE 綁定 API
  modified:
    - buygo-line-notify.php: 加入 activation hook
    - includes/class-plugin.php: 加入資料庫初始化和 LineUserService 載入
decisions:
  - decision: 使用混合儲存（custom table + user_meta）
    rationale: custom table 提供完整歷史和進階查詢，user_meta 提供 WordPress 快取和向後相容
    alternatives: 僅使用 user_meta 或僅使用 custom table
  - decision: UNIQUE KEY 限制 user_id 和 line_uid
    rationale: 確保一對一綁定關係（一個 WordPress 使用者只能綁定一個 LINE，反之亦然）
  - decision: 軟刪除而非硬刪除
    rationale: 保留歷史記錄，便於追蹤和除錯
metrics:
  duration: 4
  completed: 2026-01-27
---

# Phase 01 Plan 01: 建立資料庫結構與 LINE 用戶綁定 API Summary

建立 LINE 綁定的資料庫基礎設施，包含 wp_buygo_line_bindings 資料表和混合儲存查詢 API

## What Was Built

### 核心交付物

**1. Database 類別 (includes/class-database.php)**
- 使用 dbDelta() 建立 wp_buygo_line_bindings 資料表
- 實作版本控制機制（DB_VERSION + option）
- 支援外掛啟動和更新時的自動初始化
- 包含 drop_tables() 供外掛移除時使用

**2. wp_buygo_line_bindings 資料表**
- 8 個欄位：id, user_id, line_uid, display_name, picture_url, status, created_at, updated_at
- 4 個索引：PRIMARY (id), UNIQUE (user_id), UNIQUE (line_uid), KEY (status)
- 確保一對一綁定關係（一個 WordPress 使用者只能綁定一個 LINE）
- 支援軟刪除（status: active/inactive）

**3. LineUserService API (includes/services/class-line-user-service.php)**
- `bind_line_account()`: 綁定 LINE 帳號（雙寫 custom table + user_meta）
- `get_user_line_id()`: 根據 user_id 取得 LINE UID（優先從 user_meta）
- `get_line_user()`: 根據 line_uid 取得完整綁定資料
- `get_user_binding()`: 根據 user_id 取得完整綁定資料
- `unbind_line_account()`: 解除綁定（軟刪除）
- `is_user_bound()` / `is_line_uid_bound()`: 檢查綁定狀態

### 整合點

**buygo-line-notify.php**
- 註冊 activation hook 呼叫 Database::init()
- 外掛啟動時自動建立/升級資料表

**includes/class-plugin.php**
- Plugin::init() 加入 Database::init()（處理更新情境）
- loadDependencies() 加入 LineUserService 載入

## Technical Decisions

### 混合儲存策略

**決策**: 同時使用 custom table 和 user_meta 儲存綁定資料

**理由**:
- **Custom table**: 提供完整歷史記錄、進階查詢能力、軟刪除支援
- **User meta**: 提供 WordPress 快取機制、向後相容性、快速讀取

**實作細節**:
- 寫入時雙寫（bind_line_account 同時寫入兩處）
- 讀取時優先從 user_meta（有快取）
- 備用從 custom table 查詢（確保資料完整性）

### UNIQUE KEY 設計

**決策**: 在 user_id 和 line_uid 上分別建立 UNIQUE KEY

**理由**:
- 確保一對一綁定關係
- 防止同一個 WordPress 使用者綁定多個 LINE
- 防止同一個 LINE 綁定多個 WordPress 使用者

**影響**:
- 重新綁定時會自動更新現有記錄
- 需要在應用層處理 UNIQUE constraint 錯誤

### 軟刪除設計

**決策**: 使用 status 欄位實作軟刪除（active/inactive）

**理由**:
- 保留綁定歷史記錄
- 便於追蹤和除錯
- 支援未來可能的「重新綁定」功能

**實作**:
- unbind_line_account 將 status 設為 inactive
- 查詢時過濾 status = 'active'
- 同時清除 user_meta（確保 WordPress 使用者介面正確顯示）

## Testing Results

### 資料表驗證

使用 init-db-test.php 驗證資料表建立成功：
- ✓ 8 個欄位全部正確
- ✓ 4 個索引全部建立（PRIMARY, idx_user_id UNIQUE, idx_line_uid UNIQUE, idx_status）
- ✓ 資料庫版本 1.0.0 已記錄

### API 功能測試

使用 test-line-user-service.php 執行完整測試：
- ✓ 綁定功能正常（bind_line_account）
- ✓ 查詢 LINE UID 正常（get_user_line_id）
- ✓ 查詢完整資料正常（get_line_user）
- ✓ 查詢用戶綁定正常（get_user_binding）
- ✓ user_meta 雙寫成功（3 個 meta keys）
- ✓ 檢查函數正常（is_user_bound, is_line_uid_bound）
- ✓ 解除綁定正常（unbind_line_account）
- ✓ 軟刪除驗證成功（status 設為 inactive）

所有 8 項測試通過，無失敗案例。

## Known Limitations

1. **wp-cli 連線問題**: Local by Flywheel 環境的 wp-cli 無法連線到 MySQL，需要透過瀏覽器執行測試腳本
2. **測試腳本暫存**: init-db-test.php 和 test-line-user-service.php 為臨時測試檔案，未納入 git
3. **錯誤處理**: 目前未實作詳細的錯誤訊息和日誌記錄

## Deviations from Plan

無偏差 - 計畫執行完全符合預期。

### 額外功能

在計畫基礎上增加了以下實用功能（Rule 2 - 缺失的關鍵功能）：
- `get_user_binding()`: 根據 user_id 查詢完整綁定資料（原計畫未包含）
- `is_user_bound()` / `is_line_uid_bound()`: 檢查綁定狀態的便捷方法
- `Database::drop_tables()`: 供外掛移除時清理資料表

這些功能對後續開發很有幫助，屬於基礎設施的完善。

## Next Phase Readiness

### 已準備就緒

✅ **資料庫層**: wp_buygo_line_bindings 表已建立且功能完整
✅ **API 層**: LineUserService 提供完整的綁定與查詢 API
✅ **混合儲存**: custom table + user_meta 雙寫機制運作正常
✅ **測試驗證**: 所有核心功能已測試通過

### 後續計畫相依性

**01-02 (Channel 設定管理)** - 可以開始
- 需要 LineUserService 來關聯使用者與 Channel
- 可以參考 bind_line_account 的雙寫模式

**02-01 (LIFF 登入)** - 可以開始
- LIFF 完成後呼叫 `LineUserService::bind_line_account()`
- 已有完整的綁定 API

**03-01 (Webhook)** - 可以開始
- Webhook 接收 line_uid 後呼叫 `LineUserService::get_line_user()`
- 已有完整的查詢 API

### 無阻礙事項

無已知阻礙。資料庫基礎設施已完成，可進行下一階段開發。

## Commits

| Hash    | Message                                            |
|---------|----------------------------------------------------|
| 696587d | feat(01-01): 建立 Database 類別並實作 wp_buygo_line_bindings 資料表 |
| 9d89a22 | feat(01-01): 建立 LineUserService 實作混合儲存 API |

## Performance Notes

- **執行時間**: 4 分鐘
- **任務數**: 2/2 完成
- **測試通過率**: 100% (8/8 測試通過)

資料表建立和 API 實作都很順利，無遇到重大技術障礙。
