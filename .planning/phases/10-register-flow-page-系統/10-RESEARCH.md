# Phase 10: Register Flow Page 系統 - Research

**Researched:** 2026-01-29
**Domain:** WordPress Shortcode API + OAuth Callback Page Rendering + User Registration
**Confidence:** HIGH

## Summary

本研究聚焦於實作 Register Flow Page 機制，讓 OAuth callback 完成後可在任意頁面顯示註冊表單。這是 Nextend Social Login 架構的核心模式，透過 NSLContinuePageRenderException 例外控制流程，讓 WordPress 繼續渲染頁面而非重定向，再由動態註冊的 shortcode 在頁面中顯示註冊表單。

Phase 9 已完成的基礎設施（Login_Handler、NSLContinuePageRenderException、StateManager）為本階段提供了堅實的基礎。本階段的核心任務是：(1) 在 OAuth callback 完成後正確儲存 LINE profile 到持久化系統；(2) 動態註冊 shortcode 並拋出例外；(3) 實作 shortcode handler 渲染註冊表單；(4) 實作表單提交處理建立用戶並綁定 LINE。

**Primary recommendation:** 使用 WordPress Transient API 儲存 LINE profile（與 StateManager 相同機制），在 Login_Handler 中 catch 到 NSLContinuePageRenderException 後動態註冊 shortcode，讓 WordPress 繼續載入 Register Flow Page 渲染表單。

## Standard Stack

本階段完全使用 WordPress Core API，不需要額外套件。

### Core

| Component | Version | Purpose | Why Standard |
|-----------|---------|---------|--------------|
| WordPress Shortcode API | 6.x | 動態註冊 shortcode 顯示註冊表單 | WordPress 原生 API，穩定、廣泛支援 |
| WordPress Transient API | 6.x | 持久化儲存 LINE profile（跨請求） | 支援自動過期、物件快取整合、適合臨時資料 |
| wp_insert_user() | 6.x | 建立 WordPress 用戶 | WordPress 原生函數，完整驗證和 hooks |
| wp_set_auth_cookie() | 6.x | 自動登入新註冊用戶 | WordPress 原生認證機制 |
| wp_dropdown_pages() | 6.x | 後台頁面選擇器 | WordPress 原生設定 UI 元件 |

### Supporting

| Component | Version | Purpose | When to Use |
|-----------|---------|---------|-------------|
| sanitize_user() | 6.x | 清理用戶名 | 處理 LINE displayName 作為用戶名時 |
| is_email() | 6.x | 驗證 email 格式 | 表單提交驗證 |
| username_exists() | 6.x | 檢查用戶名是否存在 | 避免重複用戶名 |
| email_exists() | 6.x | 檢查 email 是否存在 | Auto-link 判斷 |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Transient API | PHP Session | Session 在某些主機不可用，REST API 不支援 |
| wp_insert_user() | wp_create_user() | wp_insert_user() 支援更多參數（display_name, role） |
| Shortcode | Block | Shortcode 更簡單、相容性更好、符合 Nextend 模式 |

## Architecture Patterns

### Recommended Project Structure

```
includes/
├── handlers/
│   └── class-login-handler.php     # 擴展: 儲存 profile + 動態註冊 shortcode
├── shortcodes/
│   └── class-register-flow-shortcode.php  # 新增: Shortcode handler
├── services/
│   ├── class-state-manager.php     # 已存在: 擴展儲存 LINE profile
│   ├── class-user-service.php      # 已存在: 用戶建立
│   └── class-line-user-service.php # 已存在: LINE 綁定
├── admin/
│   └── class-settings-page.php     # 擴展: Register Flow Page 選擇器
└── exceptions/
    └── class-nsl-continue-page-render-exception.php  # 已存在
```

### Pattern 1: NSLContinuePageRenderException + Dynamic Shortcode

**What:** 在 OAuth callback 完成後，先儲存 LINE profile 到 Transient，動態註冊 shortcode，然後拋出 NSLContinuePageRenderException。Login_Handler 捕捉到例外後 return（讓 WordPress 繼續），WordPress 載入 Register Flow Page，shortcode 被執行，渲染註冊表單。

**When to use:** 新用戶需要顯示註冊表單時（LINE UID 未在 wp_buygo_line_users 中找到對應用戶）

**Example:**

```php
// Source: Phase 9 Login_Handler + Nextend Social Login 架構
// 在 Login_Handler::handle_callback() 中

// 1. LINE profile 已取得（$profile）
// 2. 查詢用戶發現是新用戶
$user_id = LineUserService::getUserByLineUid($line_uid);

if (!$user_id) {
    // 3. 儲存 LINE profile 到 Transient（供 shortcode 使用）
    $profile_key = 'buygo_line_profile_' . $state;
    set_transient($profile_key, [
        'profile'    => $profile,
        'state_data' => $state_data,
        'state'      => $state,  // 用於表單驗證
    ], 600);  // 10 分鐘

    // 4. 動態註冊 shortcode
    add_shortcode('buygo_line_register_flow', [
        RegisterFlowShortcode::class,
        'render'
    ]);

    // 5. 拋出例外（讓頁面繼續渲染）
    throw new NSLContinuePageRenderException(
        NSLContinuePageRenderException::FLOW_REGISTER,
        [
            'profile_key' => $profile_key,
            'profile'     => $profile,
            'state_data'  => $state_data,
        ]
    );
}
```

### Pattern 2: Shortcode with Transient Lookup

**What:** Shortcode handler 從 Transient 讀取 LINE profile，若找不到則顯示錯誤訊息。

**When to use:** Shortcode 被執行時

**Example:**

```php
// Source: WordPress Shortcode API + Transient API
class RegisterFlowShortcode {
    public static function render($atts): string {
        // 從 URL 參數取得 state（由 Login_Handler 傳遞）
        $state = isset($_GET['state']) ? sanitize_text_field($_GET['state']) : '';

        if (empty($state)) {
            return '<div class="buygo-line-error">請透過 LINE 登入流程訪問此頁面。</div>';
        }

        // 從 Transient 讀取 LINE profile
        $profile_key = 'buygo_line_profile_' . $state;
        $data = get_transient($profile_key);

        if ($data === false) {
            return '<div class="buygo-line-error">登入資料已過期，請重新嘗試。</div>';
        }

        // 渲染註冊表單
        return self::render_form($data['profile'], $data['state']);
    }
}
```

### Pattern 3: Form Submission Handler via login_init

**What:** 表單提交使用相同的 wp-login.php?loginSocial=buygo-line 入口，透過 action=register 參數區分。

**When to use:** 用戶提交註冊表單時

**Example:**

```php
// Source: WordPress login_init hook
// 在 Login_Handler::handle_login_init() 中

// 檢查是否為表單提交
if (isset($_POST['action']) && $_POST['action'] === 'buygo_line_register') {
    $this->handle_register_submission();
    return;
}
```

### Anti-Patterns to Avoid

- **直接在 shortcode 中建立用戶:** Shortcode 應該只渲染 UI，表單提交處理應該在專門的 handler 中
- **使用 REST API 處理表單提交:** REST API 回應 JSON，不適合表單重定向流程
- **在 Shortcode 中 echo 輸出:** Shortcode 必須 return 字串，echo 會導致輸出位置錯誤
- **忽略 State 驗證:** 表單提交必須驗證 state，防止 CSRF 攻擊

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| 用戶名產生 | 自己寫複雜的清理邏輯 | 現有 UserService::generate_username() | 已處理中文、特殊字元、重複檢查 |
| 密碼產生 | rand() + md5() | wp_generate_password() | 密碼學安全、可設定長度和字元類型 |
| 用戶建立 | 直接寫資料庫 | wp_insert_user() | 自動驗證、觸發 hooks、處理 nicename |
| 登入設定 | 手動設定 cookie | wp_set_auth_cookie() | 正確處理 secure flag、expiration、hash |
| Nonce 驗證 | 自己實作 CSRF 防護 | wp_nonce_field() + wp_verify_nonce() | WordPress 標準安全機制 |
| 頁面選擇器 | 自己查詢頁面建立 select | wp_dropdown_pages() | 正確處理階層、排序、權限 |

**Key insight:** WordPress Core 已經有完整的用戶管理和認證機制，不要重新實作這些功能。重點是正確整合 LINE OAuth 流程到 WordPress 標準流程中。

## Common Pitfalls

### Pitfall 1: Shortcode 在錯誤時機註冊

**What goes wrong:** Shortcode 在 `init` hook 中全域註冊，導致直接訪問 Register Flow Page 時也能渲染表單（但沒有 LINE profile 資料）

**Why it happens:** 誤解 Nextend 架構，以為 shortcode 要像一般 shortcode 一樣全域註冊

**How to avoid:**
- Shortcode 只在 OAuth callback 時動態註冊（在 Login_Handler 中 catch 到 exception 前註冊）
- Shortcode handler 必須檢查 Transient 是否存在，不存在則顯示錯誤訊息

**Warning signs:** 直接訪問 Register Flow Page 時看到空白表單或 PHP 錯誤

### Pitfall 2: State 參數遺失

**What goes wrong:** 表單提交時無法驗證請求來源，可能被 CSRF 攻擊

**Why it happens:** 忘記在表單中包含隱藏的 state 欄位，或 state 已過期被清除

**How to avoid:**
- 表單中包含隱藏欄位：`<input type="hidden" name="state" value="...">`
- 使用獨立的 Transient key 儲存 LINE profile（不要和 StateManager 的 state 共用）
- 表單提交時驗證 state 對應的 Transient 是否存在

**Warning signs:** 表單提交後顯示「State 驗證失敗」錯誤

### Pitfall 3: Email 已存在但未處理 Auto-link

**What goes wrong:** 用戶使用的 LINE email 已在 WordPress 中存在（例如之前用 email 註冊過），直接建立新用戶會失敗

**Why it happens:** 忘記在建立用戶前檢查 email 是否存在

**How to avoid:**
- 表單提交時先檢查 email_exists()
- 若存在則執行 Auto-link：綁定現有用戶而非建立新用戶
- 顯示適當訊息：「已將 LINE 帳號綁定到您的現有帳號」

**Warning signs:** 用戶填寫已存在的 email 後看到「Email 已存在」錯誤，但預期應該自動綁定

### Pitfall 4: Shortcode 輸出被 escape

**What goes wrong:** 註冊表單的 HTML 被 WordPress 自動 escape，顯示為純文字

**Why it happens:** 在某些主題或外掛環境中，shortcode 輸出會被額外處理

**How to avoid:**
- Shortcode 返回的 HTML 應該已經正確 escape
- 避免在 shortcode 返回值外再包裝其他處理
- 測試多種主題確保相容性

**Warning signs:** 頁面顯示原始 HTML 標籤而非渲染後的表單

### Pitfall 5: 重複提交建立多個用戶

**What goes wrong:** 用戶雙擊提交按鈕或按 F5 重新整理，導致建立多個用戶或重複綁定

**Why it happens:** 沒有防重複提交機制

**How to avoid:**
- 表單提交成功後立即清除 Transient
- 在處理開始時檢查 LINE UID 是否已綁定
- 使用 JavaScript 禁用提交按鈕防止雙擊
- 提交成功後立即重定向（PRG 模式）

**Warning signs:** 資料庫中出現重複的用戶或綁定記錄

## Code Examples

### Example 1: 儲存 LINE Profile 到 Transient

```php
// Source: WordPress Transient API
// 在 Login_Handler::handle_callback() 中，拋出例外前

// 儲存 LINE profile 供 shortcode 使用
$profile_transient_key = 'buygo_line_profile_' . $state;
set_transient($profile_transient_key, [
    'profile'    => $profile,     // LINE profile (userId, displayName, pictureUrl, email)
    'state_data' => $state_data,  // 原始 state 資料 (redirect_url, user_id)
    'state'      => $state,       // state 值（用於表單驗證）
    'timestamp'  => time(),       // 建立時間
], 600);  // 10 分鐘有效期
```

### Example 2: 動態註冊 Shortcode

```php
// Source: WordPress add_shortcode()
// 在 Login_Handler::handle_login_init() 的 catch 區塊中

} catch (NSLContinuePageRenderException $e) {
    // 動態註冊 shortcode
    add_shortcode('buygo_line_register_flow', function($atts) use ($e) {
        $shortcode = new RegisterFlowShortcode();
        return $shortcode->render($atts, $e->getData());
    });

    // 將 state 加入 URL 讓 shortcode 可以讀取 Transient
    $register_page_id = get_option('buygo_line_register_flow_page');
    if ($register_page_id) {
        $register_url = add_query_arg('state', $e->getData()['state'], get_permalink($register_page_id));
        wp_redirect($register_url);
        exit;
    }

    // 若沒有設定 Register Flow Page，讓 WordPress 繼續渲染（在 wp-login.php 上）
    return;
}
```

### Example 3: Shortcode 渲染註冊表單

```php
// Source: WordPress Shortcode API
class RegisterFlowShortcode {
    public function render($atts, $exception_data = null): string {
        // 從 URL 參數或 exception 資料取得 state
        $state = $exception_data['state'] ?? (isset($_GET['state']) ? sanitize_text_field($_GET['state']) : '');

        if (empty($state)) {
            return $this->render_error('請透過 LINE 登入流程訪問此頁面。');
        }

        // 從 Transient 讀取 LINE profile
        $profile_key = 'buygo_line_profile_' . $state;
        $data = get_transient($profile_key);

        if ($data === false) {
            return $this->render_error('登入資料已過期，請重新嘗試 LINE 登入。');
        }

        $profile = $data['profile'];

        // 渲染表單
        ob_start();
        ?>
        <div class="buygo-line-register-form">
            <!-- LINE Profile 顯示 -->
            <div class="line-profile">
                <?php if (!empty($profile['pictureUrl'])): ?>
                    <img src="<?php echo esc_url($profile['pictureUrl']); ?>"
                         alt="LINE Avatar" class="line-avatar">
                <?php endif; ?>
                <span class="line-name"><?php echo esc_html($profile['displayName'] ?? ''); ?></span>
            </div>

            <!-- 註冊表單 -->
            <form method="post" action="<?php echo esc_url(site_url('wp-login.php?loginSocial=buygo-line')); ?>">
                <?php wp_nonce_field('buygo_line_register_action', 'buygo_line_register_nonce'); ?>
                <input type="hidden" name="action" value="buygo_line_register">
                <input type="hidden" name="state" value="<?php echo esc_attr($state); ?>">
                <input type="hidden" name="line_uid" value="<?php echo esc_attr($profile['userId']); ?>">

                <p>
                    <label for="user_login">用戶名</label>
                    <input type="text" name="user_login" id="user_login"
                           value="<?php echo esc_attr($profile['displayName'] ?? ''); ?>" required>
                </p>

                <p>
                    <label for="user_email">Email</label>
                    <input type="email" name="user_email" id="user_email"
                           value="<?php echo esc_attr($profile['email'] ?? ''); ?>" required>
                </p>

                <p>
                    <button type="submit" class="button button-primary">完成註冊</button>
                </p>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    private function render_error(string $message): string {
        return '<div class="buygo-line-error">' . esc_html($message) . '</div>';
    }
}
```

### Example 4: 表單提交處理

```php
// Source: WordPress wp_insert_user() + wp_set_auth_cookie()
// 在 Login_Handler 中新增方法

private function handle_register_submission(): void {
    // 1. Nonce 驗證
    if (!isset($_POST['buygo_line_register_nonce']) ||
        !wp_verify_nonce($_POST['buygo_line_register_nonce'], 'buygo_line_register_action')) {
        wp_die('安全驗證失敗', 'Error', ['response' => 403]);
    }

    // 2. 取得並驗證 state
    $state = isset($_POST['state']) ? sanitize_text_field($_POST['state']) : '';
    $profile_key = 'buygo_line_profile_' . $state;
    $data = get_transient($profile_key);

    if ($data === false) {
        wp_die('登入資料已過期，請重新嘗試', 'Error', ['response' => 400]);
    }

    $profile = $data['profile'];
    $state_data = $data['state_data'];

    // 3. 取得表單資料
    $user_login = sanitize_user($_POST['user_login'] ?? '');
    $user_email = sanitize_email($_POST['user_email'] ?? '');
    $line_uid = sanitize_text_field($_POST['line_uid'] ?? '');

    // 4. 驗證 LINE UID 一致性
    if ($line_uid !== $profile['userId']) {
        wp_die('LINE 帳號資訊不一致', 'Error', ['response' => 400]);
    }

    // 5. 驗證用戶名和 Email
    if (empty($user_login) || empty($user_email)) {
        wp_die('請填寫用戶名和 Email', 'Error', ['response' => 400]);
    }

    if (!is_email($user_email)) {
        wp_die('請輸入有效的 Email 地址', 'Error', ['response' => 400]);
    }

    // 6. 檢查 Email 是否已存在（Auto-link）
    $existing_user_id = email_exists($user_email);
    if ($existing_user_id) {
        // Auto-link: 綁定現有用戶
        $link_result = LineUserService::linkUser($existing_user_id, $line_uid, false);
        if ($link_result) {
            // 清除 Transient
            delete_transient($profile_key);

            // 自動登入
            wp_set_auth_cookie($existing_user_id, true);

            // 導向
            $redirect_to = $state_data['redirect_url'] ?? home_url();
            wp_safe_redirect($redirect_to);
            exit;
        } else {
            wp_die('此 LINE 帳號已綁定其他用戶', 'Error', ['response' => 400]);
        }
    }

    // 7. 檢查用戶名是否已存在
    if (username_exists($user_login)) {
        // 加上數字後綴
        $base_login = $user_login;
        $counter = 1;
        while (username_exists($user_login)) {
            $user_login = $base_login . $counter;
            $counter++;
        }
    }

    // 8. 建立用戶
    $user_id = wp_insert_user([
        'user_login'   => $user_login,
        'user_email'   => $user_email,
        'user_pass'    => wp_generate_password(16, false),
        'display_name' => $profile['displayName'] ?? $user_login,
        'role'         => 'subscriber',
    ]);

    if (is_wp_error($user_id)) {
        wp_die('用戶建立失敗: ' . $user_id->get_error_message(), 'Error', ['response' => 500]);
    }

    // 9. 綁定 LINE
    LineUserService::linkUser($user_id, $line_uid, true);  // is_registration = true

    // 10. 儲存 LINE profile 到 user_meta
    update_user_meta($user_id, 'buygo_line_avatar_url', $profile['pictureUrl'] ?? '');

    // 11. 清除 Transient
    delete_transient($profile_key);

    // 12. 自動登入
    wp_set_auth_cookie($user_id, true);

    // 13. 導向
    $redirect_to = $state_data['redirect_url'] ?? home_url();
    wp_safe_redirect($redirect_to);
    exit;
}
```

### Example 5: 後台頁面選擇器

```php
// Source: WordPress wp_dropdown_pages()
// 在 settings-page.php 中新增

<tr>
    <th scope="row">
        <label for="register_flow_page">LINE 註冊流程頁面</label>
    </th>
    <td>
        <?php
        wp_dropdown_pages([
            'name'             => 'register_flow_page',
            'id'               => 'register_flow_page',
            'selected'         => get_option('buygo_line_register_flow_page', 0),
            'show_option_none' => '— 請選擇頁面 —',
            'option_none_value'=> 0,
        ]);
        ?>
        <p class="description">
            選擇一個包含 <code>[buygo_line_register_flow]</code> shortcode 的頁面。
            若未設定，新用戶將在 wp-login.php 上看到註冊表單。
        </p>
        <?php
        // 檢查所選頁面是否包含 shortcode
        $page_id = get_option('buygo_line_register_flow_page', 0);
        if ($page_id) {
            $page = get_post($page_id);
            if ($page && strpos($page->post_content, '[buygo_line_register_flow]') === false) {
                echo '<p class="notice notice-warning" style="padding: 10px; margin-top: 10px;">';
                echo '警告：所選頁面未包含 <code>[buygo_line_register_flow]</code> shortcode。';
                echo '</p>';
            }
        }
        ?>
    </td>
</tr>
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| REST API callback 輸出 HTML | 標準 WordPress URL (wp-login.php) | Phase 9 | 解決 HTML 原始碼顯示問題 |
| PHP Session 儲存 state | Transient API（三層 fallback） | Phase 9 | 更好的相容性，支援無 Session 環境 |
| 全域 shortcode 註冊 | 動態註冊（僅在 OAuth callback 時） | Phase 10 | 防止未授權訪問空白表單 |
| 直接建立用戶 | Register Flow Page + 表單 | Phase 10 | 允許用戶修改用戶名和 Email |

**Deprecated/outdated:**
- `includes/api/class-login-api.php` 的 REST endpoint：已標記 @deprecated 2.0.0，但保留向後相容
- 直接用 LINE profile 建立用戶（不經過 Register Flow Page）：不符合 Nextend 架構，無法處理缺少 email 的情況

## Open Questions

### 1. 是否需要在 wp-login.php 上渲染表單作為 fallback？

**What we know:**
- 若管理員未設定 Register Flow Page，OAuth callback 後會停留在 wp-login.php
- NSLContinuePageRenderException 被 catch 後會 return，WordPress 繼續渲染 wp-login.php
- Shortcode 在 wp-login.php 上不會被執行（wp-login.php 不是 post/page）

**What's unclear:**
- 是否應該直接在 wp-login.php 上輸出註冊表單（不使用 shortcode）？
- 或者強制要求管理員必須設定 Register Flow Page？

**Recommendation:**
- 實作兩種模式：
  1. 若有設定 Register Flow Page：重定向到該頁面（URL 帶 state 參數）
  2. 若未設定：直接在 wp-login.php 上輸出簡化版註冊表單
- 後台顯示警告訊息建議管理員設定 Register Flow Page

### 2. 表單樣式如何處理？

**What we know:**
- 不同主題有不同的表單樣式
- 需要基本的 CSS 確保表單可用

**What's unclear:**
- 是否提供預設樣式？
- 如何讓管理員自訂樣式？

**Recommendation:**
- 提供最小化的 BEM 風格 class names（`.buygo-line-register-form`, `.line-profile`, `.line-avatar`）
- 內建基本樣式，可被主題覆蓋
- 不使用 inline styles，方便自訂

## Sources

### Primary (HIGH confidence)
- [WordPress Shortcode API](https://developer.wordpress.org/apis/shortcode/) - add_shortcode, shortcode callback
- [WordPress add_shortcode()](https://developer.wordpress.org/reference/functions/add_shortcode/) - 參數、timing、return 規範
- [WordPress wp_insert_user()](https://developer.wordpress.org/reference/functions/wp_insert_user/) - 用戶建立、參數、驗證
- [WordPress Transient API](https://developer.wordpress.org/apis/transients/) - 臨時資料儲存、過期機制
- [WordPress login_init Hook](https://developer.wordpress.org/reference/hooks/login_init/) - hook 執行時機

### Secondary (MEDIUM confidence)
- [WordPress wp_set_auth_cookie()](https://developer.wordpress.org/reference/functions/wp_set_auth_cookie/) - 自動登入機制
- [Nextend Social Login Documentation](https://nextendweb.com/nextend-social-login-docs/) - Register Flow Page 概念、shortcode 用法
- 專案內部文件 `.planning/NEXTEND-SOCIAL-LOGIN-ANALYSIS.md` - 完整逆向工程分析

### Tertiary (LOW confidence)
- WebSearch 關於動態 shortcode 註冊的社群討論（需實測驗證）

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - 完全使用 WordPress Core API，官方文件完整
- Architecture: HIGH - 基於 Phase 9 已驗證的架構和 Nextend 成熟模式
- Pitfalls: MEDIUM - 部分基於推測，需要實際測試驗證

**Research date:** 2026-01-29
**Valid until:** 60 天（WordPress Core API 穩定，Nextend 架構成熟）
