# Directory Structure

```
buygo-line-notify/
├── buygo-line-notify.php        # 外掛主檔案（entry point）
├── uninstall.php                 # 外掛解除安裝腳本
├── composer.json                 # PHP 依賴管理
├── composer.lock                 # 鎖定版本
├── phpunit-unit.xml             # PHPUnit 測試設定
├── README.md                     # 外掛說明
├── TESTING.md                    # 測試指南
│
├── includes/                     # 核心程式碼
│   ├── index.php                # 防止目錄列表
│   ├── class-plugin.php         # Plugin bootstrap (Singleton)
│   ├── class-buygo-line-notify.php  # Facade API
│   │
│   ├── services/                # 服務層
│   │   ├── index.php
│   │   ├── class-logger.php
│   │   ├── class-settings-service.php
│   │   ├── class-image-uploader.php
│   │   └── class-line-messaging-service.php
│   │
│   ├── admin/                   # WordPress 後台功能
│   │   ├── index.php
│   │   └── class-demo-page.php
│   │
│   └── cron/                    # 排程/背景任務
│       ├── index.php
│       └── class-retry-dispatcher.php
│
├── tests/                       # 測試檔案
│   ├── bootstrap-unit.php       # 單元測試啟動檔
│   │
│   └── Unit/                    # 單元測試（不依賴 WordPress）
│       ├── Services/
│       │   └── SampleServiceTest.php
│       │
│       └── Facade/
│           └── BuygoLineNotifyTest.php
│
└── vendor/                      # Composer 依賴（gitignore）
```

## Key Locations

### Entry Points
- **主檔案:** `buygo-line-notify.php`
  - 定義常數、載入 Plugin、啟動外掛

### Core Classes
- **Plugin Bootstrap:** `includes/class-plugin.php`
  - Singleton pattern
  - 載入所有依賴
  - 註冊 hooks

- **Facade:** `includes/class-buygo-line-notify.php`
  - 提供簡化 API 給其他外掛

### Services (Business Logic)
- **LINE 訊息:** `includes/services/class-line-messaging-service.php`
  - 發送 reply/push 訊息
  - 錯誤處理

- **Logger:** `includes/services/class-logger.php`
  - Singleton
  - 統一日誌

- **Settings:** `includes/services/class-settings-service.php`
  - 外掛設定管理

- **Image Upload:** `includes/services/class-image-uploader.php`
  - 圖片處理（可能用於 LINE 圖片訊息）

### Admin
- **Demo Page:** `includes/admin/class-demo-page.php`
  - 示範頁面
  - 僅在 WordPress admin 載入

### Cron
- **Retry Dispatcher:** `includes/cron/class-retry-dispatcher.php`
  - 失敗訊息重試機制

### Tests
- **Unit Tests:** `tests/Unit/`
  - 純 PHP 測試，不依賴 WordPress
  - 每個服務都應有對應測試

## Naming Conventions

### Files
- **Classes:** `class-{name}.php` (WordPress 慣例)
  - 例：`class-plugin.php`, `class-logger.php`
- **Tests:** `{ClassName}Test.php` (PHPUnit 慣例)
  - 例：`SampleServiceTest.php`

### Classes
- **Namespace:** `BuygoLineNotify\` (主)
  - Services: `BuygoLineNotify\Services\`
  - Admin: `BuygoLineNotify\Admin\`
  - Cron: `BuygoLineNotify\Cron\`
  - Tests: `BuygoLineNotify\Tests\`

- **Class Names:** PascalCase
  - 例：`Plugin`, `LineMessagingService`, `DemoPage`

### Constants
- **Plugin-level:** `BuygoLineNotify_{NAME}`
  - 例：`BuygoLineNotify_PLUGIN_VERSION`
  - 例：`BuygoLineNotify_PLUGIN_DIR`

## Directory Protection

每個 includes 子目錄都有 `index.php`（空檔案）防止直接訪問。

## Autoloading Strategy

**Composer Autoload:**
- PSR-4: `BuygoLineNotify\` → `includes/`
- Classmap: `includes/` (所有類別檔案)

**Manual Includes:**
Plugin class 使用 `include_once` 載入服務（在 `loadDependencies()`）：
- 服務層
- Admin 層（條件式）
- Cron 層
- Facade

這確保類別載入順序和條件式載入（如 admin 功能）。

## Testing Structure

```
tests/
└── Unit/               # 單元測試（純 PHP）
    ├── Services/       # 測試服務層
    ├── Facade/         # 測試 Facade API
    └── Cron/           # (未來) 測試 Cron
```

測試對應原始碼結構，易於定位。

## Notes

### wordpress-plugin-kit 生成
此結構由 `wordpress-plugin-kit` 自動生成，遵循：
- WordPress Coding Standards
- 測試優先設計
- 清晰的層次分離

### 與 buygo-plus-one-dev 一致
目錄結構與 buygo-plus-one-dev 外掛一致，便於：
- 代碼遷移
- 團隊協作
- 維護性
