# Phase 12-05 驗證報告：衝突策略測試

**日期**: 2026-01-29
**驗證目標**: 驗證 ProfileSyncService 三種衝突處理策略的運作

---

## 測試環境

- **網站**: https://test.buygo.me
- **WordPress Admin**: https://test.buygo.me/wp-admin
- **LINE Developers Console**: 已設定完成
- **測試用 LINE 帳號**: Fish 老魚 (LINE UID: U823e48d899eb99be6fb49d53609048d9)

## 前置作業完成

✅ **LINE Login 流程驗證通過**：
- OAuth 授權流程正常
- Token 交換成功
- Profile 取得成功
- 新用戶註冊成功（用戶：Fish 老魚，Email: fishtest@example.com）
- LINE 頭像同步成功

✅ **孤立綁定記錄問題已修復**：
- Commit: 633eafc
- 批次刪除功能現在會清除孤立的 LINE 綁定記錄

---

## Task 1: line_priority 策略驗證

**策略說明**: LINE profile 優先覆蓋 WordPress 資料

### 測試步驟

1. **準備測試用戶**
   - [ ] 登入 WordPress admin
   - [ ] 找到已綁定 LINE 的測試用戶（Fish 老魚）
   - [ ] 記錄當前用戶資料（display_name, email）

2. **修改 WordPress 資料**
   - [ ] 到 WordPress 後台編輯該用戶
   - [ ] 修改 display_name 為「測試名稱」
   - [ ] 修改 email 為「test@example.com」
   - [ ] 儲存變更

3. **設定衝突策略**
   - [ ] 到 LINE 設定頁面
   - [ ] 確認「登入時更新 Profile」已勾選
   - [ ] 選擇「LINE 優先」策略
   - [ ] 儲存設定

4. **執行 LINE 登入**
   - [ ] 登出當前用戶
   - [ ] 使用 LINE 登入（Fish 老魚帳號）
   - [ ] 登入成功後檢查用戶資料

5. **驗證結果**
   - [ ] display_name 應該被更新為「Fish 老魚」（LINE 的名稱）
   - [ ] email 應該被更新為 LINE profile 的 email
   - [ ] 檢查同步日誌（wp_options: buygo_line_sync_log_<user_id>）

### 測試結果

**狀態**: [ ] 通過 / [ ] 失敗

**實際行為**:
```
[待填寫]
修改前 WordPress 資料：
- display_name:
- email:

修改後 WordPress 資料：
- display_name:
- email:

LINE Login 後資料：
- display_name:
- email:
```

**同步日誌**:
```
[待填寫 - 使用 check-conflict-log.php 查看]
```

**問題（如有）**: [待填寫]

---

## Task 2: wordpress_priority 策略驗證

**策略說明**: 保留 WordPress 現有資料，只在欄位為空時才更新

### 測試步驟

1. **準備測試（使用同一用戶）**
   - [ ] 再次修改 WordPress 用戶資料
   - [ ] display_name: 「WordPress 自訂名稱」
   - [ ] email: 保留原本的 email
   - [ ] 儲存變更

2. **切換衝突策略**
   - [ ] 到 LINE 設定頁面
   - [ ] 選擇「WordPress 優先」策略
   - [ ] 儲存設定

3. **執行 LINE 登入**
   - [ ] 登出
   - [ ] 使用 LINE 登入
   - [ ] 檢查用戶資料

4. **驗證結果**
   - [ ] display_name 應該保持「WordPress 自訂名稱」（不被 LINE 覆蓋）
   - [ ] email 應該保持不變
   - [ ] 檢查同步日誌

5. **驗證空白欄位更新**
   - [ ] 編輯用戶，清空 display_name（設為空字串）
   - [ ] 再次 LINE 登入
   - [ ] display_name 應該被更新為「Fish 老魚」

### 測試結果

**狀態**: [ ] 通過 / [ ] 失敗

**案例 1: 保留現有資料**
```
[待填寫]
修改後 WordPress 資料：
- display_name: WordPress 自訂名稱
- email:

LINE Login 後資料：
- display_name: [應保持 WordPress 自訂名稱]
- email: [應保持不變]
```

**案例 2: 空白欄位更新**
```
[待填寫]
清空後 WordPress 資料：
- display_name: [空]

LINE Login 後資料：
- display_name: [應更新為 Fish 老魚]
```

**同步日誌**:
```
[待填寫]
```

**問題（如有）**: [待填寫]

---

## Task 3: manual 策略驗證

**策略說明**: 不自動更新，記錄衝突讓管理員決定

### 測試步驟

1. **準備測試**
   - [ ] 修改 WordPress 用戶資料
   - [ ] display_name: 「需要審核的名稱」
   - [ ] 儲存變更

2. **切換衝突策略**
   - [ ] 到 LINE 設定頁面
   - [ ] 選擇「手動處理」策略
   - [ ] 儲存設定

3. **執行 LINE 登入**
   - [ ] 登出
   - [ ] 使用 LINE 登入
   - [ ] 檢查用戶資料

4. **驗證結果**
   - [ ] display_name 應該保持「需要審核的名稱」（不更新）
   - [ ] email 應該保持不變
   - [ ] **檢查衝突日誌**（wp_options: buygo_line_conflict_log_<user_id>）
   - [ ] 衝突日誌應該記錄 display_name 的衝突

### 測試結果

**狀態**: [ ] 通過 / [ ] 失敗

**實際行為**:
```
[待填寫]
修改後 WordPress 資料：
- display_name: 需要審核的名稱

LINE Login 後資料：
- display_name: [應保持 需要審核的名稱]
```

**衝突日誌範例**:
```
[待填寫 - 使用 check-conflict-log.php 查看]
期待格式：
[
  {
    "timestamp": "2026-01-29 XX:XX:XX",
    "field": "display_name",
    "current_value": "需要審核的名稱",
    "new_value": "Fish 老魚"
  }
]
```

**同步日誌**:
```
[待填寫 - manual 策略不應產生 sync_log，只有 conflict_log]
```

**問題（如有）**: [待填寫]

---

## 驗證工具

使用以下腳本協助驗證：

```bash
# 查看同步和衝突日誌
php check-conflict-log.php

# 查看當前設定
php check-settings.php

# 查看用戶資料
# 到 WordPress admin -> 用戶 -> 編輯用戶
```

---

## 總結

**完成狀態**: [ ] 全部通過 / [ ] 部分通過 / [ ] 失敗

**通過的策略**:
- [ ] line_priority
- [ ] wordpress_priority
- [ ] manual

**發現的問題**:
[待填寫]

**建議改進**:
[待填寫]

---

## 下一步

驗證完成後：
1. 填寫所有測試結果
2. 截圖重要畫面（設定頁面、用戶資料、日誌輸出）
3. 建立 PHASE-12-SUMMARY.md
4. 標記 Phase 12 完成
5. Commit verification results
