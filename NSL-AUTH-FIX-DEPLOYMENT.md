# NSL LINE 認證跳過問題修復 - 部署指南

## 問題描述

**症狀:** 在 one.buygo.me 點擊「綁定 LINE 帳號」按鈕後,不會跳轉到 LINE OAuth 認證頁面,直接返回網站首頁或會員頁面。

**根本原因:** NSL (Nextend Social Login) 檢測到用戶在 `wp_social_users` 表中已有 LINE 綁定記錄時,`getConnectUrl()` 會跳過 OAuth 認證流程。

## 修復內容

**Commit:** `e9e310d`  
**日期:** 2026-02-07  
**檔案:** `includes/services/class-login-service.php`

### 修改說明

在 `LoginService::get_nsl_authorize_url()` 方法的所有 NSL URL 生成路徑中,強制加入 `prompt=consent` 參數:

1. **getConnectUrl() 路徑** (已登入用戶)
   ```php
   $nsl_url = $provider->getConnectUrl();
   $nsl_url = add_query_arg('prompt', 'consent', $nsl_url);
   ```

2. **getLoginUrl() 路徑** (未登入用戶)
   ```php
   $nsl_url = $provider->getLoginUrl();
   $nsl_url = add_query_arg('prompt', 'consent', $nsl_url);
   ```

3. **Fallback 手動構建**
   ```php
   $params = array(
       'loginSocial' => 'line',
       'prompt'      => 'consent',
   );
   ```

## 部署步驟

### 1. 拉取最新程式碼

```bash
cd /path/to/buygo-line-notify
git pull origin main
```

確認已包含 commit `e9e310d`:
```bash
git log --oneline -5
```

應該看到:
```
e9e310d fix(nsl): 強制在 NSL OAuth URL 加入 prompt=consent 避免跳過認證
```

### 2. 上傳到生產環境

**方法 A: 使用 FTP/SFTP**
上傳檔案:
- `includes/services/class-login-service.php`

**方法 B: 使用 Git 部署**
```bash
cd /var/www/html/wp-content/plugins/buygo-line-notify
git pull origin main
```

### 3. 上傳測試腳本

將以下測試腳本上傳到 `one.buygo.me/test-scripts/`:

**主要驗證工具:**
```bash
# 從本地複製
scp verify-nsl-fix.php user@one.buygo.me:/path/to/public/test-scripts/
scp diagnose-and-fix-nsl-auth.php user@one.buygo.me:/path/to/public/test-scripts/
scp check-nsl-user-binding.php user@one.buygo.me:/path/to/public/test-scripts/
```

或使用 FTP 上傳這些檔案。

### 4. 清除快取

**WordPress 快取:**
```php
wp cache flush
```

或在後台手動清除快取 (如果使用快取外掛)。

**瀏覽器快取:**
硬性重新整理頁面 (Ctrl+F5 或 Cmd+Shift+R)

### 5. 驗證修復

#### 方法 1: 使用驗證工具 (推薦)

訪問: `https://one.buygo.me/test-scripts/verify-nsl-fix.php`

檢查項目:
- ✓ URL 包含 `prompt=consent` 參數
- ✓ 點擊「測試 LINE 綁定」會跳轉到 `access.line.me`
- ✓ OAuth 認證流程正常完成
- ✓ 返回網站後綁定成功

#### 方法 2: 手動測試

1. 登入 WordPress 帳號
2. 訪問 `https://one.buygo.me/my-account/`
3. 點擊「綁定 LINE 帳號」按鈕
4. **預期行為:**
   - 跳轉到 `https://access.line.me/oauth2/v2.1/authorize`
   - 顯示 LINE 授權頁面
   - 授權後返回網站
   - 顯示綁定成功訊息

## 故障排除

### 問題 1: 仍然跳過認證

**檢查清單:**
- [ ] 確認檔案已上傳 (檢查修改日期)
- [ ] 清除所有快取 (WordPress + 瀏覽器)
- [ ] 檢查 PHP 語法錯誤 (查看 error_log)
- [ ] 確認 NSL 外掛已啟用

**診斷命令:**
```bash
# 檢查檔案內容
grep "prompt.*consent" /path/to/buygo-line-notify/includes/services/class-login-service.php
```

應該有 3 處匹配。

### 問題 2: 驗證腳本顯示未修復

訪問: `https://one.buygo.me/test-scripts/diagnose-and-fix-nsl-auth.php`

檢查:
- NSL 設定狀態
- URL 參數分析
- 是否有歷史綁定記錄

### 問題 3: 出現 PHP 錯誤

查看錯誤日誌:
```bash
tail -f /path/to/wp-content/debug.log
```

常見錯誤:
- 語法錯誤 → 重新上傳檔案
- 類別不存在 → 確認 NSL 外掛已啟用
- 方法不存在 → 檢查 NSL 版本相容性

## 預期結果

### 修復前
```
用戶點擊綁定按鈕 
  → URL 變成 ?nsl_bypass_cache=... 
  → 直接返回網站 
  → ✗ 沒有看到 LINE 認證頁面
```

### 修復後
```
用戶點擊綁定按鈕 
  → 跳轉到 access.line.me 
  → 顯示 LINE OAuth 認證頁面 
  → 用戶授權 
  → 返回網站 
  → ✓ 綁定成功
```

## 技術細節

### 為什麼需要 prompt=consent?

LINE OAuth 2.0 規格中,`prompt=consent` 參數會:
- 強制顯示授權頁面
- 即使用戶已授權過也會再次詢問
- 繞過 NSL 的「已綁定檢測」邏輯

### NSL 行為分析

NSL 的 `getConnectUrl()` 在檢測到以下情況時會跳過認證:
1. `wp_social_users` 表中有對應的 LINE 綁定
2. Force Reauthorization 設定未啟用
3. 用戶 session 中有快取的綁定資訊

加入 `prompt=consent` 可以強制繞過這些檢查。

## 回滾指示

如果修復導致其他問題,可以回滾:

```bash
cd /path/to/buygo-line-notify
git revert e9e310d
```

或手動移除 `prompt=consent` 相關修改。

## 相關資源

- Debug 記錄: `.planning/debug/resolved/nsl-skip-line-authentication.md`
- 驗證腳本: `test-scripts/verify-nsl-fix.php`
- 診斷工具: `test-scripts/diagnose-and-fix-nsl-auth.php`
- NSL 整合指南: `NSL-INTEGRATION-GUIDE.md`

## 聯絡支援

如有問題,請提供:
- 驗證腳本的完整輸出
- `wp-content/debug.log` 相關錯誤
- 瀏覽器 Console 錯誤訊息
- 實際產生的 authorize URL

---

**修復完成日期:** 2026-02-07  
**測試狀態:** ⏳ 等待生產環境驗證  
**預期影響:** ✅ 解決 LINE 綁定跳過認證問題
