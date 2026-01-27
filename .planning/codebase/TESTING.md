# Testing Practices

## Framework

**PHPUnit 9.6** with **Yoast PHPUnit Polyfills 2.0**

## Test Structure

```
tests/
├── bootstrap-unit.php           # 單元測試啟動檔
└── Unit/                        # 單元測試（不依賴 WordPress）
    ├── Services/
    │   └── SampleServiceTest.php
    └── Facade/
        └── BuygoLineNotifyTest.php
```

## Test Types

### Unit Tests (當前實作)
- **Location:** `tests/Unit/`
- **特性:**
  - 純 PHP 測試
  - 不依賴 WordPress
  - 不依賴資料庫
  - 快速執行

- **測試對象:**
  - 服務層邏輯
  - Facade API
  - 任何不需要 WordPress 的類別

### Integration Tests (未實作)
未來可能需要測試：
- WordPress hooks 整合
- Database operations
- Admin UI

## Configuration

**PHPUnit Config:** `phpunit-unit.xml`

```xml
<phpunit>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
    </testsuites>
    <bootstrap>tests/bootstrap-unit.php</bootstrap>
</phpunit>
```

## Running Tests

### Composer Scripts
```bash
# 執行所有測試
composer test

# 詳細輸出
composer test:unit

# 覆蓋率報告（輸出至 coverage/）
composer test:coverage
```

### Direct PHPUnit
```bash
./vendor/bin/phpunit -c phpunit-unit.xml
```

## Writing Tests

### Test File Template

```php
<?php

namespace BuygoLineNotify\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use BuygoLineNotify\Services\ServiceName;

class ServiceNameTest extends TestCase
{
    public function testMethodDoesExpectedBehavior(): void
    {
        // Arrange
        $service = new ServiceName();

        // Act
        $result = $service->method();

        // Assert
        $this->assertEquals($expected, $result);
    }
}
```

### Test Naming
- **Class:** `{ClassName}Test`
- **Method:** `test{MethodName}{Behavior}`
  - 例：`testSendReplyReturnsTrue`
  - 例：`testSendReplyReturnsErrorWhenTokenMissing`

### Arrange-Act-Assert Pattern
```php
public function testExample(): void
{
    // Arrange: 準備測試資料
    $input = 'test data';
    $expected = 'expected result';

    // Act: 執行被測試的方法
    $actual = $this->subject->method($input);

    // Assert: 驗證結果
    $this->assertEquals($expected, $actual);
}
```

## Mocking

### WordPress Functions (if needed)
Since unit tests don't load WordPress, you might need to mock WordPress functions:

```php
// Option 1: Define stub functions in bootstrap-unit.php
function wp_remote_post($url, $args) {
    return ['response' => ['code' => 200]];
}

// Option 2: Use a mocking library (未來考慮)
```

### External Services
```php
public function testLineApiFailureHandling(): void
{
    // Mock wp_remote_post to return error
    // Test error handling logic
}
```

## Coverage

### Target Coverage
- **Services:** 80%+ (核心商業邏輯)
- **Facade:** 70%+
- **Admin/Cron:** (未來測試)

### Coverage Report
```bash
composer test:coverage
# Opens coverage/index.html
```

## Test Data

### Fixtures
如果需要測試資料：
```php
// tests/Unit/Fixtures/
class TestDataProvider
{
    public static function lineMessages(): array
    {
        return [
            ['type' => 'text', 'text' => 'Test message'],
        ];
    }
}
```

## Testing Best Practices

### ✅ DO
- Test one thing per test method
- Use descriptive test names
- Keep tests fast (unit tests should be < 1s total)
- Test edge cases and error conditions
- Use type hints in test methods

### ❌ DON'T
- Test private methods directly (test through public API)
- Make tests depend on execution order
- Use real external APIs in unit tests
- Hard-code dates/times (use Carbon or similar)

## Continuous Integration (未來)

### 建議設定
```yaml
# .github/workflows/tests.yml
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Run tests
        run: composer test
```

## Test Bootstrap

**File:** `tests/bootstrap-unit.php`

用途：
- 設定測試環境
- 載入 Composer autoloader
- 定義 WordPress 函數 stubs（如需要）
- 設定常數

```php
<?php
// Load Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Define WordPress constants for testing
define('ABSPATH', '/tmp/');

// Define plugin constants
define('BuygoLineNotify_PLUGIN_DIR', dirname(__DIR__) . '/');
```

## Debugging Tests

### Verbose Output
```bash
composer test:unit
# or
./vendor/bin/phpunit -c phpunit-unit.xml --verbose
```

### Single Test
```bash
./vendor/bin/phpunit -c phpunit-unit.xml --filter testMethodName
```

### Debug with var_dump
```php
public function testDebug(): void
{
    $result = $this->service->method();
    var_dump($result); // Will show in test output
    $this->assertTrue(true);
}
```

## Notes

### Yoast PHPUnit Polyfills
提供 WordPress 兼容的 PHPUnit assertions：
- `assertIsString()` (compatible with older PHPUnit)
- WordPress-specific test utilities

### 測試優先
此專案從 `wordpress-plugin-kit` 生成，測試基礎設施已準備好。
**README.md** 甚至說「你會在 1 分鐘內跑起單元測試」。

### 未來擴展
可能需要：
- Integration tests (with WordPress loaded)
- E2E tests (for admin UI)
- API mocking library (for LINE API tests)
