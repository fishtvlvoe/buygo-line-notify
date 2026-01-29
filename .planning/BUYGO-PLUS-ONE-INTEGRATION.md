# Buygo Plus 1 整合計畫

**撰寫日期**: 2026-01-29
**目的**: 說明 buygo-line-notify 外掛需要完成哪些功能，才能提供完整的 API 讓 Buygo Plus 1 外掛整合使用

---

## 整合目標

**buygo-line-notify 的定位**：純粹的 LINE API 包裝器，提供基礎設施層的 API。

**Buygo Plus 1 的定位**：業務邏輯層，負責所有商業邏輯和訊息內容管理。

### 🎯 核心原則

1. **buygo-line-notify 不包含業務邏輯**
   - ❌ 不提供 hard-coded 訊息模板
   - ❌ 不監聽 FluentCart 事件
   - ❌ 不決定「什麼時候發送訊息」
   - ✅ 只提供「如何發送訊息」的基礎 API

2. **Buygo Plus 1 掌控所有業務邏輯**
   - ✅ 管理訊息模板（儲存在 Buygo Plus 1 的資料庫）
   - ✅ 監聽 FluentCart 事件（訂單建立、出貨）
   - ✅ 決定發送時機和內容
   - ✅ 呼叫 buygo-line-notify API 發送訊息

### 整合架構

```
FluentCart 事件 (訂單建立、出貨)
    ↓
Buygo Plus 1 監聽事件
    ↓
Buygo Plus 1 從自己的資料庫取得訊息模板
    ↓
Buygo Plus 1 組裝 Flex Message 內容（替換變數）
    ↓
Buygo Plus 1 呼叫 buygo-line-notify API
    ↓
buygo-line-notify 發送訊息到 LINE
```

---

## 目前狀態（2026-01-29）

### ✅ 已完成功能

#### 1. 基礎設施（Phase 1）
- [x] 資料庫表結構（`wp_buygo_line_users`）
- [x] 後台設定頁面（Channel Access Token, Channel Secret, etc.）
- [x] 設定加密儲存
- [x] 後台選單整合（偵測 buygo-plus-one-dev 存在）

#### 2. LINE Login 系統（Phase 8-14, v0.2 重構）
- [x] OAuth 流程（authorize → callback → token exchange）
- [x] 標準 WordPress URL 機制（`wp-login.php?loginSocial=buygo-line`）
- [x] Register Flow Page + Shortcode（`[buygo_line_login]`）
- [x] Profile Sync（名稱、Email、頭像）
- [x] Avatar 整合（`get_avatar_url` filter）
- [x] 前台整合（我的帳號頁面綁定/解除綁定）
- [x] 後台管理頁面（用戶列表 LINE 綁定欄位）

#### 3. 基礎 Webhook 系統（Phase 2）
- [x] REST API endpoint (`/wp-json/buygo-line-notify/v1/webhook`)
- [x] LINE Webhook 簽名驗證（`x-line-signature`）
- [x] Verify Event 處理（`replyToken: 000...000`）
- [x] 事件去重機制（`webhookEventId` + Transient）
- [x] 背景處理支援（FastCGI / WordPress Cron）

### ❌ 尚未完成功能（需要完成才能整合）

根據 buygo-plus-one-dev 分析和 REQUIREMENTS.md，以下功能是 Buygo Plus 1 整合的**必要條件**：

#### 1. LINE Message 接收與事件處理（擴充 Webhook 系統）

**需求來源**: WEBHOOK-06 + buygo-plus-one-dev 分析

**缺少的功能**:
- [ ] **事件路由系統**: 提供 Hooks 讓其他外掛（如 Buygo Plus 1）註冊事件處理器
- [ ] **Message Event 處理**: 接收 LINE 用戶訊息（文字、圖片、貼圖）
- [ ] **Follow/Unfollow Event 處理**: 用戶加入/封鎖官方帳號
- [ ] **Postback Event 處理**: Rich Menu 或 Flex Message 按鈕點擊
- [ ] **權限檢查機制**: 檢查 LINE 用戶是否為管理員/協作者

**參考實作**: `buygo-plus-one-dev/includes/services/class-line-webhook-handler.php`

**整合需求**:
```php
// Buygo Plus 1 需要能夠這樣註冊事件處理器
add_action('buygo_line_message_event', function($event, $line_uid, $user_id) {
    // 處理收到的訊息
}, 10, 3);

add_action('buygo_line_postback_event', function($event, $line_uid, $user_id) {
    // 處理 Rich Menu/Flex Message 按鈕點擊
}, 10, 3);
```

---

#### 2. LINE Message 發送系統（NOTIFY-01 到 NOTIFY-05）

**需求來源**: NOTIFY-01, NOTIFY-02, NOTIFY-04

**缺少的功能**:
- [ ] **Messaging API Client**: 封裝 LINE Messaging API 呼叫
- [ ] **文字訊息發送**: `pushTextMessage($line_uid, $text)`
- [ ] **Flex Message 發送**: `pushFlexMessage($line_uid, $flex_contents)`
- [ ] **圖片訊息發送**: `pushImageMessage($line_uid, $image_url)`
- [ ] **Reply Message 支援**: `replyMessage($reply_token, $messages)`
- [ ] **用戶綁定檢查**: 自動檢查用戶是否已綁定 LINE
- [ ] **錯誤處理與重試**: API 呼叫失敗時的重試機制
- [ ] **Facade 模式**: 提供簡化的 API 介面

**參考實作**:
- `buygo-plus-one-dev/includes/services/class-line-message-service.php`
- `buygo-plus-one-dev/includes/services/class-notification-templates.php`

**整合需求**:
```php
// Buygo Plus 1 需要能夠這樣發送訊息
use BuygoLineNotify\Services\MessagingService;

// 發送文字訊息
MessagingService::pushText($user_id, '您的訂單已建立');

// 發送 Flex Message
MessagingService::pushFlex($user_id, $flex_contents);

// 發送圖片
MessagingService::pushImage($user_id, $image_url);
```

---

#### 3. ~~通知模板系統~~（❌ 不實作，由 Buygo Plus 1 負責）

**原因**: 訊息模板屬於業務邏輯，應由 Buygo Plus 1 管理。

**Buygo Plus 1 負責**:
- 儲存訊息模板到自己的資料庫
- 管理模板變數和內容
- 組裝 Flex Message（替換變數）
- 透過 buygo-line-notify API 發送已組裝好的訊息

**buygo-line-notify 只需要**:
- 接收已組裝好的 Flex Message JSON
- 發送到 LINE（不需要知道內容是什麼）

**正確的整合方式**:
```php
// Buygo Plus 1 自己管理模板
$template = get_option('buygo_plus_one_line_templates')['order_created'];

// Buygo Plus 1 自己替換變數
$message = str_replace(
    ['{{order_id}}', '{{total}}', '{{items_count}}'],
    ['12345', '1,200', 3],
    $template
);

// 呼叫 buygo-line-notify API 發送
MessagingService::pushFlex($user_id, json_decode($message, true));
```

---

#### 4. FluentCart 產品上架功能（整合需求）

**需求來源**: buygo-plus-one-dev 分析（非 REQUIREMENTS.md，屬於業務邏輯）

**說明**: 這個功能**不應該**放在 buygo-line-notify，應該保留在 Buygo Plus 1，但 buygo-line-notify 需要提供必要的 API 支援。

**buygo-line-notify 需要提供**:
- [ ] **圖片上傳處理**: 接收 LINE 用戶上傳的圖片
- [ ] **圖片下載與儲存**: 下載圖片並儲存到 WordPress Media Library
- [ ] **Postback 資料傳遞**: 將 Rich Menu/Flex Message 按鈕點擊資料傳遞給 Buygo Plus 1

**Buygo Plus 1 負責**:
- 接收圖片上傳事件（透過 `buygo_line_message_event` hook）
- 解析商品資訊（價格、規格、名稱等）
- 呼叫 FluentCart API 建立商品

**參考實作**: `buygo-plus-one-dev/includes/services/class-fluentcart-service.php`

**整合需求**:
```php
// Buygo Plus 1 註冊圖片上傳處理器
add_action('buygo_line_image_message', function($event, $line_uid, $user_id, $image_id) {
    // 下載圖片
    $media_id = BuygoLineNotify\Services\ImageService::downloadToMediaLibrary($image_id);

    // 建立 FluentCart 商品（Buygo Plus 1 的邏輯）
    FluentCartService::createProduct([
        'image' => $media_id,
        // ... 其他商品資訊
    ]);
}, 10, 4);
```

---

#### 5. ~~FluentCart 訂單通知系統~~（❌ 不實作，由 Buygo Plus 1 負責）

**原因**: FluentCart 整合屬於業務邏輯，應由 Buygo Plus 1 負責。

**Buygo Plus 1 負責**:
- 監聽 `fluent_cart/order_created` 和 `fluent_cart/shipping_status_changed_to_shipped` 事件
- 決定發送時機（立即或延遲）
- 管理延遲通知與重試機制（60s, 120s, 300s）
- 使用 order meta 避免重複發送
- 從自己的資料庫取得訊息模板
- 組裝訊息內容（訂單編號、金額、商品列表等）
- 呼叫 buygo-line-notify API 發送訊息

**buygo-line-notify 只需要**:
- 提供 `MessagingService::pushText()` 和 `MessagingService::pushFlex()` API
- 檢查用戶是否已綁定 LINE
- 發送訊息到 LINE

**正確的整合方式**:
```php
// Buygo Plus 1 監聽 FluentCart 事件
add_action('fluent_cart/order_created', function($order) {
    // 檢查買家是否綁定 LINE
    if (!MessagingService::isUserLinked($order->user_id)) {
        return;
    }

    // 排程延遲通知（Buygo Plus 1 的邏輯）
    wp_schedule_single_event(time() + 60, 'buygo_plus_send_order_notification', [
        'order_id' => $order->id,
        'event' => 'order_created'
    ]);
}, 10, 1);

// Buygo Plus 1 處理延遲通知
add_action('buygo_plus_send_order_notification', function($order_id, $event) {
    // 取得訂單資料
    $order = /* ... */;

    // 從 Buygo Plus 1 資料庫取得模板
    $template = /* ... */;

    // 組裝訊息內容
    $message = /* ... */;

    // 呼叫 buygo-line-notify API 發送
    MessagingService::pushFlex($order->user_id, $message);
}, 10, 2);
```

---

#### 6. 資料庫擴充（支援訊息與通知記錄）

**需求來源**: buygo-plus-one-dev 分析

**缺少的資料表**:
- [ ] **`wp_buygo_webhook_logs`**: 記錄 Webhook 事件（event_type, event_data, line_user_id）
- [ ] **`wp_buygo_notification_logs`**: 記錄發送的通知（user_id, message_type, status, sent_at）
- [ ] **`wp_buygo_debug_logs`**: Debug 日誌（level, module, message, data）

**用途**:
- Webhook logs: 偵錯 LINE 事件處理流程
- Notification logs: 追蹤通知發送狀態，支援重試機制
- Debug logs: 開發階段偵錯用

---

## 功能實作優先順序

根據整合需求，建議以下實作順序：

### Phase A: LINE Message 發送系統（核心功能）
**優先度**: 🔴 最高（Buygo Plus 1 整合的基礎）

1. **Messaging API Client**
   - 封裝 LINE Messaging API 呼叫（Push Message, Reply Message）
   - 錯誤處理與重試機制
   - 速率限制處理

2. **基礎訊息發送**
   - 文字訊息發送
   - 圖片訊息發送
   - Flex Message 發送

3. **Facade 模式**
   - 提供簡化的 API 介面（`MessagingService::pushText()`, `pushFlex()`, `pushImage()`）
   - 自動檢查用戶綁定狀態

**完成標準**: Buygo Plus 1 可以發送 LINE 訊息給已綁定用戶

---

### Phase B: Webhook 事件路由系統
**優先度**: 🔴 最高（Buygo Plus 1 需要監聽事件）

1. **事件路由機制**
   - 提供 WordPress Hooks 讓外掛註冊處理器
   - `buygo_line_message_event`
   - `buygo_line_image_message`
   - `buygo_line_postback_event`
   - `buygo_line_follow_event`
   - `buygo_line_unfollow_event`

2. **權限檢查機制**
   - 檢查 LINE 用戶是否為 WordPress 管理員
   - 檢查是否有 `buygo_admin` 或 `buygo_helper` 角色

3. **圖片處理**
   - 下載 LINE 圖片到 WordPress Media Library
   - 提供 `ImageService::downloadToMediaLibrary($message_id)` API

**完成標準**: Buygo Plus 1 可以接收並處理 LINE 用戶訊息

---

### ~~Phase C: 通知模板系統~~（❌ 取消，由 Buygo Plus 1 負責）

**說明**: 訊息模板屬於業務邏輯，應由 Buygo Plus 1 管理。buygo-line-notify 不實作此功能。

---

### ~~Phase D: FluentCart 訂單通知整合~~（❌ 取消，由 Buygo Plus 1 負責）

**說明**: FluentCart 整合屬於業務邏輯，應由 Buygo Plus 1 負責。buygo-line-notify 不實作此功能。

---

### Phase C: 資料庫擴充與 Debug 工具
**優先度**: 🟢 低（開發輔助）

1. **建立資料表**
   - `wp_buygo_webhook_logs`（記錄 Webhook 事件）
   - `wp_buygo_message_logs`（記錄發送的訊息，不包含業務邏輯）

2. **後台 Debug 頁面**
   - 查看 Webhook 事件記錄（event_type, line_uid, received_at）
   - 查看訊息發送記錄（user_id, message_type, status, sent_at）
   - **不記錄訊息內容**（內容由 Buygo Plus 1 管理）

**完成標準**: 可以在後台追蹤 LINE 事件和訊息發送狀態（不包含業務邏輯資料）

---

## 整合後的架構圖

```
┌─────────────────────────────────────────────────────────────┐
│                    Buygo Plus 1 (Vue 3)                      │
│  【業務邏輯層】                                                │
│  - 後台管理介面                                                │
│  - 訊息模板管理（儲存、編輯、變數替換）                          │
│  - FluentCart 整合（監聽訂單事件、延遲通知、重試機制）            │
│  - 商品上架邏輯（接收 LINE 圖片、建立 FluentCart 商品）           │
│  - 決定「什麼時候發送什麼訊息給誰」                              │
└─────────────────────────────────────────────────────────────┘
                           ↓ 呼叫 API
┌─────────────────────────────────────────────────────────────┐
│                  buygo-line-notify 外掛                       │
│  【基礎設施層 - 純粹的 LINE API 包裝器】                       │
│                                                              │
│  [Webhook 系統]                                               │
│  - 接收 LINE 事件 (message, postback, follow, unfollow)       │
│  - 事件路由 (buygo_line_*_event hooks)                        │
│  - 圖片下載與儲存（不決定如何使用圖片）                          │
│                                                              │
│  [Messaging 系統]                                             │
│  - MessagingService::pushText($user_id, $text)               │
│  - MessagingService::pushFlex($user_id, $flex_json)          │
│  - MessagingService::pushImage($user_id, $image_url)         │
│  - 檢查用戶綁定狀態                                            │
│  - 錯誤處理與重試                                              │
│                                                              │
│  [LINE Login 系統] ✅ 已完成                                   │
│  - OAuth 流程                                                 │
│  - Profile Sync                                              │
│  - Avatar 整合                                                │
│                                                              │
│  ❌ 不包含：模板管理、FluentCart 監聽、業務邏輯                 │
└─────────────────────────────────────────────────────────────┘
                           ↓ API 呼叫
┌─────────────────────────────────────────────────────────────┐
│                    LINE Messaging API                        │
│  - Push Message                                              │
│  - Reply Message                                             │
│  - Get Content (下載圖片)                                      │
└─────────────────────────────────────────────────────────────┘
```

---

## 整合檢查清單

### buygo-line-notify 開發階段（基礎設施層）
- [ ] Phase A: LINE Message 發送系統完成
- [ ] Phase B: Webhook 事件路由系統完成
- [ ] Phase C: 資料庫擴充與 Debug 工具完成

### buygo-line-notify 測試階段
- [ ] 測試文字訊息發送（接收已組裝好的內容）
- [ ] 測試 Flex Message 發送（接收已組裝好的 JSON）
- [ ] 測試圖片訊息發送
- [ ] 測試 Webhook 事件接收（message, postback, follow, unfollow）
- [ ] 測試圖片下載與儲存
- [ ] 測試事件路由（hooks 正確觸發）

### buygo-line-notify 文件階段
- [ ] API 文件（MessagingService 方法、Hooks 列表）
- [ ] 整合指南（Buygo Plus 1 如何使用 API）
- [ ] Webhook 事件說明

### Buygo Plus 1 開發階段（由 Buygo Plus 1 團隊負責）
- [ ] 訊息模板管理（CRUD）
- [ ] FluentCart 事件監聽
- [ ] 延遲通知與重試機制
- [ ] 模板變數替換
- [ ] 商品上架邏輯

---

## 未來考慮（v2）

以下功能暫時不需要，但未來可能需要：

1. **Rich Menu 管理**: 透過後台建立和管理 Rich Menu
2. **LINE Pay 整合**: 支援 LINE Pay 付款流程
3. **Flex Message 視覺化編輯器**: 拖拉式編輯器
4. **進階分析**: 訊息發送統計、用戶互動分析

---

## 結論

**目前 buygo-line-notify 已完成**:
- ✅ LINE Login 系統（OAuth、Profile Sync、Avatar 整合）
- ✅ 基礎 Webhook 系統（endpoint、簽名驗證、去重、背景處理）
- ✅ 後台管理頁面（設定、用戶列表）

**需要完成才能整合 Buygo Plus 1**:
- ❌ LINE Message 發送系統（Phase A）- 基礎設施層
- ❌ Webhook 事件路由系統（Phase B）- 基礎設施層
- ❌ 資料庫擴充與 Debug 工具（Phase C）- 開發輔助

**建議實作順序**: Phase A → Phase B → Phase C

完成 Phase A 和 Phase B 後，Buygo Plus 1 就可以開始整合（發送訊息和接收事件）。Phase C 可以後續再補完。

**重要提醒**:
- ❌ buygo-line-notify 不實作訊息模板系統（由 Buygo Plus 1 管理）
- ❌ buygo-line-notify 不監聽 FluentCart 事件（由 Buygo Plus 1 監聽）
- ❌ buygo-line-notify 不包含任何 hard-coded 訊息內容
- ✅ buygo-line-notify 只是純粹的 LINE API 包裝器

---

*整合計畫撰寫完成: 2026-01-29*
