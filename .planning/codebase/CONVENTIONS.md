# Coding Conventions

**Analysis Date:** 2026-01-31

## Naming Patterns

**Files:**
- Class files: `class-{name}.php` (snake-case) - Example: `class-login-service.php`, `class-webhook-api.php`
- Directories: snake-case - Example: `includes/services/`, `includes/admin/`, `includes/api/`
- HTML templates: `{name}.php` in `admin/views/` - Example: `settings-tab.php`, `settings-page.php`

**Classes:**
- PascalCase with namespace prefix - Example: `LoginService`, `WebhookAPI`, `LineUserService`
- Namespace structure mirrors directory layout - Example: `BuygoLineNotify\Services\LoginService` in `includes/services/class-login-service.php`

**Functions and Methods:**
- Class methods: camelCase with access modifiers - Example: `getUserByLineUid()`, `getLineUidByUserId()`, `isUserLinked()`
- Static methods: PascalCase prefix optional - Example: `static function init()`, `static function register_hooks()`
- WordPress hooks/callbacks: snake_case - Example: `register_hooks()`, `handle_webhook()`, `get_authorize_url()`
- Private methods: snake_case or camelCase - Example: `private function get_encryption_key()`

**Variables:**
- Instance variables: snake_case - Example: `$this->state_manager`, `$this->login_service`
- Static variables: snake_case prefixed with `$_` - Example: `private static ?self $_instance = null`
- Array keys: snake_case - Example: `['redirect_url']`, `['user_id']`
- Parameters: snake_case - Example: `function get_authorize_url(?string $redirect_url = null, ?int $user_id = null)`

**Constants:**
- ALL_CAPS with underscores - Example: `const LINE_OAUTH_BASE_URL`, `const DB_VERSION`, `const PROFILE_TRANSIENT_PREFIX`

## Code Style

**Formatting:**
- Indentation: 4 spaces (tabs shown as 4 spaces in display)
- Line length: No strict limit, but keep reasonable (code wraps naturally in editors)
- Brace style: Opening brace on same line (PSR-2 style)
  ```php
  public function methodName() {
      // Code here
  }
  ```

**PHP Version:**
- Minimum: PHP 8.0+ (from composer.json: `"php": "^8.0 || ^8.1 || ^8.2 || ^8.3"`)
- Use modern PHP features: typed properties, union types, null coalescing

**Null Coalescing:**
- Prefer `??` operator - Example: `$value = $data['key'] ?? null`
- Use null safe operator `?->` where appropriate

**Linting:**
- No automated linter configured
- Follow WordPress Coding Standards manually for security functions (sanitize, escape)

## Import Organization

**Order:**
1. Namespace declaration
2. `use` statements (alphabetically ordered)
3. WordPress guard: `if (!defined('ABSPATH')) { exit; }`
4. Class/file content

**Example (from `class-login-handler.php`):**
```php
<?php
namespace BuygoLineNotify\Handlers;

use BuygoLineNotify\Services\LoginService;
use BuygoLineNotify\Services\LineUserService;
use BuygoLineNotify\Services\StateManager;
use BuygoLineNotify\Services\Logger;
use BuygoLineNotify\Services\SettingsService;
use BuygoLineNotify\Exceptions\NSLContinuePageRenderException;

if (!defined('ABSPATH')) {
    exit;
}
```

**Path Aliases:**
- Not used; full namespace paths preferred
- PSR-4 autoloading: `BuygoLineNotify\` maps to `includes/`

## Error Handling

**Pattern:**
- Use try-catch blocks for external API calls and database operations
- Catch generic `\Exception` in admin pages - Example: `class-user-list-column.php`
  ```php
  try {
      // Perform action
  } catch (\Exception $e) {
      // Handle gracefully
  }
  ```

**Return Values:**
- Methods return `int|null` for ID queries - Example: `public static function getUserByLineUid(string $line_uid): ?int`
- Methods return `string|null` for value queries - Example: `public static function getLineUidByUserId(int $user_id): ?string`
- Methods return `bool` for action success - Example: `public static function linkUser(...): bool`
- Array methods return typed arrays with keys - Example: `public static function getWebhookLogs(...): array`

**WordPress Integration:**
- Use `wp_die()` with `esc_html__()` for permission errors in admin pages
- Use `wp_nonce_field()` and `wp_verify_nonce()` for form security
- Use `wp_schedule_single_event()` for background task scheduling

## Logging

**Framework:** WordPress `error_log()` function

**Patterns:**
- Debug logging in lifecycle methods - Example: `class-plugin.php`
  ```php
  error_log('BuygoLineNotify: plugins_loaded hook fired, initializing plugin');
  ```
- Webhook rejection logging - Example: `class-webhook-api.php`
  ```php
  error_log('BUYGO_LINE_NOTIFY: Webhook rejected - Invalid signature');
  ```
- Structured logging via Logger service - Example: `class-logger.php`
  ```php
  public static function logWebhookEvent(string $event_type, ?string $line_uid, ?int $user_id, ?string $webhook_event_id)
  ```

**What to Log:**
- Entry points and initialization
- Webhook verification failures
- OAuth state validation failures
- Database operation failures
- Message send status (success/failed)

## Comments

**When to Comment:**
- Document complex algorithms (state management, OAuth flow)
- Explain database schema version updates - Example: `class-database.php`
  ```php
  // 版本 2.1.0: 新增 Debug 資料表
  if (version_compare($current_version, '2.1.0', '<')) {
      self::create_webhook_logs_table();
  }
  ```
- Explain workarounds and non-obvious code
- Use Chinese for business logic clarity
- Use English for technical implementation details

**Comment Style:**
- Single line: `// Comment`
- Multi-line: `/* Comment */` for file headers
- Block comments above methods for complex logic

**JSDoc/PHPDoc:**
- Full DocBlock for all public methods with `@param` and `@return`
- Example from `class-logger.php`:
  ```php
  /**
   * 記錄 Webhook 事件
   *
   * @param string      $event_type      事件類型(如:message, follow, postback)
   * @param string|null $line_uid        LINE User ID
   * @param int|null    $user_id         WordPress User ID
   * @param string|null $webhook_event_id Webhook Event ID(用於去重)
   * @return int|false Insert ID 或 false
   */
  public static function logWebhookEvent(...)
  ```

- Type hints in signatures preferred over DocBlock types

## Function Design

**Size:**
- Prefer functions under 100 lines for readability
- Larger functions (like `class-login-handler.php` at 1033 lines) indicate complex logic requiring careful modification
- Extract private helper methods for repeated logic

**Parameters:**
- Limit to 4-5 parameters; use array for complex configurations
- Use typed parameters with null checks - Example:
  ```php
  public static function linkUser(int $user_id, string $line_uid, bool $is_registration = false): bool
  ```
- Use default values for optional parameters

**Return Values:**
- Explicit return type hints: `void`, `bool`, `string`, `int`, `array`, `?Type`
- Return null for "not found" scenarios - Example: `?int`, `?string`
- Return arrays with consistent key structure - Example:
  ```php
  return [
      'logs'       => $results,
      'total'      => (int) $total,
      'page'       => $page,
      'per_page'   => $per_page,
      'total_pages' => ceil($total / $per_page),
  ];
  ```

## Module Design

**Exports:**
- Classes use static methods for utility functions - Example: `SettingsService::get()`, `SettingsService::set()`
- Service classes are instantiated in handlers/API classes
- Facade pattern used for external plugin access - Example: `class-buygo-line-notify.php`

**Barrel Files:**
- Not used; import specific classes directly
- Each class in its own file matching filename

**Class Organization:**
- Static singleton pattern for Plugin class:
  ```php
  final class Plugin {
      private static ?self $_instance = null;

      public static function instance(): self {
          if (self::$_instance === null) {
              self::$_instance = new self();
          }
          return self::$_instance;
      }
  }
  ```

**Dependency Injection:**
- Constructor injection used in handlers - Example: `class-login-handler.php`
  ```php
  public function __construct() {
      $this->login_service = new LoginService();
      $this->state_manager = new StateManager();
  }
  ```

## WordPress Specific

**Hooks and Filters:**
- Register hooks in static `register_hooks()` method
- Use `add_action()` and `add_filter()` with class methods - Example:
  ```php
  add_action('init', [$this, 'onInit']);
  add_filter('manage_users_columns', [self::class, 'add_line_column']);
  ```

**Security:**
- Always use `wp_nonce_field()` in forms: `wp_nonce_field('buygo_line_notify_demo_action', 'buygo_line_notify_nonce')`
- Sanitize input: `sanitize_text_field(wp_unslash($_POST['key']))`
- Escape output: `esc_html()`, `esc_attr()`, `esc_url()`, `esc_js()`
- Check capabilities: `current_user_can()` before admin operations

**REST API:**
- Register routes in `register_routes()` method
- Use namespace pattern: `'buygo-line-notify/v1'`
- Define permission callbacks: `'permission_callback' => '__return_true'` or capability check

---

*Convention analysis: 2026-01-31*
