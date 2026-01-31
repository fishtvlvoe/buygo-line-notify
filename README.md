# Buygo Line Notify

WordPress 外掛,提供 LINE Messaging API 整合功能,作為 BuyGo 系統的基礎設施層。

## 版本資訊

**當前版本**: 0.2.0
**WordPress 最低版本**: 6.0
**PHP 最低版本**: 8.0
**授權**: GPLv2 or later

## 功能特色

### 核心功能

- **LINE Login 整合**: 完整的 LINE OAuth 2.0 登入系統
  - 新用戶註冊 / Auto-link / 已登入綁定
  - Profile Sync（name、email、avatar）
  - 標準 WordPress URL 機制（wp-login.php?loginSocial=buygo-line）
  - Register Flow Page + Shortcode
- **LINE 訊息發送**: 支援文字、圖片、Flex Message
- **Webhook 接收**: 接收並處理 LINE Webhook 事件
- **圖片下載**: 自動下載 LINE 圖片到 WordPress Media Library
- **使用者綁定**: LINE UID 與 WordPress User ID 關聯管理
- **頭像整合**: 自動使用 LINE 頭像（get_avatar_url filter）
- **Debug 工具**: 完整的日誌記錄與後台管理介面

### 技術特點

- **Facade API 設計**: 簡單易用的統一介面
- **WordPress Hooks 整合**: 完整的事件驅動架構
- **去重機制**: 防止重複處理 Webhook 事件
- **背景處理**: FastCGI 或 WordPress Cron 背景執行
- **錯誤處理**: 完整的 WP_Error 錯誤處理機制
- **資料庫優化**: 索引優化與自動清理舊日誌

## 安裝方式

### 方法 1: 透過 WordPress 後台上傳

1. 下載 `buygo-line-notify.zip`
2. WordPress 後台 > 外掛 > 安裝外掛 > 上傳外掛
3. 選擇下載的 ZIP 檔案並安裝
4. 啟用外掛

### 方法 2: 手動安裝

1. 解壓縮 `buygo-line-notify.zip`
2. 上傳 `buygo-line-notify` 資料夾到 `/wp-content/plugins/`
3. WordPress 後台 > 外掛 > 已安裝的外掛
4. 找到 "Buygo Line Notify" 並啟用

## 設定步驟

### 1. 取得 LINE Channel Credentials

1. 前往 [LINE Developers Console](https://developers.line.biz/console/)
2. 建立 Messaging API Channel
3. 取得 **Channel Secret** 和 **Channel Access Token**

### 2. 設定外掛

1. WordPress 後台 > LINE Notify > 設定
2. 填寫 **Channel Secret**
3. 填寫 **Channel Access Token**
4. 儲存設定

### 3. 設定 Webhook URL

1. LINE Developers Console > 您的 Channel > Messaging API
2. Webhook URL 填寫: `https://your-site.com/wp-json/buygo-line-notify/v1/webhook`
3. 啟用 **Use webhook**
4. 停用 **Auto-reply messages** (避免重複回覆)

### 4. 設定 LINE Login（選用）

1. LINE Developers Console > 建立 LINE Login Channel
2. 取得 **Channel ID** 和 **Channel Secret**
3. 設定 Callback URL: `https://your-site.com/wp-login.php?loginSocial=buygo-line`
4. WordPress 後台 > LINE Notify > 設定 > 填寫 LINE Login 設定
5. 建立「註冊流程頁面」並放置 `[buygo_line_register_flow]` shortcode

## 使用方式

### Facade API (推薦)

```php
// 檢查外掛是否啟用
if (!BuygoLineNotify\BuygoLineNotify::is_active()) {
    return;
}

// 發送文字訊息
$messaging = BuygoLineNotify\BuygoLineNotify::messaging();
$result = $messaging->pushText($user_id, '您好,這是測試訊息');

// 下載圖片
$images = BuygoLineNotify\BuygoLineNotify::images();
$attachment_id = $images->downloadToMediaLibrary($message_id, $user_id);

// 查詢使用者綁定
$lineUsers = BuygoLineNotify\BuygoLineNotify::line_users();
$user = $lineUsers->getUserByLineUid($line_uid);
```

### LINE Login Shortcodes

```php
// 顯示 LINE 登入按鈕
[buygo_line_login]

// 顯示 LINE 登入按鈕（自訂樣式）
[buygo_line_login button_text="使用 LINE 登入" size="large"]

// 註冊流程頁面（放在專屬頁面）
[buygo_line_register_flow]
```

### WordPress Hooks

```php
// LINE 登入成功後
add_action('buygo_line_after_login', function($user_id, $line_uid, $profile) {
    // 處理登入成功
}, 10, 3);

// LINE 註冊成功後
add_action('buygo_line_after_register', function($user_id, $line_uid, $profile) {
    // 處理註冊成功
}, 10, 3);

// LINE 綁定成功後
add_action('buygo_line_after_link', function($user_id, $line_uid, $profile) {
    // 處理綁定成功
}, 10, 3);

// 監聽所有 Webhook 事件
add_action('buygo_line_notify/webhook_event', function($event, $event_type, $line_uid, $user_id) {
    // 處理事件
}, 10, 4);

// 監聯訊息事件 (文字/圖片)
add_action('buygo_line_notify/webhook_message', function($event, $line_uid, $user_id) {
    if ($event['message']['type'] === 'text') {
        $text = $event['message']['text'];
        // 處理文字訊息
    }
}, 10, 3);

// 監聽關注事件
add_action('buygo_line_notify/webhook_follow', function($event, $line_uid, $user_id) {
    // 發送歡迎訊息
}, 10, 3);
```

### 直接使用 Service 類別

```php
use BuygoLineNotify\Services\MessagingService;
use BuygoLineNotify\Services\ImageService;
use BuygoLineNotify\Services\LineUserService;

// 發送訊息
MessagingService::pushText($user_id, '您好');
MessagingService::pushImage($user_id, $image_url);
MessagingService::pushFlex($user_id, $flex_contents);

// 回覆訊息
MessagingService::replyText($reply_token, '收到您的訊息了');

// 下載圖片
$attachment_id = ImageService::downloadToMediaLibrary($message_id, $user_id);

// 使用者綁定
$user = LineUserService::getUserByLineUid($line_uid);
$line_uid = LineUserService::getLineUidByUserId($user_id);
```

## Debug 工具

### 後台管理介面

位置: **WordPress 後台 > LINE Notify > Debug Tools**

功能:
- 查看 Webhook 事件日誌
- 查看訊息發送日誌 (成功/失敗)
- 查看統計資料 (事件總數、發送成功率)
- 清理舊日誌 (保留指定天數)

### REST API 端點

```
GET  /wp-json/buygo-line-notify/v1/debug/webhook-logs?page=1&per_page=50
GET  /wp-json/buygo-line-notify/v1/debug/message-logs?page=1&per_page=50
GET  /wp-json/buygo-line-notify/v1/debug/statistics
POST /wp-json/buygo-line-notify/v1/debug/clean-logs
```

## 整合指南

本外掛設計為基礎設施層,可與其他外掛整合。

完整整合文件請參閱: [Buygo-Notify-Plus1 整合專案](../Buygo-Notify-Plus1/)

### 整合範例

參考 `buygo-plus-one` 外掛的整合方式:
- 訂單通知: 監聽 FluentCart 事件並發送 LINE 通知
- 商品上架: 透過 LINE 上傳圖片並建立 FluentCart 產品

## 資料表結構

外掛會建立以下資料表:

- `wp_buygo_line_users`: LINE 使用者綁定 (單一真實來源)
- `wp_buygo_line_bindings`: 舊版綁定資料 (向後相容,保留不刪除)
- `wp_buygo_webhook_logs`: Webhook 事件日誌
- `wp_buygo_message_logs`: 訊息發送日誌

## 系統需求

- WordPress 5.8 或更高版本
- PHP 7.4 或更高版本
- MySQL 5.7 或更高版本 / MariaDB 10.3 或更高版本
- HTTPS (LINE Webhook 要求)

## 常見問題

### Q1: Webhook 收不到事件

**檢查清單**:
1. Webhook URL 是否正確設定在 LINE Developers Console
2. WordPress 站點是否使用 HTTPS
3. Channel Secret 和 Access Token 是否正確
4. 查看 Debug Tools > Webhook 日誌是否有記錄

### Q2: 訊息發送失敗

**檢查清單**:
1. Channel Access Token 是否正確且有效
2. 使用者是否已綁定 LINE UID
3. 查看 Debug Tools > 訊息日誌查看錯誤訊息

### Q3: 圖片下載失敗

**檢查清單**:
1. Channel Access Token 是否有效
2. WordPress `wp-content/uploads/` 目錄權限是否正確
3. 網路連線是否正常

### Q4: 如何清理舊日誌?

**方法 1**: 使用後台工具
- WordPress 後台 > LINE Notify > Debug Tools > 清理舊日誌

**方法 2**: 使用程式碼
```php
use BuygoLineNotify\Services\Logger;
Logger::cleanOldLogs(30); // 保留 30 天
```

## 開發與測試

### 單元測試

```bash
cd buygo-line-notify
composer install
composer test
```

### 測試腳本

外掛提供測試腳本方便開發除錯:
- `test-messaging-service.php`: 測試訊息發送功能
- `test-binding-status.php`: 測試使用者綁定狀態

## 更新日誌

### 0.2.0 (2026-01-31)

**LINE Login 完整整合**

- ✨ LINE Login OAuth 2.0 完整流程
  - 標準 WordPress URL 機制（wp-login.php?loginSocial=buygo-line）
  - Register Flow Page + Shortcode 系統
  - 新用戶註冊 / Auto-link / 已登入綁定
  - State 驗證（32 字元隨機 + hash_equals + 10 分鐘有效期）

- ✨ Profile Sync 系統
  - 同步 LINE profile（display_name, email, avatar）
  - 衝突處理策略（LINE 優先 / WordPress 優先 / 手動）
  - 同步日誌記錄

- ✨ Avatar 整合
  - get_avatar_url filter hook
  - 7 天快取機制
  - 自動使用 LINE 頭像

- ✨ 前台整合
  - wp-login.php LINE 登入按鈕
  - [buygo_line_login] shortcode
  - 帳號綁定狀態顯示

- 🧪 單元測試
  - StateManager 測試
  - WebhookVerifier 測試
  - ProfileSyncService 測試
  - LineUserService 測試
  - AvatarService 測試

### 0.1.0 (2026-01-29)

**首次發布**

- ✨ 核心訊息發送功能 (MessagingService)
  - 支援文字、圖片、Flex Message
  - Push Message 和 Reply Message
  - 自動查詢 LINE UID

- ✨ Webhook 接收與處理 (WebhookHandler)
  - LINE 簽章驗證
  - 事件去重機制 (使用 webhookEventId)
  - FastCGI 背景處理
  - WordPress Hooks 整合

- ✨ 圖片下載服務 (ImageService)
  - 自動下載 LINE 圖片到 Media Library
  - 支援多種圖片格式 (JPG, PNG, GIF, WebP)
  - 自動產生縮圖

- ✨ 使用者綁定服務 (LineUserService)
  - LINE UID ↔ WordPress User ID 關聯
  - 向後相容舊系統資料
  - 支援多資料來源查詢

- ✨ Debug 工具 (Logger, Debug API, Admin Page)
  - Webhook 事件日誌
  - 訊息發送日誌
  - 統計資料儀表板
  - 自動清理舊日誌

- ✨ Facade API 設計
  - 簡單易用的統一介面
  - 完整的錯誤處理
  - 詳細的 PHPDoc 註解

## 授權

本外掛採用 GPLv2 (或更新版本) 授權。

## 技術支援

- **整合文件**: [Buygo-Notify-Plus1 整合專案](../Buygo-Notify-Plus1/)
- **LINE Messaging API 文件**: https://developers.line.biz/en/docs/messaging-api/
- **WordPress Plugin 開發指南**: https://developer.wordpress.org/plugins/

## 作者

BuyGo Development Team

---

**首次發布**: 2026-01-29
**版本**: 0.2.0
**最後更新**: 2026-01-31
