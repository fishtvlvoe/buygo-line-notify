# External Integrations

## LINE Messaging API

**Purpose:** 發送 LINE 訊息通知

**Service Class:** `BuygoLineNotify\Services\LineMessagingService`
- Location: `includes/services/class-line-messaging-service.php`

### Authentication
- Method: Bearer Token
- Token: Channel Access Token (設定在建構函式)
- Header: `Authorization: Bearer {token}`

### Reply Message API
- **Endpoint:** `https://api.line.me/v2/bot/message/reply`
- **Method:** POST
- **用途:** 回覆用戶訊息（需要 reply token）
- **訊息格式:**
  - 文字訊息（字串）
  - LINE 訊息物件（陣列）
  - 多則訊息（陣列）

### Push Message API
- **Endpoint:** `https://api.line.me/v2/bot/message/push`
- **Method:** POST
- **用途:** 主動發送訊息給用戶（需要 LINE user ID）
- **訊息格式:** 同 Reply Message

### Error Handling
- WordPress HTTP errors (`is_wp_error()`)
- LINE API error responses (檢查 HTTP status code)
- Logger service 記錄錯誤

### Retry Mechanism
- **Service:** `BuygoLineNotify\Cron\RetryDispatcher`
- Location: `includes/cron/class-retry-dispatcher.php`
- Purpose: 失敗訊息重試機制（待實作細節）

## WordPress Core

**Database:** WordPress database via `wpdb`
- Options API (likely for settings storage)
- Custom tables (if needed)

**Admin Interface:**
- **Class:** `BuygoLineNotify\Admin\DemoPage`
- Location: `includes/admin/class-demo-page.php`
- Hook: Admin menu registration

**Cron/Scheduling:**
- WordPress Cron system
- Custom cron jobs via `RetryDispatcher`

## Image Upload Service

**Class:** `BuygoLineNotify\Services\ImageUploader`
- Location: `includes/services/class-image-uploader.php`
- Purpose: 處理圖片上傳（可能用於 LINE 圖片訊息）

## Logging

**Class:** `BuygoLineNotify\Services\Logger`
- Location: `includes/services/class-logger.php`
- Pattern: Singleton (`get_instance()`)
- Purpose: 統一的日誌記錄服務

## Settings

**Class:** `BuygoLineNotify\Services\SettingsService`
- Location: `includes/services/class-settings-service.php`
- Purpose: 外掛設定管理（LINE tokens, 設定選項等）

## Notes

### 從 buygo-plus-one-dev 遷移
此外掛的 LINE 訊息服務來自 `buygo-plus-one-dev` 專案，代表：
- 已經過實戰測試的代碼
- 可能需要整合回 FluentCart 或其他 buygo 生態系統

### 未來整合可能性
註解中提到要整合「之前的 LINE 上架到 FluentCart 的功能」，暗示：
- FluentCart integration 待實作
- 可能需要 webhook 接收器（LINE Bot webhook）
- 商品上架自動化功能
