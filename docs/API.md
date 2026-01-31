# BuyGo LINE Notify API 文件

本文件說明外掛提供的 Facade API、Service 類別和 WordPress Hooks。

## Facade API（推薦）

使用 `BuygoLineNotify\BuygoLineNotify` 類別提供簡潔的統一介面。

### 檢查外掛狀態

```php
use BuygoLineNotify\BuygoLineNotify;

// 檢查外掛是否啟用
if (!BuygoLineNotify::is_active()) {
    return;
}
```

### 訊息服務

```php
$messaging = BuygoLineNotify::messaging();

// 發送文字訊息（使用 WordPress User ID）
$result = $messaging->pushText($user_id, '您好，這是測試訊息');

// 發送圖片
$result = $messaging->pushImage($user_id, 'https://example.com/image.jpg');

// 發送 Flex Message
$result = $messaging->pushFlex($user_id, $flex_contents);

// 回覆訊息（需要 reply_token）
$result = $messaging->replyText($reply_token, '收到您的訊息了');
```

### 圖片服務

```php
$images = BuygoLineNotify::images();

// 下載 LINE 圖片到 Media Library
$attachment_id = $images->downloadToMediaLibrary($message_id, $user_id);
```

### LINE 用戶服務

```php
$lineUsers = BuygoLineNotify::line_users();

// 根據 LINE UID 查詢 WordPress User ID
$user_id = $lineUsers->getUserByLineUid($line_uid);

// 根據 WordPress User ID 查詢 LINE UID
$line_uid = $lineUsers->getLineUidByUserId($user_id);

// 檢查用戶是否已綁定 LINE
$is_linked = $lineUsers->isUserLinked($user_id);

// 建立綁定
$result = $lineUsers->linkUser($user_id, $line_uid, $is_registration);

// 解除綁定
$result = $lineUsers->unlinkUser($user_id);

// 取得完整綁定資料
$binding = $lineUsers->getBinding($user_id);
```

## Service 類別

### LineUserService

用戶綁定管理（靜態方法）。

```php
use BuygoLineNotify\Services\LineUserService;

// 新 API（v0.2，推薦使用）
LineUserService::getUserByLineUid(string $line_uid): ?int
LineUserService::getLineUidByUserId(int $user_id): ?string
LineUserService::isUserLinked(int $user_id): bool
LineUserService::linkUser(int $user_id, string $line_uid, bool $is_registration = false): bool
LineUserService::unlinkUser(int $user_id): bool
LineUserService::getBinding(int $user_id): ?object
LineUserService::getBindingByLineUid(string $line_uid): ?object

// 舊 API（已 deprecated，保留向後相容）
LineUserService::bind_line_account(int $user_id, string $line_uid, array $profile): bool
LineUserService::get_user_line_id(int $user_id): ?string
LineUserService::is_user_bound(int $user_id): bool
```

### StateManager

OAuth state 參數管理。

```php
use BuygoLineNotify\Services\StateManager;

$stateManager = new StateManager();

// 產生 32 字元隨機 state
$state = $stateManager->generate_state();

// 儲存 state 和相關資料（10 分鐘有效期）
$stateManager->store_state($state, [
    'redirect_url' => 'https://example.com/callback',
    'action' => 'login',
]);

// 驗證 state（返回儲存的資料或 false）
$data = $stateManager->verify_state($state);

// 消費 state（防重放攻擊）
$stateManager->consume_state($state);
```

### ProfileSyncService

LINE profile 同步到 WordPress。

```php
use BuygoLineNotify\Services\ProfileSyncService;

// 同步 profile（action: 'register', 'login', 'link'）
ProfileSyncService::syncProfile($user_id, [
    'displayName' => 'LINE Display Name',
    'email' => 'user@line.me',
    'pictureUrl' => 'https://profile.line-scdn.net/...',
], 'login');

// 取得同步日誌
$logs = ProfileSyncService::getSyncLog($user_id);

// 清除同步日誌
ProfileSyncService::clearSyncLog($user_id);
```

### AvatarService

LINE 頭像整合。

```php
use BuygoLineNotify\Services\AvatarService;

// 初始化（註冊 filter hook）
AvatarService::init();

// 清除單一用戶頭像快取
AvatarService::clearAvatarCache($user_id);

// 清除所有用戶頭像快取
AvatarService::clearAllAvatarCache();
```

### WebhookVerifier

Webhook 簽名驗證。

```php
use BuygoLineNotify\Services\WebhookVerifier;

$verifier = new WebhookVerifier();

// 檢查是否為開發模式
$is_dev = $verifier->is_development_mode();

// 驗證簽名
$is_valid = $verifier->verify_signature($request);
```

### SettingsService

外掛設定管理。

```php
use BuygoLineNotify\Services\SettingsService;

// 取得設定
$value = SettingsService::get('channel_access_token');
$value = SettingsService::get('channel_secret');

// 儲存設定（敏感欄位自動加密）
SettingsService::set('channel_access_token', $token);

// 取得 Profile Sync 設定
$strategy = SettingsService::get_conflict_strategy(); // 'line_priority' | 'wordpress_priority' | 'manual'
$sync_on_login = SettingsService::get_sync_on_login(); // bool
```

## WordPress Hooks

### LINE Login Hooks

```php
// LINE 登入成功後觸發
add_action('buygo_line_after_login', function($user_id, $line_uid, $profile) {
    // $user_id: WordPress User ID
    // $line_uid: LINE UID
    // $profile: LINE profile array (displayName, email, pictureUrl)
}, 10, 3);

// LINE 註冊成功後觸發（新用戶）
add_action('buygo_line_after_register', function($user_id, $line_uid, $profile) {
    // 新建立的用戶
}, 10, 3);

// LINE 綁定成功後觸發（已登入用戶綁定）
add_action('buygo_line_after_link', function($user_id, $line_uid, $profile) {
    // 已存在用戶新增 LINE 綁定
}, 10, 3);
```

### Webhook Hooks

```php
// 所有 Webhook 事件
add_action('buygo_line_notify/webhook_event', function($event, $event_type, $line_uid, $user_id) {
    // $event: 完整 event 物件
    // $event_type: 'message', 'follow', 'unfollow', 'postback' 等
    // $line_uid: LINE UID
    // $user_id: WordPress User ID（若已綁定）
}, 10, 4);

// 訊息事件
add_action('buygo_line_notify/webhook_message', function($event, $line_uid, $user_id) {
    $message_type = $event['message']['type']; // 'text', 'image', 'sticker' 等
    if ($message_type === 'text') {
        $text = $event['message']['text'];
    }
}, 10, 3);

// 關注事件
add_action('buygo_line_notify/webhook_follow', function($event, $line_uid, $user_id) {
    // 用戶加入好友
}, 10, 3);

// 取消關注事件
add_action('buygo_line_notify/webhook_unfollow', function($event, $line_uid, $user_id) {
    // 用戶封鎖或刪除好友
}, 10, 3);

// Postback 事件
add_action('buygo_line_notify/webhook_postback', function($event, $line_uid, $user_id) {
    $data = $event['postback']['data'];
}, 10, 3);
```

### Filter Hooks

```php
// 修改 LINE 登入按鈕 HTML
add_filter('buygo_line_login_button_html', function($html, $args) {
    return $html;
}, 10, 2);

// 修改 LINE 登入 URL
add_filter('buygo_line_login_url', function($url, $redirect_url) {
    return $url;
}, 10, 2);

// Avatar URL filter（內建實作）
// 已綁定 LINE 的用戶會自動返回 LINE 頭像
add_filter('get_avatar_url', [AvatarService::class, 'filterAvatarUrl'], 10, 3);
```

## Shortcodes

### [buygo_line_login]

顯示 LINE 登入按鈕。

| 參數 | 預設值 | 說明 |
|------|--------|------|
| `button_text` | `使用 LINE 帳號登入` | 按鈕文字 |
| `size` | `normal` | 按鈕大小：`small`, `normal`, `large` |
| `redirect_url` | 當前頁面 | 登入成功後跳轉 URL |

```php
// 基本用法
[buygo_line_login]

// 自訂按鈕
[buygo_line_login button_text="LINE 快速登入" size="large"]

// 指定跳轉 URL
[buygo_line_login redirect_url="https://example.com/my-account"]
```

### [buygo_line_register_flow]

註冊流程頁面，用於顯示 LINE profile 並讓用戶確認註冊。

```php
// 放在專屬頁面
[buygo_line_register_flow]
```

## REST API 端點

### Webhook

```
POST /wp-json/buygo-line-notify/v1/webhook
```

接收 LINE Webhook 事件。需要設定在 LINE Developers Console。

### Debug API

```
GET  /wp-json/buygo-line-notify/v1/debug/webhook-logs
GET  /wp-json/buygo-line-notify/v1/debug/message-logs
GET  /wp-json/buygo-line-notify/v1/debug/statistics
POST /wp-json/buygo-line-notify/v1/debug/clean-logs
```

需要管理員權限。

## 資料表結構

### wp_buygo_line_users

LINE 用戶綁定資料表（v0.2 單一真實來源）。

| 欄位 | 類型 | 說明 |
|------|------|------|
| ID | bigint(20) | 主鍵 |
| type | varchar(20) | 類型（'line'）|
| identifier | varchar(64) | LINE UID |
| user_id | bigint(20) | WordPress User ID |
| register_date | datetime | 透過 LINE 註冊時間 |
| link_date | datetime | 綁定時間 |

### wp_buygo_webhook_logs

Webhook 事件日誌。

### wp_buygo_message_logs

訊息發送日誌。

---

*最後更新: 2026-01-31*
