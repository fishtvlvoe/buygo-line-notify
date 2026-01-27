# Technical Concerns

## 🟡 未完成功能

### 1. RetryDispatcher 實作不完整
**Location:** `includes/cron/class-retry-dispatcher.php`

**問題:**
- Class 已註冊 hooks 但實際重試邏輯可能未實作
- 沒有看到重試排程、失敗訊息儲存機制

**影響:**
- LINE 訊息發送失敗時無法自動重試
- 可能導致訊息遺失

**建議:**
- 實作失敗訊息佇列（可能使用 WordPress transients 或 custom table）
- 實作 WordPress Cron job 來處理重試
- 定義重試策略（次數、間隔、指數退避等）

### 2. ImageUploader 用途不明確
**Location:** `includes/services/class-image-uploader.php`

**問題:**
- 服務已載入但未看到使用場景
- LINE 圖片訊息是否需要先上傳圖片？

**建議:**
- 確認是否需要此服務
- 如果不需要，可以移除以減少複雜度
- 如果需要，補充使用文件和範例

### 3. DemoPage 是臨時代碼
**Location:** `includes/admin/class-demo-page.php`

**問題:**
- 檔案名稱暗示這是示範用
- 可能需要替換為實際的設定頁面

**建議:**
- 重命名為 `class-settings-page.php`
- 實作真正的 LINE Token 設定介面
- 提供測試訊息發送功能

## 🟡 配置管理

### 4. LINE Token 儲存方式不明確
**Current:**
- `LineMessagingService` 需要在建構函式傳入 token
- `SettingsService` 存在但未看到具體實作

**問題:**
- Token 如何儲存？（WordPress options? 加密?）
- 如何從設定頁面傳到服務層？

**建議:**
- 定義明確的設定儲存結構
- 考慮 token 加密儲存
- 實作 `SettingsService::get_line_token()` 方法

### 5. 缺少環境變數支援
**問題:**
- 開發/正式環境可能需要不同的 token
- 目前只能從 WordPress 設定讀取

**建議:**
- 支援環境變數（如 `LINE_CHANNEL_ACCESS_TOKEN`）
- 環境變數優先於資料庫設定
- 在 `.env.example` 提供範本

## 🟢 架構優點（需保持）

### Logger Singleton
- 統一的日誌記錄機制
- 避免多個 logger 實例

### Service Layer 分離
- 商業邏輯與 WordPress 解耦
- 可單獨測試

### Facade Pattern
- 簡化外部使用
- 隱藏內部複雜性

## 🔴 潛在問題

### 6. 錯誤處理不夠完整
**Current:**
```php
if (is_wp_error($response)) {
    $this->log('error', ...);
    return new \WP_Error(...);
}
```

**問題:**
- 只檢查 WordPress HTTP errors
- 未檢查 LINE API error responses (如 token 過期、rate limit)

**建議:**
```php
// 檢查 HTTP status code
$code = wp_remote_retrieve_response_code($response);
if ($code !== 200) {
    $body = wp_remote_retrieve_body($response);
    $error = json_decode($body, true);
    // 處理不同錯誤類型
}
```

### 7. 缺少 Rate Limiting
**問題:**
- LINE Messaging API 有 rate limit
- 大量訊息可能被限流

**建議:**
- 實作訊息佇列
- 加入 rate limiting 機制
- 使用 WordPress Cron 分批發送

### 8. 沒有 Webhook 接收器
**需求（從專案目標）:**
- 「整合之前的 LINE 上架到 FluentCart 的功能」
- 可能需要接收 LINE Bot webhook

**問題:**
- 目前只有發送訊息功能
- 沒有接收用戶訊息的 webhook endpoint

**建議:**
- 實作 REST API endpoint: `/wp-json/buygo-line-notify/v1/webhook`
- 驗證 LINE signature
- 處理不同事件類型（message, follow, unfollow 等）

## 🟡 測試覆蓋

### 9. 測試覆蓋不足
**Current:**
- `SampleServiceTest` - 示範測試
- `BuygoLineNotifyTest` - Facade 測試

**Missing:**
- `LineMessagingServiceTest` - 核心服務測試
- `LoggerTest`
- `RetryDispatcherTest`

**建議:**
- 優先測試 `LineMessagingService`（核心功能）
- Mock `wp_remote_post` 和 LINE API responses
- 測試錯誤處理路徑

### 10. 缺少 Integration Tests
**問題:**
- 只有 unit tests
- 未測試與 WordPress 的整合

**建議:**
- 加入 WordPress test suite (可選)
- 測試 hooks 是否正確註冊
- 測試 admin UI 顯示

## 🟢 安全性（Good）

### ✅ ABSPATH Check
所有檔案都有防直接訪問：
```php
if (!defined('ABSPATH')) {
    exit;
}
```

### ✅ Namespace
使用命名空間避免衝突：
```php
namespace BuygoLineNotify\Services;
```

### ⚠️ 需加強
- LINE Token 應加密儲存
- Webhook signature 驗證（未來實作時）
- Input sanitization（如果接收用戶輸入）

## 🔵 效能

### 11. 同步發送可能阻塞
**Current:**
- `LineMessagingService` 同步呼叫 LINE API
- Timeout 設定 30 秒

**問題:**
- 大量訊息會阻塞 WordPress request
- 失敗時用戶需等待 30 秒

**建議:**
- 使用非同步發送（WordPress Background Processing）
- 或至少降低 timeout（如 10 秒）

## 🟡 文件

### 12. 缺少使用文件
**Current:**
- `README.md` - 基本說明
- `TESTING.md` - 測試指南

**Missing:**
- 如何設定 LINE Channel Access Token
- 如何發送訊息（程式碼範例）
- 如何整合到其他外掛

**建議:**
- 撰寫 `USAGE.md`
- 包含完整的設定步驟
- 提供程式碼範例

## 🔴 遷移自 buygo-plus-one-dev

### 13. 依賴檢查
**問題:**
- 代碼從 `buygo-plus-one-dev` 遷移而來
- 可能有對舊專案的依賴（如特定 WordPress functions 或 plugins）

**建議:**
- 檢查是否依賴 FluentCart 外掛
- 確認所有功能在獨立安裝時仍可運作
- 補充缺少的依賴檢查

## 優先級建議

### 🔥 High Priority
1. 實作 `LineMessagingServiceTest`
2. 完成 `RetryDispatcher` 邏輯
3. 實作 LINE Token 設定介面
4. 補充錯誤處理（LINE API errors）

### 🟡 Medium Priority
5. 實作 Webhook 接收器（如果需要雙向通訊）
6. 加入 Rate Limiting
7. 撰寫使用文件

### 🟢 Low Priority
8. 環境變數支援
9. Integration tests
10. 非同步訊息發送

## Notes

### 整體評估
- ✅ 架構清晰、易於擴展
- ✅ 測試基礎設施完整
- ⚠️ 核心功能未完成（重試機制）
- ⚠️ 缺少雙向通訊（webhook）
- ⚠️ 測試覆蓋不足

### 可用性
目前狀態：**可用於單向訊息發送**（reply/push）

需要完成：
- 設定介面
- 錯誤處理增強
- 測試補充
- Webhook（如果需要）

然後才適合用於生產環境。
