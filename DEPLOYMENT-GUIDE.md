# buygo-line-notify 部署指引

## 問題修復：生產環境 Fatal Error

**修復版本：** fbf10ad (2026-02-07)

**問題描述：**
- 生產環境（one.buygo.me）啟用外掛後，點擊 LINE 綁定按鈕導致 PHP Fatal Error
- 整個網站顯示「這個網站發生嚴重錯誤」

**根本原因：**
- NSL 整合程式碼只捕捉 `Exception`，未捕捉 `Error`
- PHP 7.0+ 將 Fatal Error（方法不存在、類別載入失敗）轉換為 `Error` 類型
- `try-catch` 無法捕捉 `Error`，導致網站崩潰

**修復內容：**
- 將 `catch (\Exception $e)` 改為 `catch (\Throwable $e)`
- 加入詳細的錯誤日誌（檔案、行號）
- API 端點返回更具體的錯誤訊息

---

## 部署方式

### 方式 1：使用外掛自動更新（推薦）

1. 登入 one.buygo.me 的 WordPress 後台
2. 前往「外掛」頁面
3. 檢查 `buygo-line-notify` 是否有更新提示
4. 點擊「更新」按鈕

**注意：** 外掛已設定 GitHub 自動更新，應該會自動檢測到新版本。

---

### 方式 2：手動下載並部署

如果自動更新不可用，請使用此方式。

#### 步驟 1：下載最新版本

```bash
# 方法 A：從 GitHub 下載 zip
https://github.com/fishtvlvoe/buygo-line-notify/archive/refs/heads/main.zip

# 方法 B：使用 git clone
cd /tmp
git clone https://github.com/fishtvlvoe/buygo-line-notify.git
cd buygo-line-notify
git checkout main
```

#### 步驟 2：連線到生產環境

使用 InstaWP 提供的 SFTP/SSH 資訊連線到 one.buygo.me。

**SFTP 連線資訊：**
- 主機：（請從 InstaWP 控制台查看）
- 使用者名稱：（請從 InstaWP 控制台查看）
- 密碼/SSH Key：（請從 InstaWP 控制台查看）
- 連接埠：通常是 22

#### 步驟 3：備份現有外掛

```bash
# SSH 連線後執行
cd /path/to/wp-content/plugins/
cp -r buygo-line-notify buygo-line-notify.backup
```

#### 步驟 4：上傳新版本

**使用 SFTP 用戶端（推薦）：**
1. 使用 Cyberduck、FileZilla 或其他 SFTP 用戶端
2. 連線到伺服器
3. 導航到 `/wp-content/plugins/buygo-line-notify/`
4. 只上傳修改的檔案：
   - `includes/services/class-login-service.php`
   - `includes/api/class-fluentcart-integration-api.php`

**使用 scp 命令：**
```bash
# 從本地上傳單一檔案
scp includes/services/class-login-service.php user@host:/path/to/plugins/buygo-line-notify/includes/services/
scp includes/api/class-fluentcart-integration-api.php user@host:/path/to/plugins/buygo-line-notify/includes/api/
```

#### 步驟 5：驗證檔案權限

```bash
# SSH 連線後執行
cd /path/to/wp-content/plugins/buygo-line-notify/
chmod 644 includes/services/class-login-service.php
chmod 644 includes/api/class-fluentcart-integration-api.php
```

---

## 驗證步驟

部署完成後，請執行以下驗證步驟確保修復成功。

### 1. 基本功能測試

1. 訪問 https://one.buygo.me/wp-admin/
2. 確認外掛已啟用
3. 前往「設定 > BuyGo LINE Notify」
4. 確認設定頁面正常顯示

### 2. LINE 綁定功能測試

1. 登入會員帳號（非管理員）
2. 訪問 https://one.buygo.me/my-account/
3. 找到「LINE 帳號綁定」區塊
4. 點擊「綁定 LINE 帳號」按鈕
5. **預期結果：**
   - ✅ 不會顯示 Fatal Error
   - ✅ API 成功返回 authorize_url
   - ✅ 頁面重定向到 LINE 授權頁面或 NSL 登入頁面

### 3. API 端點測試

**方式 A：使用瀏覽器（需登入）**

訪問以下 URL（需要先登入會員帳號）：

```
https://one.buygo.me/wp-json/buygo-line-notify/v1/fluentcart/bind-url?redirect_url=https://one.buygo.me/my-account/
```

**預期回應：**
```json
{
  "success": true,
  "authorize_url": "https://..."
}
```

**方式 B：使用診斷腳本**

1. 上傳 `test-scripts/diagnose-nsl-production.php` 到伺服器
2. 訪問：https://one.buygo.me/test-scripts/diagnose-nsl-production.php
3. 檢查輸出，確認沒有 Fatal Error

### 4. 錯誤日誌檢查

```bash
# SSH 連線後執行
tail -f /path/to/wp-content/debug.log
```

檢查是否有任何新的 PHP 錯誤或警告。

---

## 如果驗證失敗

### 問題 1：仍然出現 Fatal Error

**可能原因：**
- 檔案未正確上傳
- 快取問題

**解決方案：**
1. 確認檔案已正確上傳（檢查檔案大小和修改時間）
2. 清除 PHP OPcache（如果啟用）：
   ```bash
   # 在 SSH 中執行
   php -r "opcache_reset();"
   ```
3. 重新啟動 PHP-FPM（如果有權限）

### 問題 2：API 返回 500 錯誤

**檢查步驟：**
1. 查看 debug.log 中的錯誤訊息
2. 確認 NSL 外掛是否正確安裝
3. 檢查 buygo-line-notify 設定是否完整

### 問題 3：需要回退到舊版本

```bash
# SSH 連線後執行
cd /path/to/wp-content/plugins/
rm -rf buygo-line-notify
mv buygo-line-notify.backup buygo-line-notify
```

---

## 技術細節

### 修改的檔案

1. **includes/services/class-login-service.php**
   - 行 395：`catch (\Exception $e)` → `catch (\Throwable $e)`
   - 加入 file 和 line 到錯誤日誌

2. **includes/api/class-fluentcart-integration-api.php**
   - 行 191：`catch (\Exception $e)` → `catch (\Throwable $e)`
   - 改進錯誤日誌格式
   - 返回更具體的錯誤訊息

### PHP 7.0+ 錯誤處理

在 PHP 7.0+：
- `Exception` 和 `Error` 都實作 `Throwable` 介面
- Fatal Error（方法不存在、類別載入失敗）會拋出 `Error`
- `catch (\Exception $e)` 無法捕捉 `Error`
- `catch (\Throwable $e)` 可以同時捕捉 `Exception` 和 `Error`

### NSL 整合邏輯

外掛會按以下順序嘗試生成 LINE 授權 URL：

1. 檢查 NSL 是否可用（類別和方法存在）
2. 嘗試使用 NSL API：`NextendSocialLogin::getProviderByType('line')`
3. 如果失敗或不可用，fallback 到手動構建 URL
4. 返回最終的 authorize_url

---

## 聯絡資訊

如果部署過程遇到問題，請提供以下資訊：

1. debug.log 中的錯誤訊息（最近 50 行）
2. PHP 版本（執行 `php -v`）
3. WordPress 版本
4. NSL 外掛版本和類型（Pro/Free）
5. 診斷腳本的完整輸出

---

## 版本記錄

### v1.0.1 (2026-02-07)
- **修復：** NSL 整合導致的生產環境 Fatal Error
- **提交：** fbf10ad
- **相關 Issue：** 生產環境點擊 LINE 綁定按鈕崩潰

### v1.0.0 (2026-02-06)
- **新增：** NSL fallback 支援
- **提交：** 98111d9
