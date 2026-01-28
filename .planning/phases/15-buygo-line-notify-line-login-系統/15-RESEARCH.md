# Phase 15: buygo-line-notify LINE Login 系統 - Research

**Researched:** 2026-01-29
**Domain:** LINE Login OAuth 2.0, WordPress Social Login, Session/State Management
**Confidence:** HIGH

## Summary

本研究調查了實作 LINE Login OAuth 2.0 系統的最佳實踐，涵蓋授權流程、安全機制（State 驗證、CSRF 防護）、持久化儲存策略、WordPress 用戶建立與綁定，以及 LINE 內建瀏覽器的特殊處理。研究基於 LINE 官方文件（v2.1 API）、WordPress OAuth 實作慣例和 2026 年最新的安全標準。

重點發現：
1. **OAuth Flow**: LINE Login v2.1 使用標準 OAuth 2.0 授權碼流程 + OpenID Connect（authorize → callback → token exchange → profile）
2. **State 驗證**: 必須使用隨機生成的 state 參數防止 CSRF 攻擊，建議 32 字元以上（bin2hex(random_bytes(16))）
3. **持久化儲存**: Session + Transient 雙重策略（Session 優先，Transient 作為 fallback），解決 LINE 瀏覽器 cookie 限制
4. **Cookie SameSite**: 必須設為 `Lax`（不是 `Strict`），否則 OAuth callback 時 cookie 會被阻擋
5. **User 建立**: 從 LINE Profile API 取得 userId、displayName、pictureUrl，建立 WordPress 用戶或綁定現有用戶
6. **混合儲存**: user_meta（快速查詢）+ bindings 表（完整歷史）同時寫入
7. **bot_prompt=aggressive**: 強制引導用戶加入 LINE 官方帳號（確保可發送 Push Message）

**Primary recommendation:** 採用 LINE Login v2.1 API，使用 Session + Transient 雙重儲存 State 參數，Cookie SameSite=Lax，從 LINE Profile 建立/綁定 WordPress 用戶，混合儲存 LINE UID（user_meta + bindings 表），強制引導加入官方帳號（bot_prompt=aggressive）。

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| LINE Login API | v2.1 | OAuth 授權和用戶資料 | LINE 官方 API，支援 OpenID Connect，v2.0 已棄用 |
| WordPress User API | 內建 | 用戶建立和管理 | WordPress 標準用戶系統，支援 wp_create_user、update_user_meta |
| WordPress Session | 內建 (PHP Session) | State 參數儲存 | 標準 PHP session 機制（需手動 session_start） |
| WordPress Transient | 內建 | State 參數備援儲存 | WordPress 原生快取 API，支援自動過期 |
| OpenSSL | PHP 內建 | 加密 Channel Secret | 業界標準加密函式庫 |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| WordPress REST API | 內建 | OAuth callback endpoint | 註冊 `/wp-json/buygo-line-notify/v1/login/callback` 端點 |
| WordPress Cookies API | 內建 | 設定登入 Cookie（SameSite=Lax） | OAuth callback 後設定 WordPress 認證 cookie |
| WordPress Options API | 內建 | 儲存 Channel ID/Secret | 加密儲存敏感設定（已有 SettingsService） |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Session + Transient | 純 Transient | Transient 可能提前失效（object cache eviction），Session 更可靠但 LINE 瀏覽器可能清除 |
| user_meta + bindings 表 | 僅 user_meta | user_meta 查詢快但無歷史記錄；bindings 表保留完整綁定/解綁歷史 |
| SameSite=Lax | SameSite=Strict | Strict 會阻擋 OAuth callback 的 cookie（redirect 視為 cross-site） |
| LINE Login v2.1 | LINE Login v2.0 | v2.0 已棄用（EOL TBD），v2.1 支援 2FA 和更好的安全性 |

**Installation:**
```bash
# 無需安裝，全部使用 WordPress 和 PHP 內建功能
# LINE Login Channel 需在 LINE Developers Console 建立
```

## Architecture Patterns

### Recommended Project Structure
```
includes/
├── api/
│   ├── class-login-api.php           # OAuth authorize/callback REST endpoints
│   └── class-webhook-api.php         # 已存在（Phase 14）
├── services/
│   ├── class-login-service.php       # OAuth 流程邏輯（authorize、callback、token exchange）
│   ├── class-state-manager.php       # State 參數產生與驗證（Session + Transient）
│   ├── class-user-service.php        # WordPress 用戶建立/綁定
│   └── class-line-user-service.php   # LINE Profile API 查詢（新增）
└── class-plugin.php                  # 註冊 REST routes（onInit）
```

### Pattern 1: OAuth Authorization Flow

**What:** LINE Login 標準 OAuth 2.0 授權碼流程
**When to use:** 所有需要用戶 LINE 登入/綁定的場景
**Example:**
```php
// Source: LINE Developers - Integrating LINE Login
// https://developers.line.biz/en/docs/line-login/integrate-line-login/

// Step 1: 產生 State 並儲存（防 CSRF）
$state = bin2hex(random_bytes(16)); // 32 字元隨機字串
StateManager::store($state); // Session + Transient 雙重儲存

// Step 2: 重導向到 LINE 授權頁面
$params = [
    'response_type' => 'code',
    'client_id'     => SettingsService::get('login_channel_id'),
    'redirect_uri'  => rest_url('buygo-line-notify/v1/login/callback'),
    'state'         => $state,
    'scope'         => 'profile openid email',
    'bot_prompt'    => 'aggressive', // 強制引導加入 LINE 官方帳號
];
$auth_url = 'https://access.line.me/oauth2/v2.1/authorize?' . http_build_query($params);
wp_redirect($auth_url);
exit;

// Step 3: Callback 接收 code 和 state
public function handle_callback($request) {
    $code = $request->get_param('code');
    $state = $request->get_param('state');

    // 驗證 state（防 CSRF）
    if (!StateManager::verify($state)) {
        return new \WP_Error('invalid_state', 'Invalid state parameter', ['status' => 400]);
    }

    // Step 4: 用 code 換取 access_token
    $token_response = $this->exchange_token($code);
    $access_token = $token_response['access_token'];
    $id_token = $token_response['id_token'];

    // Step 5: 取得 LINE Profile
    $profile = $this->get_line_profile($access_token);

    // Step 6: 建立或綁定 WordPress 用戶
    $user_id = UserService::create_or_bind_user($profile);

    // Step 7: WordPress 登入
    wp_set_auth_cookie($user_id);

    // Step 8: 重導向到原始頁面或首頁
    wp_redirect(home_url());
    exit;
}
```

**來源：** [LINE Developers - Integrating LINE Login](https://developers.line.biz/en/docs/line-login/integrate-line-login/), [LINE Login v2.1 API Reference](https://developers.line.biz/en/reference/line-login/)

### Pattern 2: State 參數管理（Session + Transient 雙重策略）

**What:** 產生、儲存和驗證 OAuth State 參數，防止 CSRF 攻擊
**When to use:** 所有 OAuth 授權流程
**Example:**
```php
// Source: 基於 Nextend Social Login 架構 + WooCommerce Notify workarounds
class StateManager {
    /**
     * 產生並儲存 State 參數
     *
     * @return string 32 字元隨機 state
     */
    public static function generate(): string {
        // 使用 cryptographically secure 隨機產生器
        $state = bin2hex(random_bytes(16)); // 32 字元

        self::store($state);

        return $state;
    }

    /**
     * 儲存 State（雙重策略：Session 優先，Transient 備援）
     *
     * @param string $state State 參數
     */
    public static function store(string $state): void {
        // 策略 1: PHP Session（可靠但 LINE 瀏覽器可能清除）
        if (!session_id()) {
            session_start();
        }
        $_SESSION['buygo_line_oauth_state'] = $state;

        // 策略 2: WordPress Transient（備援，10 分鐘有效期）
        set_transient('buygo_line_oauth_state_' . $state, true, 600);

        // 策略 3: WordPress Option（最終備援，手動清理）
        // 注意：僅在 Session 和 Transient 都失效時使用
        update_option('buygo_line_oauth_state_' . md5($state), [
            'state' => $state,
            'created_at' => time(),
        ]);
    }

    /**
     * 驗證 State 參數（多重 fallback）
     *
     * @param string $state 要驗證的 state
     * @return bool
     */
    public static function verify(string $state): bool {
        if (empty($state)) {
            return false;
        }

        // Fallback 1: 檢查 Session
        if (!session_id()) {
            session_start();
        }
        if (isset($_SESSION['buygo_line_oauth_state'])
            && hash_equals($_SESSION['buygo_line_oauth_state'], $state)) {
            self::cleanup($state);
            return true;
        }

        // Fallback 2: 檢查 Transient
        if (get_transient('buygo_line_oauth_state_' . $state)) {
            self::cleanup($state);
            return true;
        }

        // Fallback 3: 檢查 Option（驗證時間未過期）
        $option = get_option('buygo_line_oauth_state_' . md5($state));
        if ($option && isset($option['state']) && hash_equals($option['state'], $state)) {
            // 檢查是否在 10 分鐘內
            if ((time() - $option['created_at']) < 600) {
                self::cleanup($state);
                return true;
            }
        }

        return false;
    }

    /**
     * 清理 State（驗證後或過期）
     *
     * @param string $state State 參數
     */
    private static function cleanup(string $state): void {
        // 清理 Session
        if (isset($_SESSION['buygo_line_oauth_state'])) {
            unset($_SESSION['buygo_line_oauth_state']);
        }

        // 清理 Transient
        delete_transient('buygo_line_oauth_state_' . $state);

        // 清理 Option
        delete_option('buygo_line_oauth_state_' . md5($state));
    }
}
```

**重要：** 三層 fallback 確保在各種環境（包括 LINE 瀏覽器）都能正常運作。

**來源：** [WordPress OAuth Server - Enforce State Parameter](https://developers.miniorange.com/docs/oauth/wordpress/server/enforce-state-parameters), [Curity - OAuth Cookie Best Practices](https://curity.io/resources/learn/oauth-cookie-best-practices/)

### Pattern 3: Token Exchange（Authorization Code → Access Token）

**What:** 用 authorization code 換取 access_token 和 id_token
**When to use:** OAuth callback 接收到 code 後
**Example:**
```php
// Source: LINE Developers - LINE Login v2.1 API Reference
// https://developers.line.biz/en/reference/line-login/

private function exchange_token(string $code): array {
    $url = 'https://api.line.me/oauth2/v2.1/token';

    $params = [
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'redirect_uri'  => rest_url('buygo-line-notify/v1/login/callback'),
        'client_id'     => SettingsService::get('login_channel_id'),
        'client_secret' => SettingsService::get('login_channel_secret'),
    ];

    $response = wp_remote_post($url, [
        'body'    => $params,
        'headers' => [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ],
    ]);

    if (is_wp_error($response)) {
        throw new \Exception('Token exchange failed: ' . $response->get_error_message());
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    // 檢查錯誤
    if (isset($body['error'])) {
        throw new \Exception('LINE API error: ' . $body['error_description']);
    }

    // 返回 token 資料
    return [
        'access_token'  => $body['access_token'],  // 有效期 30 天
        'refresh_token' => $body['refresh_token'], // 有效期 90 天
        'id_token'      => $body['id_token'],      // JWT，含用戶基本資料
        'expires_in'    => $body['expires_in'],    // 秒數
        'scope'         => $body['scope'],         // 已授權的權限
    ];
}
```

**驗證建議：** 可選擇性驗證 id_token（JWT）或直接用 access_token 呼叫 Profile API。

**來源：** [LINE Login v2.1 API Reference](https://developers.line.biz/en/reference/line-login/)

### Pattern 4: LINE Profile 查詢

**What:** 用 access_token 取得 LINE 用戶個人資料
**When to use:** Token exchange 後，建立/綁定 WordPress 用戶前
**Example:**
```php
// Source: LINE Developers - LINE Login v2.1 API Reference
// 兩種方式擇一：/v2/profile 或 /oauth2/v2.1/userinfo

private function get_line_profile(string $access_token): array {
    // 方式 1: /v2/profile（簡單，僅返回基本資料）
    $url = 'https://api.line.me/v2/profile';

    $response = wp_remote_get($url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $access_token,
        ],
    ]);

    if (is_wp_error($response)) {
        throw new \Exception('Profile fetch failed: ' . $response->get_error_message());
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    return [
        'userId'        => $body['userId'],        // LINE User ID（唯一識別）
        'displayName'   => $body['displayName'],   // 顯示名稱
        'pictureUrl'    => $body['pictureUrl'],    // 頭像 URL
        'statusMessage' => $body['statusMessage'] ?? '', // 狀態訊息（選填）
    ];

    // 方式 2: /oauth2/v2.1/userinfo（支援 OpenID Connect，含 email）
    // $url = 'https://api.line.me/oauth2/v2.1/userinfo';
    // 返回欄位：sub（userId）、name、picture、email（需授權）
}
```

**頭像處理：** pictureUrl 支援 `/large` 和 `/small` 後綴調整大小。

**來源：** [LINE Login v2.1 API Reference](https://developers.line.biz/en/reference/line-login/)

### Pattern 5: WordPress 用戶建立/綁定（混合儲存）

**What:** 從 LINE Profile 建立新 WordPress 用戶或綁定現有用戶
**When to use:** LINE Login callback 取得 Profile 後
**Example:**
```php
// Source: 基於 WordPress Social Login 最佳實踐
class UserService {
    /**
     * 建立或綁定 LINE 用戶
     *
     * @param array $line_profile LINE Profile 資料
     * @return int WordPress User ID
     */
    public static function create_or_bind_user(array $line_profile): int {
        global $wpdb;

        $line_uid = $line_profile['userId'];
        $display_name = $line_profile['displayName'];
        $picture_url = $line_profile['pictureUrl'];

        // 檢查是否已綁定（查詢 bindings 表）
        $table_name = $wpdb->prefix . 'buygo_line_bindings';
        $binding = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE line_uid = %s AND status = 'active'",
            $line_uid
        ));

        if ($binding) {
            // 已綁定：返回 WordPress User ID
            return (int) $binding->user_id;
        }

        // 未綁定：檢查當前是否已登入 WordPress
        $current_user_id = get_current_user_id();

        if ($current_user_id > 0) {
            // 已登入：綁定到當前用戶
            self::bind_line_user($current_user_id, $line_profile);
            return $current_user_id;
        }

        // 未登入：建立新 WordPress 用戶
        return self::create_wordpress_user($line_profile);
    }

    /**
     * 建立新 WordPress 用戶
     *
     * @param array $line_profile LINE Profile 資料
     * @return int WordPress User ID
     */
    private static function create_wordpress_user(array $line_profile): int {
        $line_uid = $line_profile['userId'];
        $display_name = $line_profile['displayName'];
        $picture_url = $line_profile['pictureUrl'];

        // 產生唯一的 username（LINE 不提供 username）
        $username = 'line_' . substr($line_uid, 0, 20);

        // 檢查 username 是否已存在
        $suffix = 1;
        $base_username = $username;
        while (username_exists($username)) {
            $username = $base_username . '_' . $suffix++;
        }

        // 建立用戶（無 email 時使用假 email）
        $user_id = wp_create_user(
            $username,
            wp_generate_password(32), // 隨機密碼（用戶無法用密碼登入）
            $line_profile['email'] ?? $username . '@line.local' // 假 email
        );

        if (is_wp_error($user_id)) {
            throw new \Exception('Failed to create user: ' . $user_id->get_error_message());
        }

        // 更新用戶資料
        wp_update_user([
            'ID'           => $user_id,
            'display_name' => $display_name,
            'nickname'     => $display_name,
        ]);

        // 下載並設定頭像（WordPress avatar）
        if (!empty($picture_url)) {
            self::set_user_avatar($user_id, $picture_url);
        }

        // 綁定 LINE（混合儲存：user_meta + bindings 表）
        self::bind_line_user($user_id, $line_profile);

        return $user_id;
    }

    /**
     * 綁定 LINE 到 WordPress 用戶（混合儲存）
     *
     * @param int $user_id WordPress User ID
     * @param array $line_profile LINE Profile 資料
     */
    private static function bind_line_user(int $user_id, array $line_profile): void {
        global $wpdb;

        $line_uid = $line_profile['userId'];
        $display_name = $line_profile['displayName'];
        $picture_url = $line_profile['pictureUrl'];

        // 儲存 1: user_meta（快速查詢）
        update_user_meta($user_id, 'buygo_line_uid', $line_uid);
        update_user_meta($user_id, 'buygo_line_display_name', $display_name);
        update_user_meta($user_id, 'buygo_line_picture_url', $picture_url);

        // 儲存 2: bindings 表（完整歷史）
        $table_name = $wpdb->prefix . 'buygo_line_bindings';
        $wpdb->replace(
            $table_name,
            [
                'user_id'      => $user_id,
                'line_uid'     => $line_uid,
                'display_name' => $display_name,
                'picture_url'  => $picture_url,
                'status'       => 'active',
            ],
            ['%d', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * 下載並設定用戶頭像
     *
     * @param int $user_id WordPress User ID
     * @param string $picture_url LINE 頭像 URL
     */
    private static function set_user_avatar(int $user_id, string $picture_url): void {
        // 使用 ImageUploader service（已存在於 Phase 1）
        $image_uploader = \BuygoLineNotify\Services\ImageUploader::get_instance();

        // 下載並上傳到 Media Library
        $attachment_id = $image_uploader->download_and_upload_from_url($picture_url, $user_id);

        if ($attachment_id) {
            // 儲存為用戶頭像（WordPress avatar）
            update_user_meta($user_id, 'buygo_line_avatar_attachment_id', $attachment_id);
        }
    }
}
```

**混合儲存原因：** user_meta 查詢快（get_user_meta），bindings 表保留歷史（綁定時間、解綁記錄）。

**來源：** [WordPress User Metadata](https://developer.wordpress.org/plugins/users/working-with-user-metadata/), [WordPress Social Login Best Practices](https://w3guy.com/wordpress-social-login/)

### Pattern 6: Cookie SameSite 設定

**What:** 設定 WordPress 認證 cookie 的 SameSite 屬性為 Lax
**When to use:** OAuth callback 後設定登入 cookie
**Example:**
```php
// Source: WordPress SameSite Cookies 最佳實踐
// https://core.trac.wordpress.org/ticket/37000

// WordPress 5.3+ 支援 SameSite，但預設可能是 Strict
// 需確保 OAuth callback 時 SameSite=Lax

public function handle_callback($request) {
    // ... 驗證 state、換取 token、取得 profile ...

    $user_id = UserService::create_or_bind_user($profile);

    // 設定 WordPress 認證 cookie（SameSite=Lax）
    // wp_set_auth_cookie() 預設行為已支援，但需確認環境
    wp_set_auth_cookie($user_id, true); // true = remember me

    // 如果需要強制設定 SameSite（WordPress 5.3+）
    // PHP 7.3+ 支援 setcookie options array
    setcookie(
        LOGGED_IN_COOKIE,
        $cookie_value,
        [
            'expires'  => time() + 14 * DAY_IN_SECONDS,
            'path'     => COOKIEPATH,
            'domain'   => COOKIE_DOMAIN,
            'secure'   => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax', // 關鍵：不用 Strict
        ]
    );

    // 重導向（SameSite=Lax 允許 GET redirect 帶 cookie）
    wp_redirect(home_url());
    exit;
}
```

**重要：** SameSite=Strict 會阻擋 OAuth callback 的 cookie（因為 LINE 重導向被視為 cross-site）。

**來源：** [WordPress SameSite Cookies Support](https://core.trac.wordpress.org/ticket/37000), [Auth0 - SameSite Cookie Attribute Changes](https://auth0.com/docs/sessions-and-cookies/samesite-cookie-attribute-changes)

### Pattern 7: bot_prompt=aggressive（強制引導加入 LINE 官方帳號）

**What:** OAuth 授權時強制顯示「加入 LINE 官方帳號」畫面
**When to use:** 需要確保用戶加入官方帳號以便發送 Push Message
**Example:**
```php
// Source: LINE Developers - Add Friend Option
// https://developers.line.biz/en/docs/line-login/link-a-bot/

// 在授權 URL 加入 bot_prompt 參數
$params = [
    'response_type' => 'code',
    'client_id'     => SettingsService::get('login_channel_id'),
    'redirect_uri'  => rest_url('buygo-line-notify/v1/login/callback'),
    'state'         => $state,
    'scope'         => 'profile openid',
    'bot_prompt'    => 'aggressive', // 強制顯示加入官方帳號畫面
];

// bot_prompt 可選值：
// - 'normal': 在同意畫面顯示加入官方帳號選項（checkbox）
// - 'aggressive': 同意後開啟新畫面引導加入官方帳號（推薦）
```

**注意：** 使用 bot_prompt 需先在 LINE Developers Console 連結 Messaging API Channel 和 LINE Login Channel。

**來源：** [LINE Developers - Add Friend Option](https://developers.line.biz/en/docs/line-login/link-a-bot/)

### Anti-Patterns to Avoid

- **❌ State 使用 md5(mt_rand())** - 不夠安全，應使用 `bin2hex(random_bytes(16))` 或更長
- **❌ 純 Transient 儲存 State** - Transient 可能提前失效（object cache eviction），必須有 Session fallback
- **❌ 純 Session 儲存 State** - LINE 瀏覽器可能清除 cookie，必須有 Transient fallback
- **❌ Cookie SameSite=Strict** - OAuth callback 時 cookie 會被阻擋，必須用 Lax
- **❌ 僅儲存 user_meta** - 無法追蹤綁定歷史和解綁記錄，應同時寫入 bindings 表
- **❌ 信任 client 提交的 userId** - 必須用 access_token 向 LINE API 驗證後再建立用戶
- **❌ 未檢查 State** - 容易受到 CSRF 攻擊，必須驗證 state 參數

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| OAuth 2.0 實作 | 自訂 OAuth 流程 | 遵循 LINE Login v2.1 API 規格 | LINE 已定義完整流程（authorize、callback、token exchange），自訂容易出錯 |
| State 隨機產生 | md5(mt_rand()) 或 uniqid() | bin2hex(random_bytes(16)) | random_bytes() 是 cryptographically secure，mt_rand() 可預測 |
| JWT 解析/驗證 | 自訂 base64 解碼 | WordPress HTTP API 或不驗證（直接用 access_token 查 Profile） | id_token 驗證複雜（需檢查簽章、exp、iss），不如直接呼叫 Profile API |
| 頭像下載 | file_get_contents + wp_upload_bits | ImageUploader service（已存在） | ImageUploader 已處理錯誤、timeout、MIME type 驗證 |
| Session 管理 | 自訂 cookie 機制 | PHP Session + WordPress Transient | Session 是標準機制，Transient 提供 WordPress 原生 fallback |

**Key insight:** LINE Login 是標準 OAuth 2.0 流程，已有完整文件和最佳實踐，不要重新發明輪子。重點在於處理 LINE 瀏覽器的特殊情況（Session + Transient 雙重儲存、SameSite=Lax）。

## Common Pitfalls

### Pitfall 1: State 參數驗證失敗（Session 被清除）

**What goes wrong:** OAuth callback 時 State 驗證失敗，顯示 "Invalid state parameter"
**Why it happens:** LINE 內建瀏覽器清除了 Session cookie，或 WordPress object cache 提前清除 Transient
**How to avoid:**
- 使用 Session + Transient + Option 三層 fallback
- Session 優先（最可靠）
- Transient 備援（10 分鐘有效期）
- Option 最終備援（手動清理過期的）
- 在 authorize 時同時寫入三層，callback 時依序檢查
**Warning signs:**
- 本機測試成功，生產環境失敗
- 在 LINE 應用內瀏覽器失敗，外部瀏覽器成功
- 日誌顯示 "State not found in session/transient"

**來源：** [Nextend Social Login - Persistent Storage](https://www.voxfor.com/choosing-between-wordpress-user-meta-system-and-custom-user-related-tables/), PROJECT.md 架構決策

### Pitfall 2: OAuth Callback Cookie 被阻擋

**What goes wrong:** OAuth callback 後無法設定登入 cookie，用戶仍然是未登入狀態
**Why it happens:** Cookie SameSite=Strict 阻擋了 cross-site redirect 的 cookie
**How to avoid:**
- 確保 WordPress 認證 cookie SameSite=Lax（不是 Strict）
- WordPress 5.3+ 預設已支援，但需確認 wp-config.php 或 plugin 是否覆蓋
- 檢查 `setcookie()` 呼叫是否正確設定 `samesite` 參數
- 測試時用 Chrome DevTools 的 Network → Cookies 檢查 SameSite 值
**Warning signs:**
- OAuth 流程完成但用戶未登入
- 瀏覽器 Console 顯示 cookie 被 SameSite policy 阻擋
- 在 LINE 應用內瀏覽器失敗，外部瀏覽器成功

**來源：** [WordPress SameSite Cookies Trac](https://core.trac.wordpress.org/ticket/37000), [OAuth Cookie Best Practices](https://curity.io/resources/learn/oauth-cookie-best-practices/)

### Pitfall 3: LINE UID 重複綁定

**What goes wrong:** 一個 LINE 帳號被綁定到多個 WordPress 用戶，或一個 WordPress 用戶綁定多個 LINE 帳號
**Why it happens:** 未正確檢查 bindings 表的 UNIQUE KEY 約束，或綁定邏輯有漏洞
**How to avoid:**
- bindings 表必須有 `UNIQUE KEY idx_user_id (user_id)` 和 `UNIQUE KEY idx_line_uid (line_uid)`
- 綁定前查詢 bindings 表檢查是否已綁定
- 使用 `$wpdb->replace()` 而非 `$wpdb->insert()`（自動處理重複）
- 未登入時建立新用戶，已登入時綁定到當前用戶
**Warning signs:**
- 用戶抱怨「LINE 登入進入了別人的帳號」
- bindings 表同一個 line_uid 有多筆 active 記錄
- user_meta 和 bindings 表資料不一致

**來源：** Database.php（已有 UNIQUE KEY 定義）

### Pitfall 4: Token Exchange 失敗（redirect_uri 不一致）

**What goes wrong:** Token exchange 時 LINE API 返回錯誤 "redirect_uri mismatch"
**Why it happens:** authorize 和 callback 的 redirect_uri 不完全一致（包括 protocol、domain、path）
**How to avoid:**
- 兩處使用相同的 redirect_uri 產生邏輯：`rest_url('buygo-line-notify/v1/login/callback')`
- 確保 WordPress Site URL 和 Home URL 設定正確（wp-config.php 的 WP_HOME 和 WP_SITEURL）
- LINE Developers Console 的 Callback URL 必須完全一致（包括 https vs http）
- 本機測試時注意 http://buygo.local 和 https://test.buygo.me 的差異
**Warning signs:**
- LINE API 返回 400 錯誤：`{"error":"invalid_request","error_description":"redirect_uri mismatch"}`
- 日誌顯示 authorize 和 callback 的 redirect_uri 不同

**來源：** [LINE Login v2.1 API Reference](https://developers.line.biz/en/reference/line-login/)

### Pitfall 5: 忘記處理用戶取消授權

**What goes wrong:** 用戶在 LINE 授權畫面點擊「取消」，應用無錯誤處理，顯示空白頁或錯誤訊息
**Why it happens:** Callback 只處理 `code` 和 `state`，未處理 `error` 參數
**How to avoid:**
```php
public function handle_callback($request) {
    // 檢查用戶是否取消授權
    $error = $request->get_param('error');
    if ($error) {
        $error_description = $request->get_param('error_description');

        // 記錄日誌
        Logger::log('oauth_cancelled', [
            'error' => $error,
            'description' => $error_description,
        ]);

        // 重導向到登入頁面並顯示訊息
        wp_redirect(add_query_arg([
            'line_login' => 'cancelled',
            'message' => urlencode('您已取消 LINE 登入'),
        ], wp_login_url()));
        exit;
    }

    // ... 正常流程 ...
}
```
**Warning signs:**
- 用戶抱怨「點取消後出現錯誤」
- Callback 日誌顯示 `error=access_denied`

**來源：** OAuth 2.0 標準錯誤處理

### Pitfall 6: LINE Profile 沒有 Email

**What goes wrong:** 建立 WordPress 用戶時因為沒有 email 而失敗
**Why it happens:** LINE Login scope 未包含 `email`，或用戶未提供 email
**How to avoid:**
- `wp_create_user()` 的 email 參數允許空字串或假 email（`username@line.local`）
- WordPress 不強制要求唯一 email（可多個用戶共用相同 email）
- 建立用戶後提示用戶補充 email（optional）
**Warning signs:**
- `wp_create_user()` 返回 WP_Error：`empty_user_email`
- 用戶可以 LINE 登入但無法收到 WordPress email 通知

**來源：** [WordPress wp_create_user() Documentation](https://developer.wordpress.org/reference/functions/wp_create_user/)

## Code Examples

### Example 1: 完整的 LINE Login Service

```php
// Source: 基於 LINE Developers 文件 + WordPress 最佳實踐
namespace BuygoLineNotify\Services;

class LoginService {
    private $state_manager;
    private $user_service;
    private $logger;

    public function __construct() {
        $this->state_manager = new StateManager();
        $this->user_service = new UserService();
        $this->logger = Logger::get_instance();
    }

    /**
     * 開始 LINE Login 流程（重導向到 LINE 授權頁面）
     *
     * @param string|null $return_url 登入後返回的 URL
     */
    public function start_login(?string $return_url = null): void {
        // 產生並儲存 State
        $state = StateManager::generate();

        // 儲存 return_url（登入後返回）
        if ($return_url) {
            StateManager::store_return_url($state, $return_url);
        }

        // 建立授權 URL
        $params = [
            'response_type' => 'code',
            'client_id'     => SettingsService::get('login_channel_id'),
            'redirect_uri'  => rest_url('buygo-line-notify/v1/login/callback'),
            'state'         => $state,
            'scope'         => 'profile openid email',
            'bot_prompt'    => 'aggressive', // 強制引導加入 LINE 官方帳號
            'nonce'         => wp_create_nonce('buygo_line_login'), // 防重放攻擊
        ];

        $auth_url = 'https://access.line.me/oauth2/v2.1/authorize?' . http_build_query($params);

        // 記錄日誌
        $this->logger->log('oauth_start', [
            'state' => $state,
            'redirect_uri' => $params['redirect_uri'],
        ]);

        // 重導向
        wp_redirect($auth_url);
        exit;
    }

    /**
     * 處理 OAuth Callback
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_callback($request) {
        // 檢查用戶是否取消授權
        $error = $request->get_param('error');
        if ($error) {
            $this->logger->log('oauth_cancelled', [
                'error' => $error,
                'description' => $request->get_param('error_description'),
            ]);

            wp_redirect(add_query_arg([
                'line_login' => 'cancelled',
            ], wp_login_url()));
            exit;
        }

        // 取得參數
        $code = $request->get_param('code');
        $state = $request->get_param('state');

        if (empty($code) || empty($state)) {
            return new \WP_Error('missing_params', 'Missing code or state', ['status' => 400]);
        }

        // 驗證 State（防 CSRF）
        if (!StateManager::verify($state)) {
            $this->logger->log('oauth_state_invalid', ['state' => $state]);
            return new \WP_Error('invalid_state', 'Invalid state parameter', ['status' => 400]);
        }

        try {
            // Token Exchange
            $token_data = $this->exchange_token($code);
            $access_token = $token_data['access_token'];

            // 取得 LINE Profile
            $profile = $this->get_line_profile($access_token);

            // 建立或綁定 WordPress 用戶
            $user_id = $this->user_service->create_or_bind_user($profile);

            // 設定 WordPress 登入 Cookie
            wp_set_auth_cookie($user_id, true);

            // 記錄日誌
            $this->logger->log('oauth_success', [
                'user_id' => $user_id,
                'line_uid' => $profile['userId'],
            ]);

            // 取得 return_url
            $return_url = StateManager::get_return_url($state);
            if (!$return_url) {
                $return_url = home_url();
            }

            // 重導向
            wp_redirect($return_url);
            exit;

        } catch (\Exception $e) {
            $this->logger->log('oauth_error', [
                'error' => $e->getMessage(),
            ]);

            return new \WP_Error('oauth_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * Token Exchange
     *
     * @param string $code Authorization code
     * @return array Token 資料
     */
    private function exchange_token(string $code): array {
        $url = 'https://api.line.me/oauth2/v2.1/token';

        $params = [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => rest_url('buygo-line-notify/v1/login/callback'),
            'client_id'     => SettingsService::get('login_channel_id'),
            'client_secret' => SettingsService::get('login_channel_secret'),
        ];

        $response = wp_remote_post($url, [
            'body'    => $params,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('Token exchange failed: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            throw new \Exception('LINE API error: ' . $body['error_description']);
        }

        return $body;
    }

    /**
     * 取得 LINE Profile
     *
     * @param string $access_token Access token
     * @return array Profile 資料
     */
    private function get_line_profile(string $access_token): array {
        $url = 'https://api.line.me/v2/profile';

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
            ],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            throw new \Exception('Profile fetch failed: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['message'])) {
            throw new \Exception('LINE API error: ' . $body['message']);
        }

        return $body;
    }
}
```

### Example 2: REST API Endpoints（Login API）

```php
// Source: 基於 WordPress REST API 慣例 + Phase 14 Webhook API 架構
namespace BuygoLineNotify\Api;

class Login_API {
    private $login_service;

    public function __construct() {
        $this->login_service = new \BuygoLineNotify\Services\LoginService();
    }

    /**
     * 註冊 REST routes
     */
    public function register_routes(): void {
        // Authorize endpoint（開始 LINE Login）
        register_rest_route(
            'buygo-line-notify/v1',
            '/login/authorize',
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'handle_authorize'],
                'permission_callback' => '__return_true', // 公開端點
            ]
        );

        // Callback endpoint（OAuth callback）
        register_rest_route(
            'buygo-line-notify/v1',
            '/login/callback',
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'handle_callback'],
                'permission_callback' => '__return_true', // 公開端點
            ]
        );

        // Bind endpoint（已登入用戶綁定 LINE）
        register_rest_route(
            'buygo-line-notify/v1',
            '/login/bind',
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'handle_bind'],
                'permission_callback' => 'is_user_logged_in', // 需登入
            ]
        );
    }

    /**
     * 處理 Authorize 請求
     *
     * @param \WP_REST_Request $request
     */
    public function handle_authorize($request) {
        $return_url = $request->get_param('return_url');

        $this->login_service->start_login($return_url);
    }

    /**
     * 處理 Callback 請求
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function handle_callback($request) {
        return $this->login_service->handle_callback($request);
    }

    /**
     * 處理 Bind 請求（已登入用戶綁定 LINE）
     *
     * @param \WP_REST_Request $request
     */
    public function handle_bind($request) {
        // 已登入用戶綁定 LINE（邏輯與 authorize 相同，但會綁定到當前用戶）
        $return_url = $request->get_param('return_url');

        $this->login_service->start_login($return_url);
    }
}
```

### Example 3: 前台登入按鈕（Shortcode）

```php
// 在 Plugin::onInit 註冊 shortcode
add_shortcode('buygo_line_login', [__CLASS__, 'render_line_login_button']);

/**
 * 渲染 LINE 登入按鈕
 *
 * @param array $atts Shortcode 參數
 * @return string HTML
 */
public static function render_line_login_button($atts): string {
    $atts = shortcode_atts([
        'text'       => 'LINE 登入',
        'return_url' => '',
    ], $atts);

    // 已登入：顯示綁定按鈕
    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $line_uid = get_user_meta($user_id, 'buygo_line_uid', true);

        if ($line_uid) {
            // 已綁定：顯示 LINE 資訊
            $display_name = get_user_meta($user_id, 'buygo_line_display_name', true);
            return sprintf(
                '<div class="buygo-line-status">已綁定 LINE：%s</div>',
                esc_html($display_name)
            );
        } else {
            // 未綁定：顯示綁定按鈕
            $bind_url = rest_url('buygo-line-notify/v1/login/bind');
            if (!empty($atts['return_url'])) {
                $bind_url = add_query_arg('return_url', urlencode($atts['return_url']), $bind_url);
            }

            return sprintf(
                '<a href="%s" class="buygo-line-login-button">綁定 LINE 帳號</a>',
                esc_url($bind_url)
            );
        }
    }

    // 未登入：顯示登入按鈕
    $login_url = rest_url('buygo-line-notify/v1/login/authorize');
    if (!empty($atts['return_url'])) {
        $login_url = add_query_arg('return_url', urlencode($atts['return_url']), $login_url);
    }

    return sprintf(
        '<a href="%s" class="buygo-line-login-button">
            <img src="%s" alt="LINE Login">
            <span>%s</span>
        </a>',
        esc_url($login_url),
        plugins_url('assets/images/line-icon.png', BUYGO_LINE_NOTIFY_FILE),
        esc_html($atts['text'])
    );
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| LINE Login v2.0 | LINE Login v2.1 | 2023+ | v2.1 支援 2FA、更好的安全性，v2.0 已棄用 |
| 純 Session 儲存 State | Session + Transient + Option 三層 | 2024+ | 解決 LINE 瀏覽器和 object cache 的問題 |
| SameSite=Strict | SameSite=Lax | 2020+ | 支援 OAuth callback（Strict 會阻擋 cross-site redirect） |
| md5(mt_rand()) 產生 State | bin2hex(random_bytes(16)) | PHP 7.0+ | cryptographically secure 隨機產生器 |
| 單一 user_meta 儲存 | user_meta + bindings 表混合儲存 | 2025+ | 快速查詢 + 完整歷史記錄 |

**Deprecated/outdated:**
- **LINE Login v2.0**: 已棄用，EOL TBD，應使用 v2.1
- **md5(uniqid()) 產生 State**: 可預測，不安全，應使用 random_bytes()
- **純 Transient 儲存關鍵資料**: Transient 可能提前失效，關鍵資料應有多重 fallback
- **直接信任 client 提交的 LINE Profile**: 必須用 access_token 向 LINE API 驗證

## Open Questions

1. **Access Token 和 Refresh Token 是否需要儲存**
   - What we know: LINE access_token 有效期 30 天，refresh_token 90 天
   - What's unclear: 是否需要儲存 token 以便後續呼叫 LINE API（如發送訊息）
   - Recommendation: Phase 15 暫不儲存（僅用於登入），Phase 16（LIFF）或未來版本再決定

2. **LIFF 和 LINE Login 的關係**
   - What we know: LIFF 可以無需 OAuth redirect 直接取得 access_token（適合 LINE 瀏覽器）
   - What's unclear: 是否應該偵測 LINE 瀏覽器並自動切換到 LIFF 流程
   - Recommendation: Phase 15 先實作標準 OAuth，Phase 16 再加入 LIFF（偵測 UA 自動切換）

3. **Email 驗證和補充流程**
   - What we know: LINE Profile 可能沒有 email，WordPress 允許無 email 用戶
   - What's unclear: 是否應該強制要求用戶補充 email（如用於接收訂單通知）
   - Recommendation: 建立用戶時允許假 email，登入後提示補充（optional）

4. **多個 WordPress 站台共用 LINE Login Channel**
   - What we know: LINE Login Channel 可設定多個 Callback URL
   - What's unclear: 多站台共用 Channel 是否有安全風險或限制
   - Recommendation: 每個站台獨立 Channel（避免 redirect_uri 驗證問題）

## Sources

### Primary (HIGH confidence)
- [LINE Developers - Integrating LINE Login](https://developers.line.biz/en/docs/line-login/integrate-line-login/) - OAuth 流程和參數
- [LINE Login v2.1 API Reference](https://developers.line.biz/en/reference/line-login/) - API 端點和回應格式
- [LINE Developers - Secure Login Process](https://developers.line.biz/en/docs/line-login/secure-login-process/) - State 驗證和安全最佳實踐
- [LINE Developers - Add Friend Option](https://developers.line.biz/en/docs/line-login/link-a-bot/) - bot_prompt 參數說明
- [WordPress User Metadata](https://developer.wordpress.org/plugins/users/working-with-user-metadata/) - user_meta API 使用
- buygo-line-notify/includes/class-database.php - bindings 表結構（已驗證）

### Secondary (MEDIUM confidence)
- [WordPress OAuth Server - Enforce State Parameter](https://developers.miniorange.com/docs/oauth/wordpress/server/enforce-state-parameters) - State 參數實作建議
- [WordPress SameSite Cookies Trac](https://core.trac.wordpress.org/ticket/37000) - SameSite 支援和設定
- [OAuth Cookie Best Practices](https://curity.io/resources/learn/oauth-cookie-best-practices/) - Cookie 安全設定
- [WordPress Social Login Best Practices](https://w3guy.com/wordpress-social-login/) - 社交登入整合指南
- [Difference Between PHP Sessions and WordPress Transients](https://kishorparmar.com/blog/difference-between-php-sessions-and-wordpress-transients/) - Session vs Transient 比較

### Tertiary (LOW confidence)
- [WordPress and PHP Sessions on Pantheon](https://docs.pantheon.io/guides/php/wordpress-sessions) - 特定主機環境的 Session 處理（需根據環境調整）
- [Choosing Between User Meta and Custom Tables](https://www.voxfor.com/choosing-between-wordpress-user-meta-system-and-custom-user-related-tables/) - 效能考量（需驗證適用性）

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - LINE Login v2.1 是官方 API，WordPress User/Session/Transient 是內建功能
- Architecture: HIGH - OAuth 2.0 是標準流程，Session + Transient 雙重策略基於實際問題（PROJECT.md 記錄）
- Pitfalls: MEDIUM - 大部分來自文件和社群經驗，部分需實際測試驗證（如 LINE 瀏覽器行為）

**Research date:** 2026-01-29
**Valid until:** 2026-04-29 (90 days - LINE API 和 WordPress 相對穩定)

## Next Steps for Planning

1. **優先實作標準 OAuth 流程** - authorize、callback、token exchange、profile、user 建立/綁定
2. **State 管理是關鍵** - Session + Transient + Option 三層 fallback，確保各種環境都能運作
3. **測試 LINE 瀏覽器** - 驗證 Session cookie 是否被清除，Transient fallback 是否有效
4. **Cookie SameSite 設定** - 確認 WordPress 預設行為，必要時手動設定
5. **混合儲存驗證** - user_meta 和 bindings 表同時寫入，查詢時優先 user_meta
6. **bot_prompt=aggressive** - 確保在 LINE Developers Console 連結 Messaging API 和 LINE Login Channel
7. **錯誤處理** - 取消授權、token exchange 失敗、profile 查詢失敗、user 建立失敗
8. **日誌記錄** - 記錄 OAuth 流程每個步驟（方便除錯）
9. **LIFF 整合留待 Phase 16** - 先驗證標準 OAuth 可運作，再加入 LIFF（偵測 LINE 瀏覽器自動切換）
