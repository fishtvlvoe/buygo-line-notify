# BuyGo Line Notify 與 Buygo Plus 1 整合計畫

## 1. 整合架構概覽

### 1.1 職責分離

**buygo-line-notify（基礎設施層）**
- LINE 訊息發送（Push Message、Reply Message）
- LINE Webhook 接收與驗證
- LINE 圖片下載至 WordPress Media Library
- LINE 使用者綁定管理（LINE UID ↔ WordPress User ID）
- Debug 日誌（Webhook 事件、訊息發送狀態）

**buygo-plus-one-dev（業務邏輯層）**
- 商品上架流程管理
- 訂單通知業務邏輯
- 模板文字生成（詢問商品資訊、確認訊息等）
- FluentCart 產品建立
- 權限檢查（誰可以上傳商品）

### 1.2 整合模式

```
LINE 使用者
    ↓ (上傳圖片/文字)
LINE Messaging API
    ↓ (Webhook)
buygo-line-notify/webhook (驗證、去重)
    ↓ (WordPress Hook: buygo_line_notify/webhook_event)
buygo-plus-one-dev/LineWebhookHandler (業務邏輯處理)
    ↓ (需要回覆訊息時)
buygo-line-notify/MessagingService (發送訊息)
    ↓ (Push/Reply)
LINE Messaging API
    ↓
LINE 使用者收到訊息
```

---

## 2. 現有整合狀態分析

### 2.1 buygo-plus-one-dev 已實作的整合點

#### 2.1.1 訂單通知（已完成）

**檔案**: `includes/services/class-line-order-notifier.php`

**功能**:
- 監聽 FluentCart 訂單事件 (`fluent_cart/order_created`, `fluent_cart/shipping_status_changed_to_shipped`)
- 延遲推播 + 重試機制（1/2/5 分鐘，最多 3 次）
- 去重（同一事件同一訂單只送一次）
- 使用 `buygo-line-notify` 的 `LineMessagingService` 發送訊息

**整合方式**:
```php
// 檢查外掛是否啟用
if (!class_exists('\BuygoLineNotify\BuygoLineNotify') || !\BuygoLineNotify\BuygoLineNotify::is_active()) {
    // 記錄錯誤但不中斷執行
    return;
}

// 使用 Facade API 發送訊息
$messaging = \BuygoLineNotify\BuygoLineNotify::messaging();
$pushResult = $messaging->push_message($lineUid, $message);
```

**狀態**: ✅ 已完成整合

#### 2.1.2 Webhook 端點（已完成）

**檔案**: `includes/api/class-line-webhook-api.php`

**功能**:
- 註冊 REST API 端點: `POST /wp-json/buygo-plus-one/v1/line/webhook`
- LINE 簽章驗證
- FastCGI 背景處理（`fastcgi_finish_request`）
- 呼叫 `LineWebhookHandler::process_events` 處理事件

**整合方式**:
```php
// Webhook API 接收 LINE 事件
public function handle_webhook($request) {
    // 驗證簽章
    if (!$this->verify_signature($request)) {
        return new \WP_Error('invalid_signature', 'Invalid signature', ['status' => 401]);
    }

    // 使用 FastCGI 或 WordPress Cron 背景處理
    if (function_exists('fastcgi_finish_request')) {
        add_action('shutdown', function() use ($data, $webhook_handler) {
            fastcgi_finish_request();
            $webhook_handler->process_events($data['events'], false);
        });
    }

    // 立即返回 200
    return rest_ensure_response(['success' => true]);
}
```

**狀態**: ✅ 已完成整合

#### 2.1.3 LINE 使用者服務（已完成）

**檔案**: `includes/services/class-line-service.php`

**功能**:
- 取得 LINE UID 透過多種來源（向後相容）:
  1. `wp_buygo_line_bindings` (buygo-line-notify)
  2. `wp_usermeta` (`_mygo_line_uid` - 舊系統)
  3. `wp_social_users` (Nextend Social Login)

**整合方式**:
```php
public function get_user_by_line_uid($line_uid) {
    // 1. 優先查詢 buygo-line-notify 的資料表
    // 2. Fallback 到舊系統
    // 3. 確保向後相容
}

public function get_line_uid($user_id) {
    // 同樣支援多來源查詢
}
```

**狀態**: ✅ 已完成整合

#### 2.1.4 通知模板（已完成）

**檔案**: `includes/services/class-notification-templates.php`

**功能**:
- 管理 LINE 通知模板（Text / Flex Message）
- 支援變數替換（`{{order_id}}`, `{{total}}` 等）
- 根據 `trigger_condition` 篩選模板

**使用範例**:
```php
// LineOrderNotifier 使用模板系統
$templates = NotificationTemplates::get_by_trigger_condition('order_created', $args);
$template = $templates[0] ?? null;

if ($template['type'] === 'flex') {
    $message = NotificationTemplates::build_flex_message($flex);
}
```

**狀態**: ✅ 已完成整合

### 2.2 buygo-line-notify 已提供的整合 API

#### 2.2.1 Facade API (推薦使用)

**檔案**: `buygo-line-notify.php`

**可用方法**:
```php
// 檢查外掛是否啟用
BuygoLineNotify\BuygoLineNotify::is_active()

// 取得 MessagingService 實例
BuygoLineNotify\BuygoLineNotify::messaging()

// 取得 LineUserService 實例
BuygoLineNotify\BuygoLineNotify::line_users()

// 取得 ImageService 實例
BuygoLineNotify\BuygoLineNotify::images()
```

#### 2.2.2 MessagingService (訊息發送)

**檔案**: `includes/services/class-messaging-service.php`

**可用方法**:
```php
// 發送文字訊息（自動查詢 LINE UID）
MessagingService::pushText(int $user_id, string $text)

// 發送圖片訊息
MessagingService::pushImage(int $user_id, string $image_url, ?string $preview_url = null)

// 發送 Flex Message
MessagingService::pushFlex(int $user_id, array $flex_contents)

// 回覆訊息（需要 replyToken）
MessagingService::replyText(string $reply_token, string $text)
MessagingService::replyImage(string $reply_token, string $image_url, ?string $preview_url = null)
MessagingService::replyFlex(string $reply_token, array $flex_contents)

// 檢查使用者是否已綁定 LINE
MessagingService::isUserLinked(int $user_id): bool
```

#### 2.2.3 LineUserService (使用者綁定)

**檔案**: `includes/services/class-line-user-service.php`

**可用方法**:
```php
// 取得 WordPress 使用者（透過 LINE UID）
LineUserService::getUserByLineUid(string $line_uid): ?\WP_User

// 取得 LINE UID（透過 WordPress User ID）
LineUserService::getLineUidByUserId(int $user_id): ?string

// 綁定 LINE UID 與 WordPress User ID
LineUserService::linkLineUser(string $line_uid, int $user_id, array $profile = []): bool

// 解除綁定
LineUserService::unlinkLineUser(string $line_uid): bool
```

#### 2.2.4 ImageService (圖片下載)

**檔案**: `includes/services/class-image-service.php`

**可用方法**:
```php
// 下載 LINE 圖片到 WordPress Media Library
ImageService::downloadToMediaLibrary(string $message_id, ?int $user_id = null): int|\WP_Error

// 取得 attachment URL
ImageService::get_attachment_url(int $attachment_id): string|false
```

#### 2.2.5 WordPress Hooks (事件監聽)

**檔案**: `includes/services/class-webhook-handler.php`

**可用 Hook**:
```php
// Webhook 事件（所有事件類型）
do_action('buygo_line_notify/webhook_event', $event, $event_type, $line_uid, $user_id);

// 特定事件類型
do_action('buygo_line_notify/webhook_message', $event, $line_uid, $user_id);        // 文字/圖片訊息
do_action('buygo_line_notify/webhook_follow', $event, $line_uid, $user_id);         // 關注
do_action('buygo_line_notify/webhook_unfollow', $event, $line_uid, $user_id);       // 取消關注
do_action('buygo_line_notify/webhook_postback', $event, $line_uid, $user_id);       // Postback
do_action('buygo_line_notify/webhook_beacon', $event, $line_uid, $user_id);         // Beacon
do_action('buygo_line_notify/webhook_accountLink', $event, $line_uid, $user_id);    // Account Link
do_action('buygo_line_notify/webhook_memberJoined', $event, $line_uid, $user_id);   // 成員加入
do_action('buygo_line_notify/webhook_memberLeft', $event, $line_uid, $user_id);     // 成員離開
```

**Hook 參數說明**:
- `$event`: 完整的 LINE Webhook Event 物件
- `$event_type`: 事件類型（`message`, `follow`, `postback` 等）
- `$line_uid`: LINE User ID（如果可用）
- `$user_id`: WordPress User ID（如果已綁定）

---

## 3. 商品上架流程整合方案

### 3.1 使用者故事

1. 賣家在 LINE 上傳圖片
2. buygo-line-notify 接收圖片 → 下載到 Media Library
3. buygo-plus-one 收到圖片事件 → 檢查權限 → 產生「模板文字」詢問商品資訊
4. buygo-line-notify 發送模板文字給賣家
5. 賣家在 LINE 回覆商品資訊
6. buygo-line-notify 接收文字訊息
7. buygo-plus-one 收到文字內容 → 解析 → 建立 FluentCart 產品
8. FluentCart 返回產品連結
9. buygo-plus-one 組裝確認訊息
10. buygo-line-notify 發送確認訊息給賣家

### 3.2 技術流程

#### 步驟 1-2: 圖片上傳與下載

**buygo-line-notify 已完成**:
- Webhook 接收 `message` 事件（`message.type === 'image'`）
- 觸發 `buygo_line_notify/webhook_message` Hook
- 提供 `$event['message']['id']` (LINE Message ID)

**buygo-plus-one 需要實作**:
```php
// 在 LineWebhookHandler 中監聽圖片訊息
add_action('buygo_line_notify/webhook_message', [$this, 'handleImageUpload'], 10, 3);

public function handleImageUpload($event, $line_uid, $user_id) {
    // 檢查是否為圖片訊息
    if ($event['message']['type'] !== 'image') {
        return;
    }

    // 1. 檢查權限（can_upload_product）
    if (!$this->can_upload_product($user_id)) {
        // 發送無權限訊息
        $messaging = \BuygoLineNotify\BuygoLineNotify::messaging();
        $messaging->replyText($event['replyToken'], '您沒有上架商品的權限');
        return;
    }

    // 2. 下載圖片到 Media Library
    $imageService = \BuygoLineNotify\BuygoLineNotify::images();
    $attachment_id = $imageService->downloadToMediaLibrary($event['message']['id'], $user_id);

    if (is_wp_error($attachment_id)) {
        // 處理錯誤
        $messaging = \BuygoLineNotify\BuygoLineNotify::messaging();
        $messaging->replyText($event['replyToken'], '圖片下載失敗，請稍後再試');
        return;
    }

    // 3. 儲存上傳狀態到用戶 meta（記錄 attachment_id，等待後續文字輸入）
    update_user_meta($user_id, 'pending_product_image', $attachment_id);
    update_user_meta($user_id, 'pending_product_timestamp', time());

    // 4. 產生模板文字詢問商品資訊
    $template = $this->getProductInfoTemplate();

    // 5. 發送模板文字
    $messaging = \BuygoLineNotify\BuygoLineNotify::messaging();
    $messaging->replyText($event['replyToken'], $template);
}
```

#### 步驟 5-7: 接收商品資訊並建立產品

**buygo-line-notify 已完成**:
- Webhook 接收 `message` 事件（`message.type === 'text'`）
- 觸發 `buygo_line_notify/webhook_message` Hook
- 提供 `$event['message']['text']` (使用者輸入文字)

**buygo-plus-one 需要實作**:
```php
// 在 LineWebhookHandler 中監聽文字訊息
add_action('buygo_line_notify/webhook_message', [$this, 'handleProductInfo'], 10, 3);

public function handleProductInfo($event, $line_uid, $user_id) {
    // 檢查是否為文字訊息
    if ($event['message']['type'] !== 'text') {
        return;
    }

    // 1. 檢查是否有待處理的商品圖片
    $attachment_id = get_user_meta($user_id, 'pending_product_image', true);
    $timestamp = get_user_meta($user_id, 'pending_product_timestamp', true);

    if (empty($attachment_id)) {
        // 沒有待處理的商品，忽略此訊息（可能是其他對話）
        return;
    }

    // 檢查是否超時（例如 30 分鐘）
    if (time() - $timestamp > 1800) {
        delete_user_meta($user_id, 'pending_product_image');
        delete_user_meta($user_id, 'pending_product_timestamp');

        $messaging = \BuygoLineNotify\BuygoLineNotify::messaging();
        $messaging->replyText($event['replyToken'], '商品上架已超時，請重新上傳圖片');
        return;
    }

    // 2. 解析商品資訊
    $product_info = $this->parseProductInfo($event['message']['text']);

    if (is_wp_error($product_info)) {
        $messaging = \BuygoLineNotify\BuygoLineNotify::messaging();
        $messaging->replyText($event['replyToken'], '商品資訊格式錯誤：' . $product_info->get_error_message());
        return;
    }

    // 3. 建立 FluentCart 產品
    $product_data = [
        'title' => $product_info['title'],
        'price' => $product_info['price'],
        'description' => $product_info['description'],
        'featured_image' => $attachment_id,
        // ... 其他欄位
    ];

    $product = ProductService::create($product_data);

    if (is_wp_error($product)) {
        $messaging = \BuygoLineNotify\BuygoLineNotify::messaging();
        $messaging->replyText($event['replyToken'], '商品建立失敗：' . $product->get_error_message());
        return;
    }

    // 4. 清除待處理狀態
    delete_user_meta($user_id, 'pending_product_image');
    delete_user_meta($user_id, 'pending_product_timestamp');

    // 5. 取得商品連結
    $product_url = $this->getProductUrl($product->id);

    // 6. 組裝確認訊息
    $confirmation = $this->buildConfirmationMessage($product, $product_url);

    // 7. 發送確認訊息
    $messaging = \BuygoLineNotify\BuygoLineNotify::messaging();
    $messaging->replyText($event['replyToken'], $confirmation);
}
```

### 3.3 模板文字範例

#### 商品資訊詢問模板
```
📸 收到您的商品圖片！

請提供以下商品資訊：
1️⃣ 商品名稱
2️⃣ 商品價格（元）
3️⃣ 商品描述

範例：
MacBook Pro 13
45000
全新未拆封，原廠保固
```

#### 確認訊息模板
```
✅ 商品已成功上架！

商品名稱：{{product_title}}
商品價格：NT$ {{product_price}}
商品連結：{{product_url}}

您可以透過以上連結查看商品詳情。
```

---

## 4. 其他事件整合方案

### 4.1 關注事件（Follow）

**使用場景**: 使用者關注 LINE Bot 時發送歡迎訊息

**buygo-line-notify 已完成**:
- 觸發 `buygo_line_notify/webhook_follow` Hook

**buygo-plus-one 可選實作**:
```php
add_action('buygo_line_notify/webhook_follow', [$this, 'handleFollow'], 10, 3);

public function handleFollow($event, $line_uid, $user_id) {
    // 發送歡迎訊息
    $welcome_message = "歡迎使用 BuyGo 商品上架系統！\n\n直接上傳商品圖片即可開始上架。";

    $messaging = \BuygoLineNotify\BuygoLineNotify::messaging();
    $messaging->replyText($event['replyToken'], $welcome_message);
}
```

### 4.2 取消關注事件（Unfollow）

**使用場景**: 使用者取消關注時記錄日誌

**buygo-line-notify 已完成**:
- 觸發 `buygo_line_notify/webhook_unfollow` Hook

**buygo-plus-one 可選實作**:
```php
add_action('buygo_line_notify/webhook_unfollow', [$this, 'handleUnfollow'], 10, 3);

public function handleUnfollow($event, $line_uid, $user_id) {
    // 記錄日誌（可選）
    $this->debugService->log('LINE', '使用者取消關注', [
        'line_uid' => $line_uid,
        'user_id' => $user_id,
    ]);
}
```

### 4.3 Postback 事件

**使用場景**: 使用者點擊 Flex Message 中的按鈕

**buygo-line-notify 已完成**:
- 觸發 `buygo_line_notify/webhook_postback` Hook
- 提供 `$event['postback']['data']` (按鈕攜帶的資料)

**buygo-plus-one 可選實作**:
```php
add_action('buygo_line_notify/webhook_postback', [$this, 'handlePostback'], 10, 3);

public function handlePostback($event, $line_uid, $user_id) {
    $data = $event['postback']['data'] ?? '';

    // 解析 postback data（例如: action=confirm_product&product_id=123）
    parse_str($data, $params);

    if ($params['action'] === 'confirm_product') {
        // 處理商品確認
        $product_id = $params['product_id'];
        // ...
    }
}
```

---

## 5. 資料流與狀態管理

### 5.1 商品上架狀態機

```
[閒置]
    ↓ (上傳圖片)
[等待商品資訊] (user_meta: pending_product_image, pending_product_timestamp)
    ↓ (輸入文字)
[處理中] (解析、建立產品)
    ↓ (成功)
[完成] (清除 user_meta, 發送確認訊息)
    ↓
[閒置]
```

### 5.2 User Meta 欄位

| Meta Key | 說明 | 清除時機 |
|----------|------|----------|
| `pending_product_image` | 待處理商品圖片 (attachment_id) | 商品建立成功 / 超時 |
| `pending_product_timestamp` | 圖片上傳時間戳 (timestamp) | 商品建立成功 / 超時 |
| `last_product_upload_error` | 最後一次上傳錯誤 | 下次成功上傳 |

### 5.3 超時處理

**建議超時時間**: 30 分鐘

**超時邏輯**:
```php
// 檢查是否超時
$timestamp = get_user_meta($user_id, 'pending_product_timestamp', true);
if (time() - $timestamp > 1800) { // 30 分鐘
    // 清除狀態
    delete_user_meta($user_id, 'pending_product_image');
    delete_user_meta($user_id, 'pending_product_timestamp');

    // 通知使用者
    $messaging = \BuygoLineNotify\BuygoLineNotify::messaging();
    $messaging->pushText($user_id, '商品上架已超時，請重新上傳圖片');
}
```

---

## 6. 錯誤處理與除錯

### 6.1 常見錯誤場景

#### 6.1.1 圖片下載失敗

**原因**:
- LINE Channel Access Token 未設定或過期
- 網路連線問題
- LINE API 限流（HTTP 429）

**處理**:
```php
$attachment_id = $imageService->downloadToMediaLibrary($event['message']['id'], $user_id);

if (is_wp_error($attachment_id)) {
    $error_code = $attachment_id->get_error_code();
    $error_message = $attachment_id->get_error_message();

    // 記錄錯誤
    $this->debugService->log('ImageDownload', '圖片下載失敗', [
        'error_code' => $error_code,
        'error_message' => $error_message,
        'message_id' => $event['message']['id'],
    ]);

    // 通知使用者
    $messaging = \BuygoLineNotify\BuygoLineNotify::messaging();
    $messaging->replyText($event['replyToken'], '圖片下載失敗，請稍後再試');

    return;
}
```

#### 6.1.2 產品建立失敗

**原因**:
- 必填欄位缺少
- 商品資訊格式錯誤
- FluentCart API 錯誤

**處理**:
```php
$product = ProductService::create($product_data);

if (is_wp_error($product)) {
    // 記錄詳細錯誤
    $this->debugService->log('ProductCreation', '商品建立失敗', [
        'error' => $product->get_error_message(),
        'product_data' => $product_data,
        'user_id' => $user_id,
    ]);

    // 提供友善錯誤訊息
    $messaging = \BuygoLineNotify\BuygoLineNotify::messaging();
    $messaging->replyText($event['replyToken'],
        '商品建立失敗，請檢查以下資訊：\n' .
        '• 商品名稱是否填寫\n' .
        '• 商品價格是否為數字'
    );

    return;
}
```

### 6.2 Debug 工具

**buygo-line-notify 提供的 Debug 工具**:

1. **Webhook 事件日誌**
   - 路徑: WordPress 後台 > LINE Notify > Debug Tools
   - 查看所有接收到的 Webhook 事件

2. **訊息發送日誌**
   - 查看所有發送的訊息（成功/失敗）
   - 包含錯誤訊息

3. **統計資料**
   - Webhook 事件總數
   - 訊息發送成功率

**buygo-plus-one 可使用的 Debug 方法**:

```php
// 使用 buygo-plus-one 的 DebugService
$this->debugService->log('ProductUpload', '商品上架流程', [
    'step' => 'image_uploaded',
    'attachment_id' => $attachment_id,
    'user_id' => $user_id,
]);
```

---

## 7. 測試計畫

### 7.1 單元測試

**buygo-line-notify 測試**（已完成）:
- ✅ MessagingService 訊息發送測試
- ✅ LineUserService 綁定查詢測試
- ✅ ImageService 圖片下載測試
- ✅ WebhookHandler 去重測試

**buygo-plus-one 需補充測試**:
- LineWebhookHandler 權限檢查
- 商品資訊解析邏輯
- ProductService FluentCart 整合

### 7.2 整合測試

#### 測試案例 1: 完整商品上架流程

**步驟**:
1. 使用者在 LINE 上傳圖片
2. 驗證 Webhook 接收成功
3. 驗證圖片下載到 Media Library
4. 驗證模板文字發送成功
5. 使用者回覆商品資訊
6. 驗證 FluentCart 產品建立成功
7. 驗證確認訊息發送成功

**預期結果**:
- 所有步驟成功完成
- 產品在 FluentCart 可見
- 使用者收到確認訊息

#### 測試案例 2: 權限不足

**步驟**:
1. 非授權使用者在 LINE 上傳圖片
2. 驗證收到「無權限」訊息

**預期結果**:
- 圖片不會被下載
- 使用者收到權限錯誤訊息

#### 測試案例 3: 超時處理

**步驟**:
1. 使用者上傳圖片
2. 等待超過 30 分鐘
3. 使用者輸入商品資訊

**預期結果**:
- 使用者收到「超時」訊息
- user_meta 已清除

#### 測試案例 4: 圖片下載失敗

**步驟**:
1. 模擬 LINE API 錯誤（例如 Token 過期）
2. 使用者上傳圖片

**預期結果**:
- 使用者收到「下載失敗」訊息
- 錯誤記錄在 Debug 日誌

### 7.3 測試環境

**測試用 LINE Bot**:
- 使用 LINE Developers Console 建立測試 Bot
- 設定 Webhook URL: `https://test.buygo.me/wp-json/buygo-plus-one/v1/line/webhook`

**測試腳本**:
```bash
# 測試 Webhook 端點
curl -X POST https://test.buygo.me/wp-json/buygo-plus-one/v1/line/webhook \
  -H "Content-Type: application/json" \
  -H "x-line-signature: <signature>" \
  -d @test-webhook.json
```

---

## 8. 遷移與部署

### 8.1 前置準備

1. **確認 buygo-line-notify 已啟用**
   ```php
   if (!class_exists('\BuygoLineNotify\BuygoLineNotify')) {
       wp_die('請先安裝並啟用 BuyGo Line Notify 外掛');
   }
   ```

2. **設定 LINE Channel Secret 和 Access Token**
   - WordPress 後台 > LINE Notify > 設定
   - 填寫 Channel Secret 和 Channel Access Token

3. **設定 Webhook URL**
   - LINE Developers Console
   - Webhook URL: `https://your-site.com/wp-json/buygo-plus-one/v1/line/webhook`

### 8.2 程式碼遷移步驟

1. **新增 Hook 監聽器**（buygo-plus-one）
   ```php
   // includes/services/class-line-webhook-handler.php
   public function __construct() {
       add_action('buygo_line_notify/webhook_message', [$this, 'handleImageUpload'], 10, 3);
       add_action('buygo_line_notify/webhook_message', [$this, 'handleProductInfo'], 10, 3);
       add_action('buygo_line_notify/webhook_follow', [$this, 'handleFollow'], 10, 3);
   }
   ```

2. **移除舊的 Webhook 處理邏輯**（如果有）
   - 移除直接呼叫 LINE API 的程式碼
   - 改用 `buygo-line-notify` 的 Facade API

3. **更新訊息發送邏輯**
   ```php
   // 舊寫法（直接呼叫 LINE API）
   $response = wp_remote_post('https://api.line.me/v2/bot/message/push', [
       'headers' => ['Authorization' => 'Bearer ' . $token],
       'body' => json_encode($data),
   ]);

   // 新寫法（使用 buygo-line-notify）
   $messaging = \BuygoLineNotify\BuygoLineNotify::messaging();
   $messaging->pushText($user_id, $text);
   ```

### 8.3 部署檢查清單

- [ ] buygo-line-notify 外掛已安裝並啟用
- [ ] LINE Channel Secret 已設定
- [ ] LINE Channel Access Token 已設定
- [ ] Webhook URL 已設定在 LINE Developers Console
- [ ] Webhook 簽章驗證測試通過
- [ ] 測試發送訊息成功
- [ ] 測試接收訊息成功
- [ ] 測試圖片下載成功
- [ ] 測試完整商品上架流程
- [ ] Debug 日誌正常運作

---

## 9. 效能考量

### 9.1 Webhook 處理

**問題**: Webhook 處理時間過長導致 LINE 超時（10 秒）

**解決方案**（buygo-plus-one 已實作）:
- 使用 FastCGI `fastcgi_finish_request` 背景處理
- Fallback 到 WordPress Cron

**程式碼**:
```php
// includes/api/class-line-webhook-api.php
if (function_exists('fastcgi_finish_request')) {
    add_action('shutdown', function() use ($data, $webhook_handler) {
        fastcgi_finish_request();
        $webhook_handler->process_events($data['events'], false);
    });
} else {
    wp_schedule_single_event(time(), 'buygo_process_line_webhook', [$data['events']]);
}
```

### 9.2 圖片下載

**問題**: 大圖片下載耗時

**解決方案**:
- 使用 WordPress 原生 `wp_remote_get` (支援 timeout 設定)
- 設定合理 timeout (30 秒)

**程式碼**:
```php
// buygo-line-notify: includes/services/class-image-service.php
$response = wp_remote_get($url, [
    'headers' => ['Authorization' => 'Bearer ' . $access_token],
    'timeout' => 30, // 30 秒超時
]);
```

### 9.3 去重機制

**問題**: 相同 Webhook Event 重複處理

**解決方案**（buygo-line-notify 已實作）:
- 使用 `webhookEventId` + Transient 去重
- 5 分鐘有效期

**程式碼**:
```php
// buygo-line-notify: includes/services/class-webhook-handler.php
$transient_key = 'buygo_webhook_' . $webhook_event_id;
if (get_transient($transient_key)) {
    return; // 重複事件，忽略
}
set_transient($transient_key, true, 5 * MINUTE_IN_SECONDS);
```

---

## 10. 安全性考量

### 10.1 Webhook 簽章驗證

**buygo-plus-one 已實作**:
```php
// includes/api/class-line-webhook-api.php
private function verify_signature($request) {
    $signature = $request->get_header('x-line-signature');
    $channel_secret = SettingsService::get('line_channel_secret', '');

    $body = $request->get_body();
    $hash = hash_hmac('sha256', $body, $channel_secret, true);
    $computed_sig = base64_encode($hash);

    return hash_equals($signature, $computed_sig); // 防止時序攻擊
}
```

### 10.2 權限檢查

**buygo-plus-one 需實作**:
```php
// includes/services/class-line-webhook-handler.php
private function can_upload_product($user_id) {
    if (!$user_id) {
        return false;
    }

    $user = get_user_by('ID', $user_id);
    if (!$user) {
        return false;
    }

    // 檢查角色
    if ($user->has_cap('administrator') ||
        $user->has_cap('buygo_admin') ||
        $user->has_cap('buygo_helper')) {
        return true;
    }

    // 檢查 wp_buygo_helpers 表
    global $wpdb;
    $table = $wpdb->prefix . 'buygo_helpers';
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$table} WHERE user_id = %d",
        $user_id
    ));

    return $exists > 0;
}
```

### 10.3 輸入驗證

**商品資訊解析需加入驗證**:
```php
private function parseProductInfo($text) {
    $lines = explode("\n", trim($text));

    if (count($lines) < 3) {
        return new \WP_Error('invalid_format', '商品資訊格式錯誤（需要至少 3 行）');
    }

    $title = sanitize_text_field($lines[0]);
    $price = intval($lines[1]);
    $description = sanitize_textarea_field(implode("\n", array_slice($lines, 2)));

    if (empty($title)) {
        return new \WP_Error('empty_title', '商品名稱不能為空');
    }

    if ($price <= 0) {
        return new \WP_Error('invalid_price', '商品價格必須大於 0');
    }

    return [
        'title' => $title,
        'price' => $price,
        'description' => $description,
    ];
}
```

---

## 11. 監控與維護

### 11.1 日誌監控

**buygo-line-notify Debug Tools**:
- Webhook 事件日誌（每日檢查）
- 訊息發送失敗率（每週檢查）
- 統計資料（每月檢查）

**buygo-plus-one DebugService**:
- 商品上架成功率
- 權限拒絕次數
- 錯誤類型分佈

### 11.2 定期清理

**buygo-line-notify 提供自動清理**:
```php
// 保留 30 天日誌
Logger::cleanOldLogs(30);
```

**建議排程**（WordPress Cron）:
```php
// 每週清理一次
add_action('buygo_weekly_cleanup', function() {
    \BuygoLineNotify\Services\Logger::cleanOldLogs(30);
});

if (!wp_next_scheduled('buygo_weekly_cleanup')) {
    wp_schedule_event(time(), 'weekly', 'buygo_weekly_cleanup');
}
```

### 11.3 效能監控

**關鍵指標**:
- Webhook 處理時間（< 3 秒）
- 圖片下載時間（< 10 秒）
- 產品建立時間（< 5 秒）
- LINE API 回應時間（< 2 秒）

**監控工具**:
- WordPress Debug Log
- buygo-line-notify Debug Tools
- New Relic / Application Performance Monitoring（如需要）

---

## 12. 未來擴充方向

### 12.1 批次商品上架

**功能**: 使用者一次上傳多張圖片，系統批次處理

**實作方式**:
- 監聽連續圖片訊息
- 收集所有圖片後統一詢問資訊
- 支援單一文字輸入多個商品資訊

### 12.2 商品編輯功能

**功能**: 透過 LINE 編輯已上架商品

**實作方式**:
- Postback 按鈕「編輯商品」
- 使用 Flex Message 展示商品資訊
- 支援個別欄位修改

### 12.3 語音輸入

**功能**: 支援語音訊息自動轉文字

**實作方式**:
- 使用 LINE 語音轉文字 API
- 整合商品資訊解析

### 12.4 圖片 OCR

**功能**: 自動識別商品包裝上的資訊

**實作方式**:
- 整合 Google Cloud Vision API
- 自動填充商品名稱、價格

---

## 13. 總結與檢查清單

### 13.1 整合檢查清單

#### buygo-line-notify（基礎設施層）
- [x] MessagingService 訊息發送 API
- [x] LineUserService 使用者綁定 API
- [x] ImageService 圖片下載 API
- [x] WebhookHandler Hook 觸發
- [x] Logger Debug 日誌
- [x] Admin Debug 後台頁面

#### buygo-plus-one（業務邏輯層）
- [ ] 監聽圖片訊息 Hook
- [ ] 權限檢查邏輯
- [ ] 圖片下載與狀態儲存
- [ ] 模板文字產生
- [ ] 監聽文字訊息 Hook
- [ ] 商品資訊解析
- [ ] FluentCart 產品建立
- [ ] 確認訊息發送
- [ ] 超時處理機制
- [ ] 錯誤處理與日誌

#### 測試與部署
- [ ] 單元測試撰寫
- [ ] 整合測試執行
- [ ] LINE Bot 設定
- [ ] Webhook URL 設定
- [ ] 簽章驗證測試
- [ ] 完整流程測試
- [ ] 正式環境部署

### 13.2 關鍵整合點總結

| 整合點 | buygo-line-notify 提供 | buygo-plus-one 使用 |
|--------|----------------------|---------------------|
| 訊息發送 | MessagingService Facade API | `BuygoLineNotify::messaging()->pushText()` |
| 使用者綁定 | LineUserService Facade API | `BuygoLineNotify::line_users()->getUserByLineUid()` |
| 圖片下載 | ImageService Facade API | `BuygoLineNotify::images()->downloadToMediaLibrary()` |
| Webhook 事件 | WordPress Hook | `add_action('buygo_line_notify/webhook_message', ...)` |
| Debug 日誌 | Logger + Admin Page | 後台 > LINE Notify > Debug Tools |

### 13.3 下一步行動

1. **立即執行**:
   - [ ] 在 buygo-plus-one 實作圖片訊息監聽
   - [ ] 實作商品資訊解析邏輯
   - [ ] 撰寫單元測試

2. **本週完成**:
   - [ ] 整合測試執行
   - [ ] 測試環境部署
   - [ ] Debug 工具驗證

3. **下週完成**:
   - [ ] 正式環境部署
   - [ ] 監控機制建立
   - [ ] 使用者測試

---

## 附錄

### A. 完整程式碼範例

請參閱:
- `buygo-line-notify/test-messaging-service.php` - MessagingService 測試範例
- `buygo-plus-one-dev/includes/services/class-line-order-notifier.php` - 訂單通知整合範例
- `buygo-plus-one-dev/includes/api/class-line-webhook-api.php` - Webhook API 整合範例

### B. API 參考

**buygo-line-notify Facade API**:
```php
\BuygoLineNotify\BuygoLineNotify::is_active()
\BuygoLineNotify\BuygoLineNotify::messaging()
\BuygoLineNotify\BuygoLineNotify::line_users()
\BuygoLineNotify\BuygoLineNotify::images()
```

**WordPress Hooks**:
```php
do_action('buygo_line_notify/webhook_event', $event, $event_type, $line_uid, $user_id)
do_action('buygo_line_notify/webhook_message', $event, $line_uid, $user_id)
do_action('buygo_line_notify/webhook_follow', $event, $line_uid, $user_id)
do_action('buygo_line_notify/webhook_unfollow', $event, $line_uid, $user_id)
do_action('buygo_line_notify/webhook_postback', $event, $line_uid, $user_id)
```

### C. 疑難排解

**問題 1**: Webhook 收不到事件

**解決**:
1. 檢查 Webhook URL 是否正確設定
2. 檢查 LINE Channel Secret 是否正確
3. 查看 buygo-line-notify Debug Tools > Webhook 日誌

**問題 2**: 訊息發送失敗

**解決**:
1. 檢查 Channel Access Token 是否正確
2. 檢查使用者是否已綁定 LINE UID
3. 查看 buygo-line-notify Debug Tools > 訊息日誌

**問題 3**: 圖片下載失敗

**解決**:
1. 檢查 Channel Access Token 是否有效
2. 檢查網路連線
3. 查看 buygo-line-notify Debug Tools > 訊息日誌

---

**文件版本**: 1.0
**建立日期**: 2026-01-29
**最後更新**: 2026-01-29
**負責人**: Claude Code
