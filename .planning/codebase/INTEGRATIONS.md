# External Integrations

**Analysis Date:** 2026-01-31

## APIs & External Services

**LINE Platform:**
- **LINE Messaging API** - Send notifications and messages to LINE users
  - SDK/Client: WordPress wp_remote_* functions (native HTTP client)
  - Auth: `CHANNEL_ACCESS_TOKEN` stored in wp_options (encrypted)
  - Endpoint: `https://api.line.me/v2/bot/message/push` (push messages)
  - Header: `Authorization: Bearer {CHANNEL_ACCESS_TOKEN}`
  - Files: `includes/services/class-line-messaging-service.php` (line 79-102)

- **LINE Login API** - OAuth 2.0 authentication
  - SDK/Client: WordPress wp_remote_* functions
  - Auth: `LOGIN_CHANNEL_ID`, `LOGIN_CHANNEL_SECRET` stored in wp_options (encrypted)
  - OAuth Base: `https://access.line.me/oauth2/v2.1`
  - API Base: `https://api.line.me`
  - Flow: authorize → callback → token exchange → profile fetch
  - Files: `includes/services/class-login-service.php` (line 48-240)

- **LINE Bot/User Info API** - Verify credentials and fetch user profiles
  - Endpoint: `https://api.line.me/v2/bot/info` (verify channel access token)
  - Endpoint: `https://api.line.me/v2/oauth/accessToken` (exchange code for token)
  - Endpoint: `https://api.line.me/v2/oauth/profile` (get user profile)
  - Files: Multiple test files verify these endpoints (`test-token-comparison.php`, `diagnose-token-flow.php`)

## Data Storage

**Primary Database:**
- MySQL/MariaDB via WordPress
- Connection: via WordPress $wpdb global
- Client: WordPress dbDelta() for schema management

**Tables:**
- `wp_buygo_line_bindings` - Maps WordPress user_id to LINE user_id (unique constraints on both)
  - Columns: id, user_id, line_uid, display_name, picture_url, status, created_at, updated_at
  - Location: `includes/class-database.php` (line 75-91)

- `wp_buygo_line_users` - Aligns with Nextend wp_social_users structure
  - Columns: user_id, identifier, access_token, refresh_token, name, email, picture_url, etc.
  - Location: `includes/class-database.php` (line 98-140)

- `wp_buygo_line_webhook_logs` - Debug table for webhook events (v2.1.0+)
  - Location: `includes/class-database.php` (line 170-198)

- `wp_buygo_line_message_logs` - Debug table for message operations (v2.1.0+)
  - Location: `includes/class-database.php` (line 200-232)

**Options Storage (wp_options):**
- `buygo_line_settings` - Encrypted plugin settings (JSON)
  - Contains: channel_access_token, channel_secret, login_channel_id, login_channel_secret
  - Encryption: AES-128-ECB via `SettingsService`
  - Location: `includes/services/class-settings-service.php` (line 15-100)

- `buygo_line_db_version` - Database schema version (for migrations)

- `buygo_line_profile_sync_queue_{user_id}` - Queued profile sync data (autoload=false)
  - Location: `includes/services/class-profile-sync-service.php`

**File Storage:**
- Local filesystem via WordPress media library
- Media Library attachment posts for user avatars from LINE
- Files: `includes/services/class-image-uploader.php` (handles wp_remote_get → wp_handle_sideload)

**Caching:**
- None detected (no Redis, Memcached, or transient caching)

## Authentication & Identity

**Auth Provider:**
- **LINE Login (OAuth 2.0)** - Primary authentication mechanism
  - Implementation: Custom OAuth flow via `LoginService` class
  - Standard WordPress integration: Uses `wp-login.php?loginSocial=buygo-line`
  - State Management: HMAC-SHA256 state tokens stored in wp_options
  - Files:
    - OAuth flow: `includes/services/class-login-service.php`
    - State management: `includes/services/class-state-manager.php`
    - Handler: `includes/handlers/class-login-handler.php` (line 49)

**User Linking:**
- WordPress users linked to LINE users via `wp_buygo_line_bindings` table
- One-to-one mapping: user_id ↔ line_uid (unique constraints)
- Display name and profile picture synced from LINE profile
- Files: `includes/services/class-user-service.php`

**Session/Token:**
- Standard WordPress session cookies (no custom JWT)
- LINE access_token stored in `wp_buygo_line_users.access_token` (encrypted)
- Refresh tokens stored for token renewal

## Webhook Handling

**Incoming Webhooks:**

- **LINE Messaging API Webhooks** - User-initiated events (messages, follows, etc.)
  - Endpoint: `POST /wp-json/buygo-line-notify/v1/webhook`
  - Signature: HMAC-SHA256 in `X-Line-Signature` header
  - Verification: `includes/services/class-webhook-verifier.php` (line 30-60)
  - Handler: `includes/api/class-webhook-api.php` (line 56-85)
  - Events: Follow, Message, UnFollow, Beacon, etc.
  - Processing: Async via WordPress cron (`buygo_process_line_webhook` action)
  - Files:
    - API: `includes/api/class-webhook-api.php`
    - Verifier: `includes/services/class-webhook-verifier.php`
    - Handler: `includes/services/class-webhook-handler.php`

**Outgoing Webhooks:**
- **FluentCart Integration Webhooks** - Order/customer events
  - Endpoint: `POST /wp-json/buygo-line-notify/v1/fluentcart/webhook` (planned)
  - Triggers on order creation, payment status updates
  - Implementation: `includes/integrations/class-fluentcart-customer-profile-integration.php`

**Webhook Logging:**
- All webhook events logged to `wp_buygo_line_webhook_logs` for debugging
- Message operations logged to `wp_buygo_line_message_logs`
- Debug view: WordPress admin → Buygo Line Notify → Debug Logs

## Cron & Background Jobs

**WordPress Cron (WP-Cron):**
- Event: `buygo_process_line_webhook` - Async webhook processing
  - Triggered by webhook API after signature verification
  - Handler: `WebhookHandler::process_events()` via Plugin::onInit() hook
  - Location: `includes/class-plugin.php` (line 92-95)

- **Retry Dispatcher** - Automatic retry for failed requests
  - Class: `includes/cron/class-retry-dispatcher.php`
  - Retries failed message deliveries and webhook acknowledgments
  - Files: `includes/cron/class-retry-dispatcher.php` (line 1-210)

## Admin Integration

**WordPress Admin Pages:**
- Settings page: Dashboard for plugin configuration
  - Parent menu: Adapts to `buygo-plus-one` if available
  - Location: `includes/admin/class-settings-page.php`
  - Route: `wp-admin/admin.php?page=buygo-line-notify-settings`

- User list column: Shows LINE binding status in Users table
  - Location: `includes/admin/class-user-list-column.php`

- Debug page: Webhook and message logs viewer
  - Location: `includes/admin/class-debug-page.php`

## Frontend Integrations

**ShortCodes:**
- `[buygo_line_login]` - LINE login button
  - Handler: `includes/shortcodes/class-login-button-shortcode.php`
  - Renders HTML button, opens OAuth flow in popup

- `[buygo_line_binding]` - Show/manage LINE binding status
  - Handler: `includes/shortcodes/class-line-binding-shortcode.php`
  - Uses Vue component for binding/unbinding

- `[buygo_line_register_flow]` - Registration flow for new users
  - Handler: `includes/shortcodes/class-register-flow-shortcode.php`

**FluentCart Integration:**
- Vue component embedded in customer profile page
  - Location: `includes/integrations/class-fluentcart-customer-profile-integration.php`
  - Shows LINE binding status, display name, picture URL
  - REST API: `GET /wp-json/buygo-line-notify/v1/fluentcart/binding-status`
  - Files: `includes/api/class-fluentcart-integration-api.php`

**JavaScript Assets:**
- `assets/js/fluentcart-line-integration.js` - Vue component for FluentCart
- `assets/js/fluentcart-line-integration-standalone.js` - Standalone component (works outside Vue)
- All requests to REST API: `window.location.origin + '/wp-json/buygo-line-notify/v1/...'`

## Environment Configuration

**Required Environment Variables:**
- None strictly required (all settings stored in wp_options)
- Optional: `BUYGO_ENCRYPTION_KEY` in wp-config.php for custom encryption key

**Critical Settings (stored encrypted in wp_options):**
- `channel_access_token` - LINE Messaging API token (from developer.line.biz)
- `channel_secret` - LINE Messaging API secret
- `login_channel_id` - LINE Login channel ID (from developers.line.biz)
- `login_channel_secret` - LINE Login channel secret
- `default_redirect_url` - Post-login redirect destination
- `webhook_enabled` - Enable/disable webhook processing

**Secrets Location:**
- All stored in `wp_options` table, encrypted with `BUYGO_ENCRYPTION_KEY`
- Never exposed via REST API without authentication
- Database backup required for recovery

## REST API Endpoints

**Base Namespace:** `buygo-line-notify/v1`

**Public Endpoints:**
- `POST /webhook` - LINE webhook receiver (public, signature-verified)
  - No authentication required, signature-based validation
  - File: `includes/api/class-webhook-api.php`

**Authenticated Endpoints (WordPress nonce/user):**
- `GET /login/authorize` - Start LINE login flow
- `POST /login/callback` - Handle OAuth callback
- `GET /fluentcart/binding-status` - Get LINE binding status
- `POST /fluentcart/bind` - Link WordPress user to LINE account
- `POST /fluentcart/unbind` - Unlink accounts
  - Files: `includes/api/class-login-api.php`, `includes/api/class-fluentcart-integration-api.php`

**Debug Endpoints (admin only):**
- `GET /debug/webhook-logs` - View webhook logs
- `GET /debug/message-logs` - View message logs
  - File: `includes/api/class-debug-api.php`

---

*Integration audit: 2026-01-31*
