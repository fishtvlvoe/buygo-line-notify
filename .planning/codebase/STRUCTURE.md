# Codebase Structure

**Analysis Date:** 2026-01-31

## Directory Layout

```
buygo-line-notify/
├── buygo-line-notify.php          # Main plugin file (entry point)
├── composer.json                  # PHP dependencies & autoloading
├── phpunit-unit.xml              # PHPUnit configuration
│
├── includes/                      # Core plugin logic (PSR-4 namespace: BuygoLineNotify\)
│   ├── class-plugin.php          # Plugin singleton loader & hook registration
│   ├── class-database.php        # Database schema & versioning
│   ├── class-updater.php         # GitHub auto-updater
│   ├── class-buygo-line-notify.php # Public facade API
│   │
│   ├── services/                 # Business logic (pure PHP, reusable)
│   │   ├── class-login-service.php                    # OAuth 2.0 flow
│   │   ├── class-line-user-service.php               # User↔LINE bindings
│   │   ├── class-settings-service.php                # Config management
│   │   ├── class-logger.php                          # Debug logging
│   │   ├── class-webhook-handler.php                 # Event processing
│   │   ├── class-webhook-verifier.php                # HMAC validation
│   │   ├── class-state-manager.php                   # OAuth state tokens
│   │   ├── class-messaging-service.php               # Message sending (facade)
│   │   ├── class-line-messaging-service.php          # LINE Messaging API wrapper
│   │   ├── class-avatar-service.php                  # Avatar URL filter
│   │   ├── class-image-service.php                   # Image cache management
│   │   ├── class-image-uploader.php                  # Upload to WordPress media
│   │   ├── class-login-button-service.php            # Button rendering
│   │   ├── class-url-filter-service.php              # login_url/logout_url filters
│   │   ├── class-account-integration-service.php     # My Account page hooks
│   │   ├── class-profile-sync-service.php            # Sync LINE profile to WP
│   │   └── index.php                                 # Empty include guard
│   │
│   ├── api/                      # REST API endpoints
│   │   ├── class-webhook-api.php                 # POST /webhook (LINE events)
│   │   ├── class-login-api.php                   # OAuth callback handlers
│   │   ├── class-fluentcart-integration-api.php  # FluentCart API routes
│   │   └── class-debug-api.php                   # Debug helpers (dev only)
│   │
│   ├── handlers/                 # WordPress hook handlers
│   │   └── class-login-handler.php               # login_init hook (OAuth flow)
│   │
│   ├── integrations/             # Third-party plugin integrations
│   │   └── class-fluentcart-customer-profile-integration.php
│   │
│   ├── admin/                    # WordPress admin pages
│   │   ├── class-settings-page.php               # Settings UI + AJAX handlers
│   │   ├── class-debug-page.php                  # Debug info page (dev only)
│   │   ├── class-user-list-column.php            # Users table LINE status column
│   │   ├── views/
│   │   │   ├── settings-page.php                 # Main settings template
│   │   │   ├── settings-tab.php                  # Settings form
│   │   │   └── bindings-tab.php                  # User binding management
│   │   └── index.php
│   │
│   ├── shortcodes/               # Frontend shortcodes
│   │   ├── class-login-button-shortcode.php      # [buygo_line_login]
│   │   ├── class-register-flow-shortcode.php     # [buygo_line_register_flow]
│   │   └── class-line-binding-shortcode.php      # [buygo_line_binding]
│   │
│   ├── cron/                     # Scheduled events & retries
│   │   └── class-retry-dispatcher.php            # Retry scheduling logic
│   │
│   ├── exceptions/               # Custom exceptions
│   │   └── class-nsl-continue-page-render-exception.php
│   │
│   └── index.php                 # Empty include guard
│
├── assets/                       # Frontend assets
│   └── js/
│       ├── fluentcart-line-integration.js        # FluentCart integration JS
│       └── fluentcart-line-integration-standalone.js
│
├── tests/                        # Unit tests (PSR-4: BuygoLineNotify\Tests\)
│   ├── bootstrap-unit.php        # Test setup (mocks, fixtures)
│   └── Unit/                     # Unit test files
│
└── .planning/                    # GSD planning documents
    └── codebase/
        ├── ARCHITECTURE.md       # This file
        └── STRUCTURE.md          # This file
```

## Directory Purposes

**includes/:**
- Purpose: All PHP code organized by responsibility (PSR-4 namespace)
- Contains: Services (business logic), APIs (REST endpoints), handlers (hooks), admin UI, shortcodes
- Key files: `class-plugin.php` (initialization), `class-database.php` (schema)

**includes/services/:**
- Purpose: Business logic isolated from WordPress hooks and presentation
- Contains: Pure classes with static/instance methods, no direct hook registration
- Examples: OAuth flow, user binding, message sending, settings management
- Pattern: Stateless where possible, dependency injection via constructor

**includes/api/:**
- Purpose: REST API endpoints for external systems and webhooks
- Contains: Classes that extend WordPress REST API, handle requests/responses
- Examples: Webhook signature verification, OAuth callback, FluentCart integration
- Pattern: Each file = one route group, register routes in `register_routes()` method

**includes/handlers/:**
- Purpose: Listen to WordPress hooks and delegate to services
- Contains: Hook listeners that orchestrate service calls
- Examples: `login_init` hook → OAuth flow coordination
- Pattern: One handler per hook family, minimal logic (just call services)

**includes/integrations/:**
- Purpose: Integration with other WordPress plugins
- Contains: Hook registration for third-party plugin events
- Examples: FluentCart customer profile sync, buygo-plus-one admin integration
- Pattern: Standalone classes, can be conditionally loaded

**includes/admin/:**
- Purpose: WordPress admin interface pages
- Contains: Page rendering, form handling, AJAX endpoints
- Key files: `class-settings-page.php` (main config), `views/` (HTML templates)
- Pattern: One class per page type, templates in `views/` subdirectory

**includes/admin/views/:**
- Purpose: HTML/PHP template files for admin pages
- Contains: Form markup, settings fields, data display
- Pattern: Minimal PHP logic, mostly HTML with `<?php echo`
- Naming: `{page-name}.php` (e.g., `settings-page.php`)

**includes/shortcodes/:**
- Purpose: Frontend shortcode implementations
- Contains: Classes that render HTML/JavaScript when shortcode used
- Examples: Login button, registration flow, binding management
- Pattern: One class per shortcode, register via `register_shortcode()`

**includes/cron/:**
- Purpose: WP-Cron event handlers and scheduling
- Contains: Scheduled tasks, retry logic
- Examples: Retry dispatcher for failed message sends
- Pattern: Static methods, registered via `add_action()` in `Plugin::onInit()`

**assets/:**
- Purpose: Frontend JavaScript and CSS assets
- Contains: JavaScript for frontend interactions, CSS for styling
- Naming: `{feature}-{type}.js` (e.g., `fluentcart-line-integration.js`)
- Enqueue: Via WordPress `wp_enqueue_script()` in handlers/services

**tests/:**
- Purpose: PHPUnit tests (no WordPress dependency)
- Contains: Test classes with naming convention `{Class}Test`
- Example: `tests/Unit/Services/LineUserServiceTest.php` tests `includes/services/class-line-user-service.php`
- Pattern: One test class per source class, organized by directory

## Key File Locations

**Entry Points:**
- `buygo-line-notify.php`: Plugin loader, hook registration, version/constant definition
- `includes/class-plugin.php`: Singleton plugin instance, dependency loading orchestration
- `includes/class-database.php`: Database initialization, schema versioning

**Configuration & Settings:**
- `includes/services/class-settings-service.php`: All settings CRUD (encrypted sensitive data)
- `buygo-line-notify.php`: Constants (version, paths, URLs)

**Core Logic:**
- `includes/services/class-login-service.php`: OAuth 2.0 flow (authorize, callback, token exchange)
- `includes/services/class-line-user-service.php`: WordPress↔LINE user binding
- `includes/services/class-webhook-handler.php`: Incoming webhook processing
- `includes/services/class-messaging-service.php`: Outbound message sending

**API Endpoints:**
- `includes/api/class-webhook-api.php`: `POST /wp-json/buygo-line-notify/v1/webhook`
- `includes/api/class-login-api.php`: OAuth callback, manual binding routes
- `includes/api/class-fluentcart-integration-api.php`: FluentCart dashboard endpoints

**Admin Interface:**
- `includes/admin/class-settings-page.php`: Settings page registration + AJAX handlers
- `includes/admin/views/settings-page.php`: Main admin template
- `includes/admin/class-debug-page.php`: Developer debug page

**Frontend Shortcodes:**
- `includes/shortcodes/class-login-button-shortcode.php`: Renders login button
- `includes/shortcodes/class-register-flow-shortcode.php`: Renders registration page

## Naming Conventions

**Files:**
- `class-{service-name}.php` - All class files use kebab-case with class- prefix
- Example: `class-login-service.php` contains `LoginService` class

**Directories:**
- `{service-type}/` - Plural form for grouping (services/, api/, handlers/, etc.)
- Example: `services/` contains all service classes

**Namespaces:**
- `BuygoLineNotify\` - Root namespace (PSR-4 maps to `includes/`)
- `BuygoLineNotify\Services\` - Maps to `includes/services/`
- `BuygoLineNotify\Api\` - Maps to `includes/api/`
- `BuygoLineNotify\Admin\` - Maps to `includes/admin/`
- `BuygoLineNotify\Handlers\` - Maps to `includes/handlers/`
- `BuygoLineNotify\Integrations\` - Maps to `includes/integrations/`
- `BuygoLineNotify\Shortcodes\` - Maps to `includes/shortcodes/`
- `BuygoLineNotify\Cron\` - Maps to `includes/cron/`
- `BuygoLineNotify\Exceptions\` - Maps to `includes/exceptions/`

**Class Names:**
- `LoginService` - PascalCase, verb + noun (action + subject)
- `LineUserService` - Compound names join logically (LINE + User)
- `Webhook_API` - REST API classes use snake_case for clarity
- `Register_Flow` - Multi-word components separated by underscore in REST context

**Functions/Methods:**
- `camelCase` for public instance methods: `getUserByLineUid()`
- `snake_case` for static/utility methods: `isUserLinked()` (camelCase actually used)
- `CONSTANT_CASE` for class constants: `const DB_VERSION = '2.1.0'`

**WordPress Hooks/Filters:**
- `buygo_line_webhook_TYPE` - Webhook event hooks (e.g., `buygo_line_webhook_message`)
- `buygo_line_notify_*` - Plugin-specific hooks/filters
- Standard WordPress hooks: `login_init`, `plugins_loaded`, `admin_menu`, etc.

**Database Table Names:**
- `wp_buygo_line_users` - WordPress↔LINE user mappings (primary source of truth)
- `wp_buygo_line_bindings` - Legacy compatibility table
- `wp_buygo_line_webhook_logs` - Webhook event audit log
- `wp_buygo_line_message_logs` - Message send attempt log

**Option Names (wp_options):**
- `buygo_line_{setting_name}` - Plugin settings (e.g., `buygo_line_channel_id`)
- `buygo_line_db_version` - Database schema version
- `buygo_line_state_{token}` - OAuth state tokens (transient)

## Where to Add New Code

**New Feature (Authentication/Integration):**
- Primary code: `includes/services/class-{feature}-service.php`
- REST API: `includes/api/class-{feature}-api.php` (if external API needed)
- Tests: `tests/Unit/Services/{Feature}ServiceTest.php`
- Admin UI: `includes/admin/class-{feature}-page.php` (if config needed)

**New Admin Page:**
- Page class: `includes/admin/class-{page-name}-page.php`
- Template: `includes/admin/views/{page-name}.php`
- Hook registration: Register in `Plugin::initAdminFeatures()` in `class-plugin.php`

**New Shortcode:**
- Shortcode class: `includes/shortcodes/class-{shortcode-name}-shortcode.php`
- Hook registration: Register in `Plugin::register_shortcodes()` in `class-plugin.php`
- Frontend assets: `assets/js/{shortcode-name}.js` (if needed)

**New REST API Endpoint:**
- API class: `includes/api/class-{endpoint-name}-api.php`
- Implementation: Extend from WordPress REST_Controller or direct route registration
- Hook registration: Register in `Plugin::onInit()` REST API section
- Route format: `/wp-json/buygo-line-notify/v1/{endpoint}`

**New Service:**
- Service class: `includes/services/class-{service-name}-service.php`
- Initialization: Add require_once in `Plugin::loadDependencies()` with proper ordering
- Dependency ordering: Load dependencies before dependents (e.g., SettingsService before ProfileSyncService)

**New Webhook Event Handler:**
- No new file needed - Hook into `buygo_line_webhook_{type}` action
- Location: Register in your own plugin via `add_action('buygo_line_webhook_message', $callback)`
- Data available: Event array from LINE webhook

**Utilities:**
- Shared helpers: `includes/services/class-{utility}-service.php` (use static methods)
- WordPress filters: Register in handlers or `onInit()`
- Example: `UrlFilterService` provides `login_url` filter

## Special Directories

**assets/:**
- Purpose: Frontend JavaScript and CSS
- Generated: No
- Committed: Yes
- Enqueued: Via `wp_enqueue_script()` in handlers or services

**tests/:**
- Purpose: PHPUnit unit tests (no WordPress loaded)
- Generated: No
- Committed: Yes
- Run: `composer test` or `composer test:coverage`

ː**.planning/:**
- Purpose: GSD planning and analysis documents
- Generated: Yes (via GSD commands)
- Committed: Yes (consumed by other GSD phases)
- Contents: ARCHITECTURE.md, STRUCTURE.md, CONVENTIONS.md, TESTING.md, etc.

---

*Structure analysis: 2026-01-31*
