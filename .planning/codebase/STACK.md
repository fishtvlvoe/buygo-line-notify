# Technology Stack

**Analysis Date:** 2026-01-31

## Languages

**Primary:**
- PHP 8.0+ - WordPress plugin core logic, REST API handlers, services
- JavaScript (Vanilla & ES6) - Frontend integration scripts for FluentCart and shortcodes
- HTML/CSS - Admin pages, shortcodes, widget styling

**Secondary:**
- SQL - Database queries and table operations via WordPress dbDelta()

## Runtime

**Environment:**
- WordPress 5.8+ (as per composer.json requirements)
- PHP 8.0, 8.1, 8.2, 8.3 compatible

**Package Manager:**
- Composer - PHP dependency management
- Lockfile: Not detected in codebase (dev dependencies only)

## Frameworks

**Core:**
- WordPress 5.8+ - Plugin framework, hooks/filters, REST API, database abstraction
- WordPress REST API v2 - Custom endpoint registration for webhooks and authentication

**Testing:**
- PHPUnit 9.6 - Unit test runner
- Yoast PHPUnit Polyfills 2.0 - WordPress compatibility layer for tests

**Build/Dev:**
- None detected (pure PHP plugin, no build step required)

## Key Dependencies

**Testing (Dev):**
- phpunit/phpunit 9.6 - Unit test framework
- yoast/phpunit-polyfills 2.0 - WordPress testing utilities and assertions

**No Production Dependencies:**
- Plugin uses only WordPress built-in functions (wp_remote_*, wp_json_encode, openssl_*)
- All external API calls use WordPress HTTP client (wp_remote_post, wp_remote_get)

## Configuration

**Environment:**
- WordPress `wp-config.php` constants:
  - `BUYGO_ENCRYPTION_KEY` - Optional encryption key for sensitive settings (defaults to 'buygo-secret-key-default' if not defined)
  - `WP_DEBUG` and `WP_DEBUG_LOG` - Logging configuration

**Plugin Constants:**
- `BuygoLineNotify_PLUGIN_VERSION` (0.1.1)
- `BuygoLineNotify_PLUGIN_DIR` - Plugin directory path
- `BuygoLineNotify_PLUGIN_URL` - Plugin URL for assets

**Database:**
- Autoload via WordPress option: `buygo_line_db_version`
- Tables created: `wp_buygo_line_bindings`, `wp_buygo_line_users`, `wp_buygo_line_webhook_logs`, `wp_buygo_line_message_logs`

## Platform Requirements

**Development:**
- PHP 8.0+ with OpenSSL extension (for encryption)
- WordPress 5.8+ with REST API enabled
- MySQL 5.7+ or MariaDB 10.2+
- Composer (for test setup)

**Production:**
- WordPress 5.8+ hosted environment
- PHP 8.0+ with OpenSSL support
- Active internet connection for LINE API calls
- Must run as WordPress plugin in `/wp-content/plugins/buygo-line-notify/`

## Encryption

**Algorithm:** AES-128-ECB via PHP openssl_encrypt/openssl_decrypt
**Key Source:**
- Primary: `BUYGO_ENCRYPTION_KEY` constant from wp-config.php
- Fallback: Hard-coded default 'buygo-secret-key-default' (security risk)

**Encrypted Fields:**
- `channel_access_token` - LINE Messaging API token
- `channel_secret` - LINE Messaging API secret
- `login_channel_id` - LINE Login channel ID
- `login_channel_secret` - LINE Login channel secret

## Hash Functions

**Signature Verification:** HMAC-SHA256
- Used for LINE webhook signature validation
- Safe comparison via `hash_equals()` (prevents timing attacks)

---

*Stack analysis: 2026-01-31*
