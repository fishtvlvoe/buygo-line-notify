# Architecture

**Analysis Date:** 2026-01-31

## Pattern Overview

**Overall:** Layered WordPress Plugin with Service-Oriented Architecture

**Key Characteristics:**
- Service layer abstraction for business logic (separates concerns from WordPress hooks)
- Singleton plugin loader for initialization and dependency management
- REST API endpoints for webhook and third-party integration
- OAuth 2.0 Line Login flow with state management
- WordPress hooks system for extensibility (other plugins can hook into LINE events)
- Facade pattern for public API exposure to other plugins

## Layers

**Presentation Layer:**
- Purpose: Render admin UI and frontend shortcodes
- Location: `includes/admin/`, `includes/admin/views/`, `includes/shortcodes/`
- Contains: WordPress admin pages, HTML templates, AJAX handlers
- Depends on: Services layer, WordPress APIs
- Used by: WordPress admin interface, frontend pages

**REST API Layer:**
- Purpose: Handle external requests (webhooks, third-party integrations)
- Location: `includes/api/`
- Contains: REST endpoint registration, request validation, signature verification
- Depends on: Services layer, WordPress REST API
- Used by: LINE servers (webhooks), frontend JavaScript

**Service Layer:**
- Purpose: Encapsulate business logic and LINE API interactions
- Location: `includes/services/`
- Contains: Authentication, messaging, user binding, settings, profile sync, logging
- Depends on: Database layer, external APIs (LINE), WordPress core functions
- Used by: API layer, handlers, admin pages, shortcodes

**Handler Layer:**
- Purpose: Respond to WordPress hooks and route to appropriate services
- Location: `includes/handlers/`
- Contains: WordPress hook listeners (e.g., login_init for OAuth flow)
- Depends on: Service layer
- Used by: WordPress action/filter system

**Integration Layer:**
- Purpose: Integrate with other WordPress plugins (FluentCart, buygo-plus-one)
- Location: `includes/integrations/`
- Contains: Profile sync hooks, customer dashboard integration
- Depends on: Service layer, third-party plugin APIs
- Used by: Other plugins, WordPress hooks

**Data Persistence Layer:**
- Purpose: Manage database schema and version control
- Location: `includes/class-database.php`
- Contains: Table creation, schema versioning, migrations
- Depends on: WordPress database functions
- Used by: All layers needing data access

**Plugin Bootstrap:**
- Purpose: Initialize entire plugin system
- Location: `buygo-line-notify.php`, `includes/class-plugin.php`
- Contains: Hook registration, dependency loading, lifecycle management
- Depends on: All layers
- Used by: WordPress plugin loader

## Data Flow

**LINE Login OAuth 2.0 Flow:**

1. User clicks "Login with LINE" button (shortcode: `[buygo_line_login]`)
2. Frontend calls `Login_Handler::register_hooks()` → listens to `login_init` hook
3. `Login_Handler` calls `LoginService::get_authorize_url()` → generates state
4. `StateManager` stores state in transients with redirect URL and user_id context
5. Redirect to LINE's authorize endpoint
6. LINE redirects back to `wp-login.php?loginSocial=buygo-line&code=XXX&state=XXX`
7. `Login_Handler` verifies state via `StateManager::verify_state()`
8. Exchange code for token via `LoginService::exchange_token()`
9. Fetch user profile via `LoginService::get_user_profile()`
10. Call `LineUserService::linkUser()` → store binding in `wp_buygo_line_users` table
11. Log in user via `wp_signon()` or create new user if configured
12. Redirect to stored `redirect_url` from state

**LINE Webhook Event Processing:**

1. LINE Platform sends Webhook POST → `/wp-json/buygo-line-notify/v1/webhook`
2. `Webhook_API::handle_webhook()` receives request
3. `WebhookVerifier` validates HMAC signature using Channel Secret
4. If signature invalid, return 401 error
5. If Verify Event (replyToken: 32 zeros), return success immediately
6. Otherwise, schedule async processing (FastCGI or WP-Cron)
7. `WebhookHandler::process_events()` deduplicates events via transient cache
8. For each event, call `LineUserService::getUserByLineUid()` to get WordPress user
9. Trigger WordPress hooks: `buygo_line_webhook_TYPE` (e.g., `buygo_line_webhook_message`)
10. Other plugins can hook into these actions to handle specific events

**User Binding (LINE ↔ WordPress):**

1. New LINE user login → check if LINE UID exists in `wp_buygo_line_users`
2. If not found, create new user or use existing WordPress user
3. Call `LineUserService::linkUser($user_id, $line_uid, $profile)` → insert into table
4. Store LINE profile (display_name, picture_url) for later use
5. Mirror binding in `wp_buygo_line_bindings` table (for backward compat)
6. On unbind, call `LineUserService::unlinkUser()` → hard delete from both tables

**State Management:**

- User initiates OAuth → `StateManager::generate_state()` creates random token
- State stored in `wp_options` with key `buygo_line_state_XXX` (transient, 10 min expiry)
- Stored data: `{redirect_url, user_id, created_at}`
- Callback verifies state matches, then destroys it
- Prevents CSRF attacks and cross-session hijacking

**Message Sending Flow:**

1. External code calls `MessagingService::pushText($user_id, $text)`
2. Check if user linked via `LineUserService::isUserLinked($user_id)`
3. Get LINE UID via `LineUserService::getLineUidByUserId($user_id)`
4. Construct LINE API message payload
5. Send via `LineMessagingService::sendMessage()` → call LINE API
6. Log result and return success/error

**Settings & Configuration:**

- All settings stored in `wp_options` table with prefix `buygo_line_`
- Sensitive data (channel secret, access token) encrypted via `SettingsService::encrypt()`
- Uses WordPress `sanitize_*` and `validate_*` functions for input validation
- Accessible via: `SettingsService::get('key')` and `SettingsService::set('key', $value)`

## Key Abstractions

**LineUserService:**
- Purpose: Single source of truth for LINE↔WordPress user bindings
- Examples: `includes/services/class-line-user-service.php`
- Pattern: Static methods with database queries, returns user ID or LINE UID
- Hides database table complexity from other services

**SettingsService:**
- Purpose: Centralized configuration management with encryption
- Examples: `includes/services/class-settings-service.php`
- Pattern: Static methods, encryption/decryption for sensitive fields
- Known encrypted fields: channel_secret, channel_access_token, client_secret

**LoginService:**
- Purpose: OAuth 2.0 flow orchestration
- Examples: `includes/services/class-login-service.php`
- Pattern: Instance methods, stateful (contains StateManager, SettingsService)
- Handles authorize URL generation, token exchange, profile fetching

**MessagingService:**
- Purpose: LINE Messaging API wrapper
- Examples: `includes/services/class-messaging-service.php`
- Pattern: Static methods, minimal business logic
- Supports: text messages, flex messages, template messages

**WebhookHandler:**
- Purpose: Webhook event processing and deduplication
- Examples: `includes/services/class-webhook-handler.php`
- Pattern: Instance methods, transient-based deduplication
- Triggers WordPress hooks for extensibility

**AvatarService:**
- Purpose: Filter WordPress avatar URLs to use LINE profile pictures
- Examples: `includes/services/class-avatar-service.php`
- Pattern: Filter hook registration, caching via transients
- Replaces Gravatar with LINE display picture

## Entry Points

**Plugin Activation:**
- Location: `buygo-line-notify.php` (main file)
- Triggers: `register_activation_hook()` → `Database::init()`
- Responsibilities: Create/upgrade database tables, schema versioning

**Plugin Initialization:**
- Location: `buygo-line-notify.php` → `plugins_loaded` action (priority 20)
- Triggers: `Plugin::instance()->init()`
- Responsibilities: Load dependencies, register all hooks, initialize services

**WordPress Init Hook:**
- Location: `includes/class-plugin.php` → `onInit()`
- Triggers: All service registration hooks
- Responsibilities: Register shortcodes, REST API routes, admin pages, cron handlers

**REST API Webhook Endpoint:**
- Location: `includes/api/class-webhook-api.php`
- Route: `POST /wp-json/buygo-line-notify/v1/webhook`
- Responsibilities: Signature verification, event deduplication, async processing

**REST API Login Endpoint:**
- Location: `includes/api/class-login-api.php`
- Routes: `GET /callback` (OAuth callback), `POST /bind` (manual binding)
- Responsibilities: State verification, token exchange, user creation/login

**WordPress Login Hook:**
- Location: `includes/handlers/class-login-handler.php`
- Hook: `login_init` action
- Triggers: URL param `loginSocial=buygo-line`
- Responsibilities: OAuth flow coordination, user login/creation

**Admin Menu:**
- Location: `includes/admin/class-settings-page.php`
- Hook: `admin_menu` action (priority 30)
- Responsibilities: Register settings page, AJAX handlers for admin UI

**Shortcode: [buygo_line_login]**
- Location: `includes/shortcodes/class-login-button-shortcode.php`
- Responsibilities: Render login button on frontend

**Shortcode: [buygo_line_register_flow]**
- Location: `includes/shortcodes/class-register-flow-shortcode.php`
- Responsibilities: Render complete LINE registration/binding flow

## Error Handling

**Strategy:** Mix of exceptions, WP_Error, and error logging

**Patterns:**

- OAuth/API errors: `return new WP_Error('error_code', 'Message', $data)`
- Database errors: Return null or false, log via `Logger::log()`
- Webhook signature failure: Return 401 WP_Error
- Missing configuration: Return WP_Error with helpful context
- Unexpected failures: Log error, notify admin via error log, fail gracefully
- Fatal errors: Caught in handlers, logged, error_log output for debugging

**Example Error Flow:**
```php
// LineUserService::getUserByLineUid() returns null if not found
if (!$line_uid = $line_data['line_uid']) {
    return new WP_Error('line_uid_not_found', 'Cannot find LINE UID');
}

// LoginService returns WP_Error on token exchange failure
$token = LoginService::exchange_token($code);
if (is_wp_error($token)) {
    error_log('Token exchange failed: ' . $token->get_error_message());
    return redirect_with_error('authentication_failed');
}
```

## Cross-Cutting Concerns

**Logging:**
- Logger class: `includes/services/class-logger.php`
- Methods: `logWebhookEvent()`, `log()`, `log_message_attempt()`
- Output: WordPress error_log and debug table `wp_buygo_line_webhook_logs`

**Validation:**
- Input: WordPress `sanitize_*` and `validate_*` functions
- Signatures: HMAC-SHA256 verification via `WebhookVerifier`
- State: Cryptographic random tokens + time-based expiry
- Tokens: Verified against LINE API with retry logic

**Authentication:**
- WordPress session: `wp_signon()` for login
- LINE OAuth: `StateManager` for CSRF prevention
- API requests: Nonce verification via WordPress REST API
- Admin pages: `manage_options` capability check

**Caching:**
- Avatar pictures: Transient cache, 24-hour expiry
- Webhook events: Transient for deduplication, 60-second window
- State tokens: Transient, 10-minute expiry
- User bindings: No explicit cache (query database on demand)

---

*Architecture analysis: 2026-01-31*
