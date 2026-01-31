# Testing Patterns

**Analysis Date:** 2026-01-31

## Test Framework

**Runner:**
- PHPUnit 9.6+
- Config: `phpunit-unit.xml`

**Assertion Library:**
- PHPUnit built-in assertions
- Yoast PHPUnit Polyfills 2.0+ for WordPress compatibility

**Run Commands:**
```bash
composer test              # Run all unit tests
composer test:unit         # Run all tests with verbose output
composer test:coverage     # Generate HTML coverage report (outputs to coverage/)
```

**Configuration File:**
From `phpunit-unit.xml`:
```xml
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.5/phpunit.xsd"
         bootstrap="tests/bootstrap-unit.php"
         colors="true"
         verbose="true"
         processIsolation="false"
         failOnWarning="true">
```

## Test File Organization

**Status:** No formal unit tests directory exists

**Current Testing Approach:**
- Ad-hoc test scripts in root directory (e.g., `test-line-user-service.php`, `test-messaging-service.php`)
- Scripts are designed to run via browser in WordPress environment
- No PHPUnit test suite currently implemented despite infrastructure being present

**Recommended Structure (when implemented):**
```
tests/
├── bootstrap-unit.php           # Test bootstrap (loads composer autoloader)
├── Unit/
│   ├── Services/
│   │   ├── LineUserServiceTest.php
│   │   ├── LoginServiceTest.php
│   │   ├── SettingsServiceTest.php
│   │   └── MessagingServiceTest.php
│   ├── Api/
│   │   ├── WebhookAPITest.php
│   │   └── LoginAPITest.php
│   └── Handlers/
│       └── LoginHandlerTest.php
├── Fixtures/
│   ├── MockLineResponse.php
│   └── TestData.php
└── README.md
```

## Test Structure

**Bootstrap File Approach:**
From `composer.json` autoload-dev:
```json
"autoload-dev": {
    "psr-4": {
        "BuygoLineNotify\\Tests\\": "tests/"
    }
}
```

**Expected Test Naming Convention:**
- File: `{ClassName}Test.php`
- Class namespace: `BuygoLineNotify\Tests\Unit\Services\LineUserServiceTest`
- Test methods: `test{Feature}()` - Example: `testGetUserByLineUid()`, `testLinkUserSuccessfully()`

**Test Class Template (to follow when implementing):**
```php
<?php
namespace BuygoLineNotify\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Yoast\PHPUnitPolyfills\TestCases\TestCase as YoastTestCase;
use BuygoLineNotify\Services\LineUserService;

class LineUserServiceTest extends YoastTestCase {

    public function test_getUserByLineUid_returns_user_id() {
        // Arrange
        $line_uid = 'U1234567890abcdef';
        $expected_user_id = 1;

        // Act
        $result = LineUserService::getUserByLineUid($line_uid);

        // Assert
        $this->assertEquals($expected_user_id, $result);
    }
}
```

## Mocking

**Framework:** PHPUnit built-in mocking (createMock, prophesize, etc.)

**Pattern for Service Dependencies:**
- Services use static methods, limiting mockability
- When implementing tests, consider dependency injection refactoring
- Mock WordPress global functions using function mocks or test doubles

**What to Mock:**
- WordPress database functions: `wpdb` queries
- External API calls: LINE Login API, LINE Messaging API
- WordPress global functions: `get_option()`, `update_option()`, `wp_schedule_single_event()`
- File operations: `wp_upload_bits()`, media library functions

**What NOT to Mock:**
- Core service logic (business rules)
- Database schema and migrations
- Data transformation functions
- Validation logic

**Example Mock Pattern (recommended for implementation):**
```php
$wpdb_mock = $this->createMock(\wpdb::class);
$wpdb_mock->expects($this->once())
    ->method('get_var')
    ->willReturn(1); // Return user ID

// Alternative: Use test fixtures instead of mocks
```

## Fixtures and Factories

**Test Data Patterns:**
- No dedicated fixture classes exist yet
- Ad-hoc test data in test scripts - Example from `test-line-user-service.php`:
  ```php
  $test_user_id = 1;
  $test_line_uid = 'U1234567890abcdef';
  $test_profile = [
      'displayName' => '測試使用者',
      'pictureUrl' => 'https://example.com/avatar.jpg'
  ];
  ```

**Recommended Fixture Location (when implemented):**
- Directory: `tests/Fixtures/`
- Files:
  - `TestData.php` - Static test data providers
  - `MockLineProfile.php` - LINE API response fixtures
  - `MockWebhookEvent.php` - Webhook event fixtures

**Recommended Factory Pattern:**
```php
class TestDataFactory {
    public static function createLineProfile($overrides = []) {
        return array_merge([
            'displayName' => 'Test User',
            'pictureUrl' => 'https://example.com/avatar.jpg',
            'statusMessage' => 'Testing'
        ], $overrides);
    }

    public static function createWebhookEvent($type = 'message', $overrides = []) {
        return array_merge([
            'type' => $type,
            'timestamp' => time() * 1000,
            'source' => ['type' => 'user', 'userId' => 'U1234567890'],
            'replyToken' => 'fake-reply-token'
        ], $overrides);
    }
}
```

## Coverage

**Requirements:** None explicitly enforced currently

**Coverage Scope (from phpunit-unit.xml):**
```xml
<coverage processUncoveredFiles="true">
    <include>
        <directory suffix=".php">includes/</directory>
    </include>
    <exclude>
        <directory suffix="Test.php">tests/</directory>
        <directory>vendor</directory>
    </exclude>
</coverage>
```

**View Coverage:**
```bash
composer test:coverage
# Opens: coverage/index.html in browser
```

**Critical Paths to Test (priority):**
1. OAuth flow validation (LoginService, StateManager)
2. LINE user binding/unbinding (LineUserService)
3. Webhook signature verification (WebhookVerifier)
4. Message delivery (MessagingService)
5. Settings encryption/decryption (SettingsService)

## Test Types

**Unit Tests:**
- Scope: Pure PHP functions, service methods (no WordPress dependencies)
- Approach: Test in isolation with mocked external dependencies
- Examples to implement:
  - SettingsService encryption/decryption
  - LineUserService database queries
  - StateManager state generation/validation
  - LoginService URL building

**Integration Tests:**
- Scope: Services working together with WordPress environment
- Approach: Use bootstrap to load WordPress, test database operations
- Currently done ad-hoc via browser test scripts

**E2E Tests:**
- Framework: Not used
- Alternative: Manual browser testing or Selenium (not configured)

## Common Patterns

**Async Testing:**
- WordPress cron: `wp_schedule_single_event()` uses transient-based scheduling
- Webhook processing: Uses `fastcgi_finish_request()` in FastCGI environments
- Pattern: Don't test async directly; test event queueing logic separately
  ```php
  public function test_webhook_scheduling() {
      // Verify event was scheduled, don't verify execution
      do_action('rest_api_init');
      // Assert wp_schedule_single_event was called
  }
  ```

**Database Testing:**
- Use SQLite in memory for test database (not currently configured)
- Alternative: Use WordPress test database from `wp-tests-lib`
- Current approach: Ad-hoc scripts using actual WordPress database

**Error Testing:**
- Test exceptions are thrown - Example pattern:
  ```php
  public function test_invalid_state_throws_exception() {
      $this->expectException(InvalidStateException::class);
      StateManager::validate_state('invalid-state-value');
  }
  ```

- Test error returns - Example:
  ```php
  public function test_webhook_invalid_signature_returns_401() {
      // Arrange: Mock webhook with bad signature
      // Act: Call webhook endpoint
      // Assert: Response status is 401
      $this->assertEquals(401, $response['status']);
  }
  ```

**WordPress Function Mocking:**
- Use function stubs for `error_log()`, `wp_schedule_single_event()`, etc.
- Pattern (recommended when implementing):
  ```php
  use Brain\Monkey\Functions;

  Functions\expect('get_option')
      ->andReturn('value');
  ```

## Ad-hoc Test Scripts (Current Approach)

**Location:** Root directory with `test-*.php` prefix

**Existing Test Scripts:**
- `test-line-user-service.php` - Tests LineUserService methods
- `test-messaging-service.php` - Tests message delivery
- `test-settings.php` - Tests settings service
- `test-binding-status.php` - Tests user binding flow
- `test-image-download.php` - Tests image service
- Other diagnostic scripts: `check-line-users.php`, `debug-page.php`

**Usage Pattern:**
1. Access via browser: `https://test.buygo.me/wp-content/plugins/buygo-line-notify/test-line-user-service.php`
2. Loads WordPress environment: `require_once __DIR__ . '/../../../wp-load.php'`
3. Runs test and displays HTML results with green (✓) or red (✗) indicators

**Example Test Script Structure:**
```php
<?php
// 載入 WordPress
if (file_exists(__DIR__ . '/../../../wp-load.php')) {
    require_once __DIR__ . '/../../../wp-load.php';
}

// 載入必要類別
require_once __DIR__ . '/includes/services/class-line-user-service.php';

use BuygoLineNotify\Services\LineUserService;

echo "<h1>LineUserService 功能測試</h1>";

// 執行測試，顯示結果
$result = LineUserService::getUserByLineUid('test-uid');
echo $result ? '<p style="color: green;">✓ Test passed</p>' : '<p style="color: red;">✗ Test failed</p>';
```

## Debugging & Development

**Test Script Manager:**
- Use `/tsm` Skills for rapid development and testing
- Backend: `https://test.buygo.me/wp-admin/admin.php?page=test-script-manager`
- Location: `wp-content/plugins/test-script-manager/`
- Workflow: Write quick scripts in PHP, embed JavaScript/CSS for integration testing, iterate fast

**When Debugging Tests:**
1. Run ad-hoc script via browser
2. Check `error_log()` output in WordPress debug log
3. Review database tables with `check-line-users.php` or `/tsm`
4. Verify settings via `check-settings.php`

---

*Testing analysis: 2026-01-31*
