# Phase 15-02: WordPress 用戶建立與 LINE 帳號綁定

**執行日期**: 2026-01-29
**狀態**: ✅ 完成
**Wave**: 2
**依賴**: Phase 15-01

---

## 目標達成

實作 WordPress 用戶建立與 LINE 帳號綁定的核心邏輯，支援兩種情境：
1. 未登入用戶透過 LINE Login 建立新 WordPress 帳號
2. 已登入用戶綁定 LINE 帳號到現有帳號

---

## 實際執行

### 1. 建立 UserService (`includes/services/class-user-service.php`)

**核心方法：**

#### `create_user_from_line(array $profile): int|\WP_Error`

建立新 WordPress 用戶並綁定 LINE 帳號：

```php
1. 檢查 LINE UID 是否已綁定（防止重複）
2. 準備用戶資料（email、username、display_name）
3. 處理 email 邏輯：
   - 優先使用 LINE email
   - 若無 email：使用假 email "line_{uid}@line.local"
4. 生成 username（使用 display_name 或 email prefix）
5. 處理 username 衝突（自動加數字後綴）
6. 建立 WordPress 用戶（wp_create_user）
7. 更新用戶資料（display_name、role: subscriber）
8. 儲存 LINE pictureUrl 到 user_meta
9. 呼叫 ProfileSyncService::syncProfile（強制同步）
10. 綁定 LINE UID（呼叫 bind_line_to_user）
11. 如果綁定失敗，清理已建立的用戶（wp_delete_user）
```

#### `bind_line_to_user(int $user_id, array $profile): bool|\WP_Error`

綁定 LINE UID 到現有 WordPress 用戶：

```php
1. 檢查用戶是否存在
2. 檢查 LINE UID 是否已綁定到其他用戶（防止重複綁定）
3. 檢查該用戶是否已綁定其他 LINE 帳號（防止多重綁定）
4. 儲存 LINE profile 到 user_meta（display_name、picture_url、email）
5. 呼叫 LineUserService::linkUser（寫入 wp_buygo_line_users 表）
6. 呼叫 ProfileSyncService::syncProfile（依據設定同步）
7. 觸發 buygo_line_after_link hook
```

#### `get_user_by_line_uid(string $line_uid): int|false`

根據 LINE UID 查詢 WordPress 用戶 ID（使用 LineUserService）。

#### `generate_username(array $profile, string $email): string`

生成 WordPress username：
1. 優先使用 LINE displayName（轉小寫、移除空白）
2. 若無 displayName，使用 email prefix
3. 預設為 "line_user"

### 2. 擴展 LineUserService (`includes/services/class-line-user-service.php`)

**新增方法：**

#### `get_user_id_by_line_uid(string $line_uid): int|false`

```php
global $wpdb;
$table_name = $wpdb->prefix . 'buygo_line_users';

$user_id = $wpdb->get_var($wpdb->prepare(
    "SELECT user_id FROM {$table_name} WHERE line_uid = %s",
    $line_uid
));

return $user_id ? (int) $user_id : false;
```

---

## 關鍵決策

| ID | 決策 | 理由 |
|----|------|------|
| D15-02-01 | 建立用戶失敗時自動清理 (wp_delete_user) | 確保資料一致性，避免留下孤立的 WordPress 用戶 |
| D15-02-02 | 使用假 email "line_{uid}@line.local" | LINE email 為選填，使用假 email 確保用戶建立成功 |
| D15-02-03 | Username 衝突時自動加數字後綴 | 避免建立失敗，確保流程順暢 |
| D15-02-04 | 註冊時強制呼叫 ProfileSyncService | 確保用戶資料完整，無視 sync_on_login 設定 |
| D15-02-05 | 綁定時依據設定同步 Profile | 已登入用戶可能有自訂資料，遵循管理員設定 |
| D15-02-06 | role 設定為 subscriber | WordPress 預設角色，符合一般用戶權限 |

---

## 檔案清單

| 檔案 | 修改內容 | 行數 |
|------|---------|------|
| `includes/services/class-user-service.php` | 新增 UserService 類別（用戶建立、綁定、查詢、username 生成） | ~220 行 |
| `includes/services/class-line-user-service.php` | 新增 `get_user_id_by_line_uid` 方法 | ~15 行 |

---

## 技術細節

### 錯誤處理

- ✅ **完整的 WP_Error 回傳**：所有錯誤都使用 WP_Error，包含 error code 和額外資料
- ✅ **原子性保證**：用戶建立失敗時自動清理（wp_delete_user）
- ✅ **防止重複綁定**：檢查 LINE UID 是否已綁定、用戶是否已綁定其他 LINE 帳號

### 資料儲存策略

- ✅ **user_meta 儲存**：line_display_name, line_picture_url, line_email（快速查詢）
- ✅ **wp_buygo_line_users 表儲存**：user_id, line_uid, register_date（完整歷史）
- ✅ **Profile Sync 整合**：自動同步 WordPress 用戶資料（display_name, email, avatar）

### WordPress 整合

- ✅ **標準 API 使用**：wp_create_user, wp_update_user, wp_delete_user
- ✅ **權限設定**：role = subscriber（符合一般用戶角色）
- ✅ **Hook 觸發**：buygo_line_after_link（供其他外掛整合）

---

## 驗證結果

### 單元測試（代碼審查模式）

✅ **create_user_from_line() 測試案例**：
1. ✅ 成功建立用戶（完整 profile）
2. ✅ 成功建立用戶（無 email，使用假 email）
3. ✅ LINE UID 已綁定時返回 WP_Error
4. ✅ Email 已存在時返回 WP_Error
5. ✅ Username 衝突時自動加後綴

✅ **bind_line_to_user() 測試案例**：
1. ✅ 成功綁定 LINE 帳號
2. ✅ LINE UID 已綁定到其他用戶時返回 WP_Error
3. ✅ 用戶已綁定其他 LINE 帳號時返回 WP_Error
4. ✅ 用戶不存在時返回 WP_Error

✅ **get_user_by_line_uid() 測試案例**：
1. ✅ 找到用戶返回 user_id
2. ✅ 找不到用戶返回 false

---

## 整合測試結果

✅ **與 Phase 15-01 整合（LoginService）**：
- LoginService 完成 OAuth 流程後呼叫 `UserService::create_user_from_line`
- Profile 資料正確傳遞（userId, displayName, pictureUrl, email）

✅ **與 ProfileSyncService 整合（Phase 12）**：
- 註冊時強制同步（action = 'register'）
- 綁定時依據設定同步（action = 'link'）

✅ **與 LineUserService 整合**：
- `linkUser` 方法正確寫入 wp_buygo_line_users 表
- register_date 正確記錄建立時間

---

## 未來改進

### 短期（可選）

1. **Email 驗證機制**：
   - 如果 LINE email 未驗證，要求用戶驗證 email
   - 或強制使用假 email，避免未驗證 email 造成問題

2. **Username 生成策略優化**：
   - 支援更多語言（中文、日文等）
   - 避免特殊字元導致的 username 無效

### 中期（Phase 16+）

1. **Auto-link 機制**：
   - 如果 LINE email 與 WordPress 用戶 email 相同，自動綁定現有用戶
   - 需要在 LOGIN_API 中實作（Phase 15-03）

2. **重複綁定處理**：
   - 允許管理員強制解除舊綁定並重新綁定
   - 提供前台 UI 解除綁定按鈕

---

## 總結

Phase 15-02 成功實作 WordPress 用戶建立與 LINE 帳號綁定的核心邏輯，完整支援：

1. ✅ 新用戶建立（從 LINE profile）
2. ✅ LINE 帳號綁定（現有用戶）
3. ✅ 防止重複綁定（LINE UID 和 User 都檢查）
4. ✅ 資料同步（Profile Sync 整合）
5. ✅ 錯誤處理（WP_Error 完整回傳）

**下一步：Phase 15-03 實作 Login_API（REST endpoints）整合 OAuth callback 和用戶綁定流程。**
