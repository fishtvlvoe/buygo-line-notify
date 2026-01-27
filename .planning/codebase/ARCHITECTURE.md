# Architecture

## Pattern

**WordPress Plugin Architecture** with **Service Layer Pattern**

## Layers

### 1. Entry Point Layer
**File:** `buygo-line-notify.php`
- 定義常數（VERSION, DIR, URL）
- 載入 Plugin bootstrap
- 啟動外掛：`Plugin::instance()->init()`

### 2. Bootstrap Layer
**Class:** `BuygoLineNotify\Plugin`
- Location: `includes/class-plugin.php`
- Pattern: Singleton
- Responsibilities:
  - 載入所有依賴（services, admin, cron）
  - 註冊 WordPress hooks
  - 條件式載入（admin 功能僅在後台載入）

### 3. Service Layer
**Location:** `includes/services/`

核心服務類別：
- `LineMessagingService` - LINE API 通訊
- `Logger` - 日誌記錄（Singleton）
- `SettingsService` - 設定管理
- `ImageUploader` - 圖片處理

服務特性：
- 單一職責原則
- 可依賴注入（如 LineMessagingService 接收 token）
- 可獨立測試（純 PHP，不依賴 WordPress）

### 4. Admin Layer
**Location:** `includes/admin/`
- `DemoPage` - 示範頁面（僅在 WordPress admin 載入）
- Hook registration pattern

### 5. Cron/Background Layer
**Location:** `includes/cron/`
- `RetryDispatcher` - 重試機制
- WordPress Cron integration

### 6. Facade Layer
**Class:** `BuygoLineNotify\BuygoLineNotify`
- Location: `includes/class-buygo-line-notify.php`
- Purpose: 提供簡化的 API 給其他外掛使用
- 隱藏內部複雜性

## Data Flow

### Outbound (發送 LINE 訊息)
```
User/Other Plugin
    ↓
BuygoLineNotify Facade (optional)
    ↓
LineMessagingService
    ↓
LINE Messaging API
    ↓
Logger (記錄結果)
```

### Settings Flow
```
Admin UI (DemoPage)
    ↓
SettingsService
    ↓
WordPress Options API
```

### Retry Flow (失敗處理)
```
LineMessagingService (失敗)
    ↓
RetryDispatcher (排程重試)
    ↓
WordPress Cron
    ↓
重新呼叫 LineMessagingService
```

## Abstractions

### Service Abstraction
服務層完全抽象化外部依賴：
- LINE API 細節封裝在 LineMessagingService
- 日誌實作細節封裝在 Logger
- WordPress 設定細節封裝在 SettingsService

### Testing Abstraction
單元測試不依賴 WordPress：
- `tests/bootstrap-unit.php` 提供純 PHP 環境
- 服務類別可獨立測試
- Mock WordPress functions (if needed)

## Entry Points

### WordPress Hooks
1. **`plugins_loaded` (implicit)**: Plugin class auto-initializes
2. **`init`**: `Plugin::onInit()` - 註冊 Cron hooks 和 Admin hooks
3. **`admin_init` (via DemoPage)**: Admin page registration

### Public API (for other plugins)
```php
// Via Facade
BuygoLineNotify\BuygoLineNotify::some_method();

// Or direct service usage
$line_service = new LineMessagingService($token);
$line_service->send_reply($reply_token, $message);
```

## Plugin Loading Sequence

1. `buygo-line-notify.php` loaded
2. Constants defined
3. `Plugin::instance()` creates singleton
4. `Plugin::init()` calls `loadDependencies()`
5. All service/admin/cron classes included
6. `add_action('init', ...)` registered
7. On WordPress `init` hook:
   - `RetryDispatcher::register_hooks()`
   - If admin: `DemoPage::register_hooks()`

## Notes

### 模組化設計
- 清晰的層次分離
- 服務可替換（如更換不同的 Logger）
- 易於擴展（新增服務只需在 `loadDependencies()` 加載）

### 從 buygo-plus-one-dev 繼承
架構模式與 buygo-plus-one-dev 一致：
- 相同的目錄結構
- 相同的服務層模式
- 相同的測試方法
