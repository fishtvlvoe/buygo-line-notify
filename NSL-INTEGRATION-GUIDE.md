# NSL Integration 使用指南

## 📋 概述

NSL Integration 是一個**過渡性解決方案**,允許 buygo-line-notify 與 Nextend Social Login (NSL) 外掛共存,同時:
- ✅ **隱藏 NSL 前台按鈕** - 用戶只看到 buygo-line-notify 的登入介面
- ✅ **自動同步登入資料** - 確保通知功能正常運作
- ✅ **自動清理綁定資料** - 刪除用戶時清除所有 LINE 相關記錄

---

## 🎯 使用場景

### 適用情況
- 需要快速上線 LINE 登入功能
- 希望利用 NSL 成熟的 OAuth 流程
- 短期內不打算完整移植 NSL 功能
- 想要統一的用戶登入體驗

### 不適用情況
- 完全不想依賴任何第三方外掛
- 有充足時間完整開發 OAuth 流程
- NSL 授權費用是問題

---

## 🚀 快速開始

### 1. 確保外掛已啟用

#### 必須啟用:
- ✅ `buygo-line-notify` (本外掛)
- ✅ `nextend-social-login-pro` 或 `nextend-social-login`

#### NSL 設定:
1. 前往 WordPress 後台 → 設定 → Nextend Social Login → LINE
2. 填入 LINE Channel ID 和 Channel Secret
3. **不需要**勾選 "Force reauthorization" (Integration 會自動處理)

### 2. 驗證整合狀態

訪問測試腳本確認整合運作正常:
```
https://your-site.com/test-scripts/test-nsl-integration.php
```

應該看到:
- ✅ NSLIntegration 類別已載入
- ✅ 所有 Hooks 已註冊
- ✅ NSL 外掛狀態正常

### 3. 測試用戶體驗

#### 前台登入頁面:
- ❌ 應該**看不到** NSL 的 LINE 登入按鈕
- ✅ 應該**只看到** buygo-line-notify 的登入按鈕

#### 登入流程:
1. 用戶點擊 LINE 登入按鈕
2. 跳轉到 LINE 授權頁面
3. 授權後返回網站
4. 自動建立/綁定 WordPress 帳號
5. 資料自動同步到 `wp_buygo_line_users`

---

## 🔧 功能詳解

### 功能 1: 隱藏 NSL 前台按鈕

#### 運作方式:
```php
// Filter: 禁用 NSL LINE provider
add_filter('nsl_is_provider_enabled_line', '__return_false', 9999);

// CSS: 隱藏任何殘留的 NSL 按鈕
add_action('wp_head', 'hide_nsl_buttons_css', 9999);
add_action('login_head', 'hide_nsl_buttons_css', 9999);
```

#### 效果:
- 用戶完全看不到 NSL 的 LINE 登入按鈕
- NSL 仍在後台運作,提供 OAuth 流程
- 只顯示 buygo-line-notify 的登入介面

---

### 功能 2: 自動同步登入資料

#### Hook: `nsl_login`
當用戶透過 NSL 成功登入時觸發

#### 運作邏輯:
```php
public static function ensure_sync_after_login(int $user_id, string $provider): void
{
    // 1. 檢查是否為 LINE 登入
    if ($provider !== 'line') {
        return;
    }

    // 2. 從 wp_social_users 取得 LINE UID
    $nsl_data = $wpdb->get_row("
        SELECT identifier, register_date 
        FROM wp_social_users
        WHERE ID = {$user_id} AND type = 'line'
    ");

    // 3. 檢查 wp_buygo_line_users 是否已存在
    $exists = $wpdb->get_var("
        SELECT COUNT(*) 
        FROM wp_buygo_line_users
        WHERE user_id = {$user_id} OR identifier = '{$nsl_data->identifier}'
    ");

    // 4. 如果不存在,插入新記錄
    if (!$exists) {
        $wpdb->insert('wp_buygo_line_users', [
            'type' => 'line',
            'identifier' => $nsl_data->identifier,
            'user_id' => $user_id,
            'register_date' => $nsl_data->register_date,
            'link_date' => current_time('mysql'),
        ]);
    }
}
```

#### 確保:
- ✅ 所有 LINE 登入的用戶都會被記錄
- ✅ 通知功能可以正常查詢 LINE UID
- ✅ 避免重複插入 (檢查 user_id 和 identifier)

---

### 功能 3: 自動清理綁定資料

#### Hooks:
- `delete_user` - 刪除用戶前記錄日誌
- `deleted_user` - 刪除用戶後清理資料

#### 清理範圍:
```php
public static function cleanup_after_user_deleted(int $user_id): void
{
    // 1. wp_buygo_line_users (主表)
    $wpdb->delete('wp_buygo_line_users', ['user_id' => $user_id]);

    // 2. wp_social_users (NSL)
    $wpdb->delete('wp_social_users', [
        'ID' => $user_id,
        'type' => 'line'
    ]);

    // 3. wp_buygo_line_bindings (舊表)
    $wpdb->delete('wp_buygo_line_bindings', ['user_id' => $user_id]);

    // 4. 所有 LINE 相關 user_meta
    delete_user_meta($user_id, 'line_uid');
    delete_user_meta($user_id, '_mygo_line_uid');
    delete_user_meta($user_id, 'buygo_line_user_id');
    delete_user_meta($user_id, 'm_line_user_id');
    delete_user_meta($user_id, 'line_user_id');
    delete_user_meta($user_id, 'nsl_user_avatar_md5');
}
```

#### 確保:
- ✅ 刪除用戶時完全清理 LINE 綁定
- ✅ 同一個 LINE 帳號可以重新註冊
- ✅ 不留下任何殘留資料

---

## 🧪 測試流程

### 測試 1: 新用戶註冊

1. **前台**: 點擊 LINE 登入按鈕
2. **LINE**: 授權應用程式
3. **返回**: 自動建立 WordPress 帳號
4. **驗證**: 檢查 `wp_buygo_line_users` 表中有記錄

```sql
SELECT * FROM wp_buygo_line_users WHERE user_id = <新用戶ID>;
```

應該看到:
- `type`: 'line'
- `identifier`: LINE UID (U開頭)
- `user_id`: WordPress User ID
- `register_date`: 註冊時間
- `link_date`: 綁定時間

---

### 測試 2: 刪除用戶後重新註冊

1. **後台**: 刪除測試用戶
2. **驗證**: 檢查所有表中的綁定都已清除

```sql
-- 應該都返回 0
SELECT COUNT(*) FROM wp_buygo_line_users WHERE user_id = <已刪除用戶ID>;
SELECT COUNT(*) FROM wp_social_users WHERE ID = <已刪除用戶ID>;
```

3. **前台**: 用同一個 LINE 帳號重新登入
4. **驗證**: 可以成功建立新的 WordPress 帳號

---

### 測試 3: 隱藏 NSL 按鈕

1. **前台**: 訪問登入頁面
2. **檢查**: 應該只看到 buygo-line-notify 的按鈕
3. **開發者工具**: 檢查 HTML,NSL 按鈕應該有 `display: none`

---

## 📊 狀態監控

### 取得整合狀態

```php
$status = \BuygoLineNotify\Integrations\NSLIntegration::get_status();

print_r($status);
```

輸出範例:
```php
Array (
    [nsl_active] => true
    [nsl_plugin_exists] => true
    [hooks_registered] => true
    [total_nsl_users] => 5
    [total_synced_users] => 5
)
```

### 檢查 Hooks

```php
// 檢查 nsl_login hook
if (has_action('nsl_login')) {
    echo "✅ NSL 登入 Hook 已註冊";
}

// 檢查 delete_user hook
if (has_action('delete_user')) {
    echo "✅ 刪除用戶 Hook 已註冊";
}
```

---

## 🚨 常見問題

### Q1: 為什麼還需要安裝 NSL?
**A**: 因為我們利用 NSL 的 OAuth 流程,這是一個成熟穩定的解決方案。NSL 在後台運作,用戶看不到它的介面。

### Q2: 用戶會看到兩個 LINE 登入按鈕嗎?
**A**: 不會。Integration 會自動隱藏 NSL 的按鈕,用戶只會看到 buygo-line-notify 的按鈕。

### Q3: 如果不啟用 NSL 會怎樣?
**A**: NSLIntegration 會自動偵測並停用整合功能。你需要使用 buygo-line-notify 自己的 OAuth 流程。

### Q4: 未來如何移除 NSL 依賴?
**A**: 上線穩定後,可以:
1. 實作完整的 LINE OAuth 流程
2. 逐步遷移用戶
3. 最後停用 NSL 外掛

### Q5: 會影響效能嗎?
**A**: 幾乎沒有影響。Integration 只在用戶登入和刪除時運作,平時不消耗資源。

---

## 🛠️ 故障排除

### 問題: NSL 按鈕仍然顯示

**檢查項目**:
1. 確認 `NSLIntegration::init()` 有被呼叫
2. 檢查 `wp_head` 和 `login_head` hooks
3. 清除瀏覽器快取

**手動隱藏**:
在主題的 `functions.php` 加入:
```php
add_action('wp_head', function() {
    echo '<style>.nsl-button-line { display: none !important; }</style>';
}, 9999);
```

---

### 問題: 登入後資料沒有同步

**檢查項目**:
1. 確認 NSL 外掛已啟用
2. 檢查 `nsl_login` hook 是否註冊
3. 查看 error_log 是否有錯誤訊息

**手動同步**:
訪問: `/test-scripts/force-sync-nsl-users.php`

---

### 問題: 刪除用戶後資料仍然存在

**檢查項目**:
1. 確認 `deleted_user` hook 是否註冊
2. 查看 error_log 確認清理是否執行

**手動清理**:
```sql
DELETE FROM wp_buygo_line_users WHERE user_id = <用戶ID>;
DELETE FROM wp_social_users WHERE ID = <用戶ID> AND type = 'line';
```

---

## 📝 未來規劃

### 階段 1: MVP (當前) - 2 週
- ✅ 使用 NSL OAuth 流程
- ✅ buygo-line-notify 提供 UI
- ✅ 自動同步和清理

### 階段 2: 過渡 - 1 個月
- 收集用戶反饋
- 優化使用者體驗
- 穩定運行監控

### 階段 3: 完整移植 - 2 個月
- 實作 LINE OAuth 流程
- 實作 Profile API
- 實作 Webhook 處理
- 分階段遷移用戶

### 階段 4: 獨立運作 - 完成
- 移除 NSL 依賴
- 完全自主的 LINE 登入系統

---

## 🔗 相關文件

- [SOLUTIONS-SUMMARY.md](../test-scripts/SOLUTIONS-SUMMARY.md) - 問題解決方案總結
- [class-nsl-integration.php](includes/integrations/class-nsl-integration.php) - Integration 原始碼
- [test-nsl-integration.php](../test-scripts/test-nsl-integration.php) - 測試腳本

---

## 📞 技術支援

如有問題,請:
1. 查看 error_log: `/wp-content/debug.log`
2. 執行測試腳本: `/test-scripts/test-nsl-integration.php`
3. 檢查整合狀態: `NSLIntegration::get_status()`

**記住**: 這是一個過渡方案,目標是快速上線,未來再逐步優化!🚀
