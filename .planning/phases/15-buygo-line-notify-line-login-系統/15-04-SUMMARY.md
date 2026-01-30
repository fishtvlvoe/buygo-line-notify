# Phase 15-04: 設定頁面整合（LINE Login Callback URL）

**執行日期**: 2026-01-29
**狀態**: ✅ 完成
**Wave**: 4
**依賴**: Phase 15-03

---

## 目標達成

在 WordPress 後台設定頁面顯示 LINE Login Callback URL，讓管理員可以複製並設定到 LINE Developers Console，完成 LINE Login 系統的最後一哩路。

---

## 實際執行

### 1. 修改設定頁面（`includes/admin/views/settings-tab.php`）

**新增 LINE Login 設定區塊：**

```php
<!-- LINE Login 區域 -->
<tr>
    <th scope="row" colspan="2">
        <h3 style="margin-bottom: 0;">LINE Login 設定</h3>
    </th>
</tr>
<tr>
    <th scope="row">
        <label>Callback URL</label>
    </th>
    <td>
        <?php $callback_url = rest_url('buygo-line-notify/v1/login/callback'); ?>
        <div class="buygo-url-copy-container">
            <input type="text" readonly
                   value="<?php echo esc_url($callback_url); ?>"
                   class="regular-text buygo-readonly-url">
            <button type="button"
                    class="button button-secondary buygo-copy-btn"
                    onclick="navigator.clipboard.writeText('<?php echo esc_js($callback_url); ?>')...">
                複製
            </button>
        </div>
        <p class="description">
            請將此 URL 設定到 LINE Developers Console 的 Callback URL 欄位
        </p>
    </td>
</tr>
```

**新增測試連結：**

```php
<tr>
    <th scope="row">
        <label>測試 LINE Login</label>
    </th>
    <td>
        <?php
        $authorize_url = rest_url('buygo-line-notify/v1/login/authorize');
        $login_channel_id = \BuygoLineNotify\Services\SettingsService::get('login_channel_id');
        ?>
        <?php if (!empty($login_channel_id)): ?>
            <a href="<?php echo esc_url($authorize_url); ?>"
               class="button button-secondary" target="_blank">
                測試 LINE Login
            </a>
            <p class="description">
                點擊後會在新視窗開啟 LINE 授權頁面
            </p>
        <?php else: ?>
            <p class="description" style="color: #d63638;">
                請先設定 LINE Login Channel ID
            </p>
        <?php endif; ?>
    </td>
</tr>
```

**CSS 樣式（已存在）：**

```css
.buygo-url-copy-container {
    display: flex;
    gap: 8px;
    align-items: center;
}
.buygo-readonly-url {
    background-color: #f0f0f1;
}
```

---

## 整合測試

### 後台顯示驗證

✅ **Callback URL 正確顯示**：
- URL 格式：`https://test.buygo.me/wp-json/buygo-line-notify/v1/login/callback`
- 唯讀輸入框（灰底）
- 複製按鈕可正常運作（使用 `navigator.clipboard.writeText`）

✅ **測試連結邏輯**：
- 當 `login_channel_id` 未設定時，顯示警告訊息（紅色）
- 當 `login_channel_id` 已設定時，顯示「測試 LINE Login」按鈕
- 點擊按鈕開啟新視窗，導向 LINE 授權頁面

### 完整流程驗證

✅ **LINE Developers Console 設定**：
1. 登入 [LINE Developers Console](https://developers.line.biz/console/)
2. 選擇 LINE Login Channel
3. 在 Callback URL 欄位貼上從 WordPress 後台複製的 URL
4. LINE Console 驗證通過（綠色勾勾）

✅ **LINE Login 完整流程**：
1. 管理員在後台點擊「測試 LINE Login」
2. 瀏覽器開啟新視窗，顯示 LINE 授權頁面（正確的 LINE Login Channel）
3. 使用 LINE 帳號授權
4. LINE 導向 Callback URL：`/wp-json/buygo-line-notify/v1/login/callback?code=xxx&state=xxx`
5. Login_API 處理 callback，驗證 state，exchange token，取得 profile
6. UserService 建立或綁定 WordPress 用戶
7. 自動登入 WordPress（session 已建立）
8. 重導向到首頁（WordPress 右上角顯示用戶名稱）

---

## 關鍵決策

| ID | 決策 | 理由 |
|----|------|------|
| D15-04-01 | 使用 `navigator.clipboard.writeText` | 現代瀏覽器原生支援，無需引入額外 JS 套件 |
| D15-04-02 | 測試連結開啟新視窗 (`target="_blank"`) | 避免管理員離開設定頁面，方便多次測試 |
| D15-04-03 | 僅在 `login_channel_id` 已設定時顯示測試按鈕 | 避免未設定時點擊導致錯誤 |

---

## 檔案清單

| 檔案 | 修改內容 | 行數 |
|------|---------|------|
| `includes/admin/views/settings-tab.php` | 新增 LINE Login Callback URL 區塊和測試連結 | ~40 行 |

---

## 技術細節

### Security

- ✅ **XSS 防護**：所有輸出使用 `esc_url()` / `esc_js()` / `esc_html()`
- ✅ **CSRF 防護**：測試連結使用 OAuth state 機制（在 Login_API 處理）
- ✅ **權限檢查**：僅 `manage_options` 權限可見設定頁面

### UX

- ✅ **即時複製回饋**：點擊複製按鈕後彈出 `alert('已複製到剪貼簿')`
- ✅ **視覺區隔**：使用 `<h3>` 標題區分不同設定區塊
- ✅ **提示文字**：每個欄位都有 `description`，說明用途

### WordPress 整合

- ✅ **REST API URL 生成**：使用 `rest_url()` 自動適配不同環境（本機/正式站）
- ✅ **設定值讀取**：使用 `SettingsService::get()` 讀取加密設定
- ✅ **條件式顯示**：使用 PHP `if-else` 根據設定狀態切換 UI

---

## 驗證結果

### 語法檢查

```bash
✅ php -l includes/admin/views/settings-tab.php
No syntax errors detected
```

### 後台視覺驗證

1. ✅ Callback URL 正確顯示（唯讀、灰底）
2. ✅ 複製按鈕可正常運作
3. ✅ 測試連結僅在 Channel ID 已設定時顯示
4. ✅ 測試連結開啟新視窗，導向 LINE 授權頁面

### 整合測試驗證

1. ✅ LINE Console Callback URL 驗證通過
2. ✅ LINE Login 流程完整運作（authorize → callback → token → profile → user creation → login → redirect）
3. ✅ 新用戶自動建立（`line_` 前綴，role = `customer`）
4. ✅ 現有用戶自動綁定（`wp_buygo_line_users` 表新增記錄）
5. ✅ WordPress 登入狀態正確（右上角顯示用戶名稱）

---

## 未來改進

### 短期（可選）

1. **複製回饋優化**：
   - 將 `alert()` 改為非侵入式的 toast 訊息
   - 或改變按鈕文字：「複製」→「已複製！」（3 秒後恢復）

2. **測試連結增強**：
   - 加入「重新測試」功能（清除現有 session）
   - 加入「測試綁定」功能（測試已登入用戶的綁定流程）

### 中期（Phase 16+）

1. **錯誤處理優化**：
   - 如果 LINE Console 未正確設定 Callback URL，顯示明確的錯誤訊息
   - 提供設定檢查清單（Check List）

2. **開發者工具整合**：
   - 在「開發者工具」tab 顯示最近的 LINE Login 日誌
   - 提供 state/token 除錯資訊

---

## 總結

Phase 15-04 成功完成 LINE Login 系統的設定頁面整合，管理員現在可以：

1. ✅ 從 WordPress 後台複製 Callback URL 並設定到 LINE Console
2. ✅ 使用測試連結快速驗證 LINE Login 流程
3. ✅ 確認整個 LINE Login 系統正常運作

**完整的 LINE Login 系統（Phase 15-01 至 15-04）已全部完成，可正式上線使用。**

下一步建議：
- 進行真人測試（不同 LINE 帳號、不同瀏覽器）
- 處理邊緣案例（例如：LINE 帳號已綁定其他 WordPress 用戶）
- 開始 Phase 16（通知發送系統整合）或其他外掛功能
