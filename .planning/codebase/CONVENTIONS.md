# Code Conventions

## Language & Style

**PHP Version:** 8.0+
- Modern PHP features encouraged
- Type declarations where appropriate

## Naming Conventions

### Classes
- **PascalCase**
  - Good: `LineMessagingService`, `RetryDispatcher`
  - Bad: `line_messaging_service`, `retryDispatcher`

### Methods & Functions
- **camelCase**
  - Good: `sendReply()`, `getInstance()`, `registerHooks()`
  - Bad: `send_reply()`, `get_instance()`, `register_hooks()`

### Properties
- **camelCase** (PHP class properties)
  - Good: `$channelAccessToken`, `$logger`
  - Bad: `$channel_access_token`, `$Logger`

### Constants
- **SCREAMING_SNAKE_CASE**
  - Plugin-level: `BuygoLineNotify_PLUGIN_VERSION`
  - Class-level: `SOME_CONSTANT`

### Files
- **kebab-case** with `class-` prefix
  - Good: `class-line-messaging-service.php`, `class-demo-page.php`
  - Follows WordPress coding standards

## Namespace Organization

```php
namespace BuygoLineNotify;              // 主命名空間
namespace BuygoLineNotify\Services;     // 服務層
namespace BuygoLineNotify\Admin;        // 管理層
namespace BuygoLineNotify\Cron;         // Cron 層
namespace BuygoLineNotify\Tests;        // 測試層
```

## Class Structure Pattern

### Service Classes

```php
<?php

namespace BuygoLineNotify\Services;

if (!defined('ABSPATH')) {
    exit; // 防止直接訪問
}

/**
 * ServiceName
 *
 * 簡短描述服務用途
 */
class ServiceName
{
    /**
     * 屬性說明
     *
     * @var type
     */
    private $property;

    /**
     * Constructor
     *
     * @param type $param 參數說明
     */
    public function __construct($param)
    {
        $this->property = $param;
    }

    /**
     * 方法說明
     *
     * @param type $param 參數說明
     * @return type 返回值說明
     */
    public function methodName($param)
    {
        // 實作
    }
}
```

### Singleton Pattern

```php
final class ClassName
{
    private static ?self $_instance = null;

    public static function instance(): self
    {
        if (self::$_instance === null) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    private function __construct()
    {
        // 防止外部實例化
    }
}
```

用於：
- `Plugin` class
- `Logger` class

## Error Handling

### WordPress Errors
```php
if (is_wp_error($response)) {
    $this->log('error', [
        'message' => $response->get_error_message(),
        'action' => 'method_name',
    ]);
    return new \WP_Error('error_code', 'Error message');
}
```

### Early Returns
```php
if (empty($required_param)) {
    return new \WP_Error('missing_param', 'Parameter is required');
}
```

### Logging Pattern
```php
$this->logger->log('level', [
    'message' => 'What happened',
    'context' => 'Additional data',
    'action' => 'method_name',
]);
```

## Documentation

### PHPDoc Required For
- All class properties
- All public methods
- All parameters with type and description
- All return values

### PHPDoc Format
```php
/**
 * 簡短描述（單行）
 *
 * 詳細描述（選填，多行）
 *
 * @param string $param1 參數1說明
 * @param array $param2 參數2說明
 * @return bool|\WP_Error 成功返回 true，失敗返回 WP_Error
 */
```

## Code Organization

### Method Order (within a class)
1. Static methods (如 `instance()`)
2. Constructor
3. Public methods
4. Protected methods
5. Private methods

### Dependency Loading
在 `Plugin::loadDependencies()` 中集中管理：
```php
private function loadDependencies(): void
{
    // 服務層
    include_once BuygoLineNotify_PLUGIN_DIR . 'includes/services/...';

    // Facade
    include_once BuygoLineNotify_PLUGIN_DIR . 'includes/class-...';

    // 其他
    include_once BuygoLineNotify_PLUGIN_DIR . 'includes/cron/...';

    // 條件式載入
    if (\is_admin()) {
        include_once BuygoLineNotify_PLUGIN_DIR . 'includes/admin/...';
    }
}
```

## WordPress Integration

### Hook Registration
```php
public static function register_hooks(): void
{
    \add_action('hook_name', [self::class, 'callback_method']);
}
```

使用靜態方法和 `[self::class, 'method']` 語法。

### WordPress Functions
總是使用命名空間前綴 `\` 調用 WordPress 函數：
```php
\add_action('init', ...);
\is_admin();
\wp_remote_post(...);
\wp_json_encode(...);
```

### ABSPATH Check
所有檔案（除了 `buygo-line-notify.php`）都應有：
```php
if (!defined('ABSPATH')) {
    exit;
}
```

## Testing Conventions

### Test File Naming
- 對應類別名稱 + `Test.php`
  - `LineMessagingService` → `LineMessagingServiceTest.php`

### Test Method Naming
```php
public function testMethodDoesExpectedBehavior(): void
{
    // Arrange
    $service = new ServiceClass();

    // Act
    $result = $service->method();

    // Assert
    $this->assertEquals($expected, $result);
}
```

### Test Structure
- Arrange-Act-Assert pattern
- One assertion per test (preferred)
- Clear test names

## Comments

### When to Comment
- 複雜邏輯需要解釋
- 非顯而易見的決策
- 臨時解決方案（TODO, FIXME）

### When NOT to Comment
- 說明顯而易見的代碼
  - Bad: `// Set logger` 在 `$this->logger = $logger;` 之前

### Comment Style
```php
// 單行註解

/*
 * 多行註解
 * 用於段落說明
 */

/**
 * PHPDoc 註解
 * 用於類別、方法、屬性
 */
```

## Constants vs Config

### Plugin Constants (defined in main file)
```php
define('BuygoLineNotify_PLUGIN_VERSION', '0.1.0');
define('BuygoLineNotify_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BuygoLineNotify_PLUGIN_URL', plugin_dir_url(__FILE__));
```

### Runtime Config (via SettingsService)
- LINE Channel Access Token
- 其他可變設定

## Notes

### 混合規範
此 codebase 混合了：
- **WordPress Coding Standards** (檔案命名、hook 使用)
- **PSR-4** (命名空間)
- **Modern PHP** (型別宣告、camelCase 方法名)

這是刻意設計，以兼顧：
- WordPress 生態系統兼容性
- 現代 PHP 最佳實踐
- 可測試性
