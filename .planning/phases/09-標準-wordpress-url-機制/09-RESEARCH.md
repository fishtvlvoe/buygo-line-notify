# Phase 9: 標準 WordPress URL 機制 - Research

**Researched:** 2026-01-29
**Domain:** WordPress OAuth Integration / Social Login Architecture
**Confidence:** HIGH

## Summary

This phase involves migrating from REST API-based OAuth callbacks to standard WordPress URL mechanisms using the `login_init` hook and `wp-login.php` entry point. This approach follows the pattern established by Nextend Social Login and other major WordPress social login plugins.

The core technical challenge is handling OAuth callbacks through WordPress's standard login page infrastructure rather than REST API endpoints, while maintaining clean redirect behavior and implementing the NSLContinuePageRenderException pattern to support register flow pages with shortcodes.

Research focused on three domains: (1) WordPress login hooks and OAuth integration patterns, (2) the transition from REST API to standard URL architecture, and (3) exception-based flow control for page rendering. All findings verified against official WordPress documentation and established plugin patterns.

**Primary recommendation:** Use `login_init` hook with `?loginSocial=buygo-line` parameter pattern, implement NSLContinuePageRenderException for flow control, and maintain existing LoginService core logic while removing REST endpoint registration.

## Standard Stack

WordPress native infrastructure provides all necessary components for OAuth social login implementation.

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress Core | 6.0+ | login_init hook, wp_set_auth_cookie | Native WordPress login infrastructure |
| PHP Exception | 7.4+ | NSLContinuePageRenderException pattern | Standard PHP exception handling |
| WordPress Shortcode API | Core | Register flow page rendering | Native WordPress content system |
| WordPress Transient API | Core | State storage (already implemented) | Existing StateManager uses this |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| WordPress login_url filter | Core | Modify login URLs | Add loginSocial parameter dynamically |
| WordPress logout_url filter | Core | Clean up on logout | Clear LINE session data |
| wp_remote_post/get | Core | LINE OAuth API calls | Already used in LoginService |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| REST API endpoints | Standard WordPress URLs | REST API can't render HTML properly, causes display issues |
| Custom login page | wp-login.php | Custom pages require more maintenance, wp-login.php is standard |
| Session storage | Transient API (current) | StateManager already uses Transients successfully |

**Installation:**
```bash
# No external dependencies required
# All components are WordPress core functionality
```

## Architecture Patterns

### Recommended Project Structure
```
includes/
├── api/
│   └── class-login-api.php          # DEPRECATE - mark for removal
├── services/
│   ├── class-login-service.php      # KEEP - refactor method names
│   ├── class-state-manager.php      # KEEP - unchanged
│   └── class-user-service.php       # KEEP - unchanged
├── exceptions/
│   └── class-nsl-continue-page-render-exception.php  # NEW
└── handlers/
    └── class-login-handler.php      # NEW - login_init logic
```

### Pattern 1: login_init Hook Entry Point
**What:** Hook into WordPress login initialization to intercept OAuth flows
**When to use:** Detect `$_GET['loginSocial'] === 'buygo-line'` in login_init
**Example:**
```php
// Source: Nextend Social Login pattern, verified in NEXTEND-SOCIAL-LOGIN-ANALYSIS.md
add_action('login_init', function() {
    if (isset($_GET['loginSocial']) && $_GET['loginSocial'] === 'buygo-line') {
        $handler = new Login_Handler();

        // Determine action: authorize or callback
        if (isset($_GET['code'])) {
            // Callback with code
            $handler->handle_callback($_GET['code'], $_GET['state']);
        } else {
            // Initial authorize request
            $handler->handle_authorize();
        }
    }
}, 10);
```

### Pattern 2: NSLContinuePageRenderException
**What:** Exception-based flow control to continue page rendering instead of redirecting
**When to use:** When OAuth callback needs to display a form (e.g., missing email)
**Example:**
```php
// Source: NEXTEND-SOCIAL-LOGIN-ANALYSIS.md:18-41
class NSLContinuePageRenderException extends Exception {}

// In OAuth callback handler
try {
    $this->doAuthenticate();
} catch (NSLContinuePageRenderException $e) {
    // Not an error - allow page rendering to continue
    // WordPress will load the register flow page
    // Shortcode will render the form
    return;
} catch (Exception $e) {
    // Real error - handle it
    $this->onError($e);
}
```

### Pattern 3: Dynamic Shortcode Registration
**What:** Register shortcode only when needed (during OAuth flow)
**When to use:** Before throwing NSLContinuePageRenderException
**Example:**
```php
// Source: NEXTEND-SOCIAL-LOGIN-ANALYSIS.md:64-72
if ($this->isCustomRegisterFlow) {
    add_shortcode('buygo_line_register_flow', array(
        $this,
        'customRegisterFlowShortcode'
    ));
    throw new NSLContinuePageRenderException('CUSTOM_REGISTER_FLOW');
}
```

### Pattern 4: Callback URL Construction
**What:** Use WordPress standard login URL instead of REST endpoint
**When to use:** Generating LINE OAuth authorize URL
**Example:**
```php
// OLD (REST API):
$callback_url = rest_url('buygo-line-notify/v1/login/callback');

// NEW (Standard WordPress):
$callback_url = wp_login_url() . '?loginSocial=buygo-line';
// Or more explicitly:
$callback_url = site_url('wp-login.php?loginSocial=buygo-line');
```

### Pattern 5: wp_set_auth_cookie Usage
**What:** Set WordPress authentication cookie after successful OAuth
**When to use:** After verifying LINE profile and creating/logging in user
**Example:**
```php
// Source: https://developer.wordpress.org/reference/functions/wp_set_auth_cookie/
// Current implementation in class-login-api.php:188
wp_set_auth_cookie($user_id, true);  // true = remember for 14 days

// Apply login_redirect filter for compatibility
$user = get_user_by('id', $user_id);
$redirect_url = apply_filters('login_redirect', $redirect_url, '', $user);

// Then redirect
wp_redirect($redirect_url);
exit;
```

### Anti-Patterns to Avoid
- **REST API for OAuth callback**: Causes HTML rendering issues (already experienced in current implementation)
- **Direct echo in shortcode**: Shortcodes must return output, never echo
- **Hardcoded redirect URLs**: Always use filters like `login_redirect` for compatibility
- **Ignoring state verification**: Always verify and consume state tokens (already implemented correctly in StateManager)

## Don't Hand-Roll

Problems that look simple but have existing solutions:

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| OAuth state management | Custom session storage | WordPress Transient API | Already implemented in StateManager; reliable, handles expiry |
| Login cookie creation | Manual cookie setting | wp_set_auth_cookie() | Handles secure flags, httpOnly, session tokens, multiple paths |
| Login URL generation | String concatenation | wp_login_url() | Handles SSL, filters, localization |
| Page flow control | Custom routing | Exception-based flow (NSLContinuePageRenderException) | Clean separation, allows WordPress to handle rendering |
| Shortcode output | echo statements | return statements | WordPress shortcode API requirement |

**Key insight:** WordPress core provides robust, well-tested infrastructure for authentication. Using standard hooks and functions ensures compatibility with other plugins and future WordPress versions. The NSLContinuePageRenderException pattern elegantly solves the "how do I show a form in the middle of OAuth" problem without complex routing.

## Common Pitfalls

### Pitfall 1: REST API HTML Output
**What goes wrong:** REST API endpoints expect JSON responses. Outputting HTML causes raw HTML to display as text in browser
**Why it happens:** REST API sets `Content-Type: application/json` headers. Direct HTML output conflicts with this
**How to avoid:** Use standard WordPress URLs (wp-login.php) instead of REST API for OAuth callbacks
**Warning signs:** Users see HTML source code instead of rendered page; `?>` visible in browser

### Pitfall 2: Shortcode Echoing Instead of Returning
**What goes wrong:** Shortcode output appears in wrong location on page (usually at top)
**Why it happens:** WordPress shortcode API requires return values. echo outputs immediately, before WordPress positions content
**How to avoid:** Always use `return` in shortcode callbacks, never `echo` or `print`
**Warning signs:** Form appears at top of page instead of where shortcode is placed

### Pitfall 3: Cookie Not Set Across All WordPress Paths
**What goes wrong:** User appears logged in on some pages but not others
**Why it happens:** WordPress uses different cookie paths for different areas (admin, plugins, content)
**How to avoid:** Use `wp_set_auth_cookie()` which handles all paths automatically
**Warning signs:** User logged in on frontend but logged out in /wp-admin

### Pitfall 4: Not Consuming State After Verification
**What goes wrong:** Replay attacks possible; state can be reused
**Why it happens:** Forgetting to delete state after successful verification
**How to avoid:** StateManager already implements `consume_state()` - ensure it's called after verification
**Warning signs:** Same OAuth callback URL works multiple times

### Pitfall 5: Hardcoded Redirect After Login
**What goes wrong:** Breaks compatibility with other plugins (e.g., WooCommerce My Account redirects)
**Why it happens:** Not using WordPress `login_redirect` filter
**How to avoid:** Always apply `login_redirect` filter before wp_redirect
**Warning signs:** User feedback "should go to checkout but goes to homepage"

### Pitfall 6: Missing loginSocial Parameter Check
**What goes wrong:** Handler runs on all login_init calls, interfering with normal login
**Why it happens:** Not checking `$_GET['loginSocial']` parameter
**How to avoid:** Always check `if (isset($_GET['loginSocial']) && $_GET['loginSocial'] === 'buygo-line')`
**Warning signs:** Normal WordPress login stops working; errors on wp-login.php

## Code Examples

Verified patterns from official sources and existing implementation:

### Complete login_init Handler
```php
// Source: Compiled from Nextend pattern + WordPress documentation
class Login_Handler {

    private $login_service;
    private $user_service;
    private $state_manager;

    public function __construct() {
        $this->login_service = new LoginService();
        $this->user_service = new UserService();
        $this->state_manager = new StateManager();
    }

    /**
     * Hook into login_init
     */
    public function register_hooks() {
        add_action('login_init', array($this, 'handle_login_init'));
    }

    /**
     * Handle login_init hook
     */
    public function handle_login_init() {
        // Only handle if loginSocial=buygo-line
        if (!isset($_GET['loginSocial']) || $_GET['loginSocial'] !== 'buygo-line') {
            return;
        }

        try {
            if (isset($_GET['code']) && isset($_GET['state'])) {
                // OAuth callback
                $this->handle_callback($_GET['code'], $_GET['state']);
            } else {
                // Initial authorize request
                $this->handle_authorize();
            }
        } catch (NSLContinuePageRenderException $e) {
            // Allow page to continue rendering
            // (for register flow page)
            return;
        } catch (Exception $e) {
            // Real error
            wp_die('LINE Login Error: ' . esc_html($e->getMessage()));
        }
    }

    /**
     * Handle initial authorize request
     */
    private function handle_authorize() {
        // Default redirect after login
        $redirect_url = isset($_GET['redirect_to']) ? $_GET['redirect_to'] : home_url();

        // Generate authorize URL
        $authorize_url = $this->login_service->get_authorize_url($redirect_url);

        // Redirect to LINE
        wp_redirect($authorize_url);
        exit;
    }

    /**
     * Handle OAuth callback
     */
    private function handle_callback($code, $state) {
        // Handle callback (verify state + exchange token + get profile)
        $result = $this->login_service->handle_callback($code, $state);

        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }

        $profile = $result['profile'];
        $state_data = $result['state_data'];
        $line_uid = $profile['userId'];
        $redirect_url = $state_data['redirect_url'] ?? home_url();

        // Check if LINE UID already bound
        $existing_user_id = $this->user_service->get_user_by_line_uid($line_uid);

        if ($existing_user_id) {
            // Login existing user
            wp_set_auth_cookie($existing_user_id, true);

            $user = get_user_by('id', $existing_user_id);
            $redirect_url = apply_filters('login_redirect', $redirect_url, '', $user);

            wp_redirect($redirect_url);
            exit;
        }

        // Create new user or show register form
        // (Implementation continues based on requirements)
    }
}
```

### NSLContinuePageRenderException Class
```php
// Source: NEXTEND-SOCIAL-LOGIN-ANALYSIS.md:18-24
// File: includes/exceptions/class-nsl-continue-page-render-exception.php

namespace BuygoLineNotify\Exceptions;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * NSLContinuePageRenderException
 *
 * Special exception that signals "this is not an error, allow page rendering to continue"
 * Used to display register flow page with shortcode during OAuth callback
 */
class NSLContinuePageRenderException extends \Exception {
    // No additional implementation needed
    // The exception type itself is the signal
}
```

### LoginService Method Refactoring (Callback URL)
```php
// Source: Current implementation in class-login-service.php:89-90
// BEFORE (REST API):
$callback_url = rest_url('buygo-line-notify/v1/login/callback');

// AFTER (Standard WordPress URL):
$callback_url = site_url('wp-login.php?loginSocial=buygo-line');
```

### Register Flow Page Shortcode
```php
// Source: Nextend pattern + WordPress shortcode best practices
// File: includes/handlers/class-login-handler.php (add this method)

/**
 * Register flow shortcode
 * Displays form to collect missing data (e.g., email)
 */
public function register_flow_shortcode($atts) {
    // Get stored profile data from transient
    $temp_data = get_transient('buygo_line_register_temp_' . get_current_user_id());

    if (!$temp_data) {
        return '<p>Registration session expired. Please try again.</p>';
    }

    $profile = $temp_data['profile'];

    // Build form HTML
    ob_start();
    ?>
    <div class="buygo-line-register-form">
        <h2>Complete Your Registration</h2>
        <form method="post" action="">
            <?php wp_nonce_field('buygo_line_register', 'buygo_line_nonce'); ?>

            <p>
                <label for="user_email">Email Address:</label>
                <input type="email" name="user_email" id="user_email" required
                       value="<?php echo esc_attr($profile['email'] ?? ''); ?>">
            </p>

            <p>
                <input type="submit" value="Complete Registration">
            </p>
        </form>
    </div>
    <?php
    return ob_get_clean(); // IMPORTANT: return, not echo
}
```

### login_url Filter Integration
```php
// Source: WordPress filter documentation + Nextend pattern
// File: includes/handlers/class-login-handler.php or main plugin file

/**
 * Add loginSocial parameter to login URL when appropriate
 */
add_filter('login_url', function($login_url, $redirect, $force_reauth) {
    // Only add if LINE login is enabled
    if (SettingsService::get('login_enabled')) {
        $login_url = add_query_arg('loginSocial', 'buygo-line', $login_url);
    }
    return $login_url;
}, 10, 3);
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| REST API OAuth callbacks | Standard WordPress URLs (wp-login.php) | 2020-2025 | Better HTML rendering, plugin compatibility |
| Custom routing for forms | Exception-based flow (NSLContinuePageRenderException) | Nextend adoption | Cleaner code, leverages WordPress rendering |
| Manual cookie handling | wp_set_auth_cookie() | Always standard | More secure, handles edge cases |
| Custom session storage | WordPress Transient API | Already adopted in StateManager | Reliable, no setup needed |

**Deprecated/outdated:**
- **REST API for social login callbacks**: Major plugins (Nextend, OneAll) use standard WordPress URLs
- **Custom login pages for OAuth**: wp-login.php is the standard, custom pages add maintenance burden
- **Manual state storage in database**: Transient API is simpler and handles expiry automatically

## Open Questions

### 1. **Register Flow Page Location**
   - What we know: Nextend requires admin to create page and insert shortcode
   - What's unclear: Should we auto-create page during activation, or require manual setup?
   - Recommendation: Require manual setup (more flexible), provide clear instructions in settings

### 2. **Email Collection Strategy**
   - What we know: LINE may not provide email; need to collect it somehow
   - What's unclear: Should we generate temp email (like `line_UID@buygo-temp.local`) or block registration?
   - Recommendation: Research separately in Phase 10 (Register Flow Page implementation)

### 3. **Backward Compatibility**
   - What we know: Existing REST API endpoints will be removed
   - What's unclear: Are there external integrations relying on REST endpoints?
   - Recommendation: Deprecate in this phase, fully remove in later phase; add deprecation notices

### 4. **Error Display Method**
   - What we know: wp_die() shows WordPress error page; redirect with query params shows custom error
   - What's unclear: Which provides better UX for LINE login errors?
   - Recommendation: Use wp_die() for critical errors, query params for user-recoverable errors

## Sources

### Primary (HIGH confidence)
- [WordPress login_init Hook Documentation](https://developer.wordpress.org/reference/hooks/login_init/) - Official WordPress Developer Resources
- [WordPress wp_set_auth_cookie() Documentation](https://developer.wordpress.org/reference/functions/wp_set_auth_cookie/) - Official WordPress Developer Resources
- NEXTEND-SOCIAL-LOGIN-ANALYSIS.md - Internal reverse engineering document (2026-01-29)
- class-state-manager.php - Existing implementation (already uses best practices)
- class-login-service.php - Existing implementation (core logic to preserve)

### Secondary (MEDIUM confidence)
- [Nextend Social Login Official Documentation](https://social-login.nextendweb.com/) - loginSocial parameter pattern
- [WordPress Shortcode API - Plugin Handbook](https://developer.wordpress.org/plugins/shortcodes/) - Shortcode best practices
- [WordPress REST API Authentication](https://developer.wordpress.org/rest-api/using-the-rest-api/authentication/) - Cookie authentication limitations
- [WordPress OAuth 2.0 Best Practices (2025)](https://oddjar.com/wordpress-rest-api-authentication-guide-2025/) - Security implementation guide

### Tertiary (LOW confidence)
- Various WordPress social login plugins (OneAll, miniOrange, WP Social) - Pattern confirmation
- WordPress security best practices articles (2026) - General authentication guidance

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - All components are WordPress core, well-documented
- Architecture: HIGH - Nextend pattern verified, existing implementation provides validation
- Pitfalls: HIGH - Based on current implementation issues + WordPress documentation

**Research date:** 2026-01-29
**Valid until:** 2026-04-29 (90 days - WordPress core is stable, login infrastructure rarely changes)

---

## Additional Notes

### Integration Points with Existing Code

**Keep unchanged:**
- `class-state-manager.php` - Already uses Transient API correctly
- `class-login-service.php` methods: `handle_callback()`, `exchange_token()`, `get_profile()` - Core OAuth logic is solid
- `class-user-service.php` - User creation and binding logic

**Modify:**
- `class-login-service.php` method `get_authorize_url()` - Change callback URL from REST to standard WordPress
- Remove: `class-login-api.php` REST endpoint registration

**Create new:**
- `class-nsl-continue-page-render-exception.php` - Exception class
- `class-login-handler.php` - login_init hook handler
- Shortcode registration for register flow page

### Testing Considerations

**Must test:**
1. OAuth flow with `wp-login.php?loginSocial=buygo-line`
2. Callback handling with state verification
3. wp_set_auth_cookie() sets cookies correctly across all paths
4. login_redirect filter compatibility
5. Shortcode rendering (return vs echo)

**Edge cases:**
1. State expiry during OAuth flow
2. Multiple simultaneous OAuth attempts
3. LINE browser cookie limitations (original problem NSLContinuePageRenderException solves)
4. Conflict with other login plugins

### Migration Path

Phase 9 focus: **Infrastructure change only** (REST API → Standard WordPress URL)
- Keep: All business logic (user creation, binding, profile sync)
- Change: Entry point and flow control mechanism
- Defer: Register flow page implementation (Phase 10)

This allows incremental migration with verification at each step.
