# Phase 11: 完整註冊/登入/綁定流程 - Research

**Researched:** 2026-01-29
**Domain:** WordPress OAuth User Flow / Account Linking / State Management
**Confidence:** HIGH

## Summary

本研究聚焦於整合 Phase 8-10 已建立的基礎設施，完成完整的註冊/登入/綁定流程。經過詳細程式碼審查後發現，Phase 9-10 已經實作了大部分 FLOW-01 到 FLOW-04 的核心功能，Phase 11 的主要任務是：(1) 完善 FLOW-03 已登入用戶綁定流程；(2) 加強 FLOW-05 State 驗證機制的安全性；(3) 確保 STORAGE-04 StateManager 的完整整合。

現有實作狀況分析：
- **FLOW-01（新用戶註冊）**：已由 Phase 10 完成（Login_Handler::handle_callback → RegisterFlowShortcode → handle_register_submission）
- **FLOW-02（Auto-link）**：已由 Phase 10 完成（handle_register_submission → handle_auto_link）
- **FLOW-03（已登入綁定）**：部分完成（Login_Handler 有 FLOW_LINK 處理，但缺少完整的表單提交處理）
- **FLOW-04（已有用戶登入）**：已由 Phase 9 完成（Login_Handler::perform_login）
- **FLOW-05（State 驗證）**：已由 Phase 9 完成（StateManager 三層 fallback + 10 分鐘有效期）

**Primary recommendation:** Phase 11 應聚焦於完善已登入用戶綁定流程（FLOW-03）的表單提交處理，並新增 StateManager 的 logged_in_user_id 欄位支援，確保所有流程的 State 驗證使用 hash_equals 防止時序攻擊。

## Standard Stack

本階段完全使用 WordPress Core API 和現有專案基礎設施。

### Core

| Component | Version | Purpose | Why Standard |
|-----------|---------|---------|--------------|
| WordPress wp_set_auth_cookie() | 6.x | 自動登入用戶 | WordPress 原生認證機制，處理 secure flag 和 session tokens |
| WordPress Transient API | 6.x | State 和 LINE Profile 持久化儲存 | 已由 StateManager 包裝，支援物件快取整合 |
| WordPress wp_insert_user() | 6.x | 建立 WordPress 用戶 | WordPress 原生函數，完整驗證和 hooks |
| WordPress wp_verify_nonce() | 6.x | CSRF 保護 | WordPress 標準安全機制 |
| hash_equals() | PHP 5.6+ | 時序安全字串比較 | 防止時序攻擊的標準 PHP 函數 |

### Supporting

| Component | Version | Purpose | When to Use |
|-----------|---------|---------|-------------|
| get_current_user_id() | 6.x | 取得當前登入用戶 ID | 判斷是否為綁定流程 |
| is_user_logged_in() | 6.x | 檢查用戶登入狀態 | 區分登入/綁定流程 |
| wp_safe_redirect() | 6.x | 安全的重定向 | 所有流程完成後的導向 |
| apply_filters('login_redirect') | 6.x | 應用登入重定向 filter | 相容其他外掛的重定向規則 |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| hash_equals() | strcmp() | strcmp() 有時序攻擊風險，hash_equals() 是常數時間比較 |
| Transient API | PHP Session | Session 在某些主機不可用，REST API 不支援 |
| wp_verify_nonce() | 自製 CSRF token | WordPress nonce 已處理用戶綁定和過期機制 |

## Architecture Patterns

### Recommended Project Structure

```
includes/
├── handlers/
│   └── class-login-handler.php     # 已完成：主要流程控制（需擴展綁定表單處理）
├── shortcodes/
│   └── class-register-flow-shortcode.php  # 已完成：註冊表單渲染
├── services/
│   ├── class-state-manager.php     # 已完成：State 管理（可選：加強 hash_equals）
│   ├── class-login-service.php     # 已完成：OAuth 流程
│   ├── class-user-service.php      # 已存在：用戶建立
│   └── class-line-user-service.php # 已完成：LINE 綁定 API
└── exceptions/
    └── class-nsl-continue-page-render-exception.php  # 已完成
```

### Pattern 1: 已登入用戶綁定流程

**What:** 用戶已登入 WordPress 後，從「我的帳號」頁面發起 LINE 綁定。

**When to use:** `is_user_logged_in()` 為 true 且 state_data 包含 logged_in_user_id 時。

**Current Implementation Gap:**
- Login_Handler 已有 FLOW_LINK 處理和 render_fallback_link_confirmation()
- 但缺少 handle_link_submission() 表單提交處理方法

**Example:**
```php
// Source: 現有 Login_Handler 架構
// 在 handle_login_init() 中新增檢查

// 檢查是否為綁定表單提交
if ( isset( $_POST['action'] ) && $_POST['action'] === 'buygo_line_link' ) {
    $this->handle_link_submission();
    return;
}
```

### Pattern 2: State 包含 logged_in_user_id

**What:** 綁定流程的 State 需要包含當前登入用戶的 ID，以便 callback 時識別要綁定的用戶。

**When to use:** 發起綁定請求時（用戶已登入）。

**Example:**
```php
// Source: LoginService::get_authorize_url()
// 已支援 $user_id 參數

// 在前端按鈕產生 authorize URL 時傳入當前用戶 ID
$authorize_url = $login_service->get_authorize_url(
    $redirect_url,
    get_current_user_id()  // 傳入當前用戶 ID
);

// StateManager 儲存時已包含 user_id
$this->state_manager->store_state(
    $state,
    array(
        'redirect_url' => $redirect_url,
        'user_id'      => $user_id,  // 已支援
    )
);
```

### Pattern 3: hash_equals 防時序攻擊

**What:** 使用 hash_equals() 進行 State 比較，防止時序攻擊。

**When to use:** 所有 State 驗證場景。

**Current Status:** StateManager::verify_state() 使用 get_transient()，因為 Transient key 本身已是 hash，不需要在驗證時再做 hash_equals。但 nonce 和 LINE UID 比較應考慮使用。

**Example:**
```php
// Source: PHP best practices
// 在 handle_register_submission() 的 LINE UID 驗證中

// 現有實作（已安全）
if ( $line_uid !== $profile['userId'] ) {
    // ...
}

// 更安全的寫法（可選，因為 LINE UID 不是秘密）
if ( ! hash_equals( $profile['userId'], $line_uid ) ) {
    // ...
}
```

### Anti-Patterns to Avoid

- **跳過 State 驗證:** 永遠不要在 callback 時跳過 state 驗證，即使「看起來」是合法請求
- **重複使用 State:** State 是一次性的，consume_state() 必須在驗證成功後立即呼叫
- **忽略 logged_in_user_id:** 綁定流程必須驗證 state_data 中的 user_id 與當前登入用戶一致
- **在表單中信任 user_id:** 表單的 user_id 隱藏欄位只是 fallback，主要應從 state_data 取得

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| 時序安全字串比較 | strcmp() / === | hash_equals() | 防止時序攻擊，PHP 5.6+ 內建 |
| State 儲存 | 自己寫 option 處理 | 現有 StateManager | 已有三層 fallback、過期機制 |
| CSRF 保護 | 自製 token 系統 | wp_nonce_field() / wp_verify_nonce() | WordPress 標準，處理用戶綁定和過期 |
| 用戶認證 Cookie | 手動設定 cookie | wp_set_auth_cookie() | 處理 secure flag、路徑、session tokens |
| 用戶存在檢查 | 直接查資料庫 | email_exists() / username_exists() | WordPress 標準函數，有快取 |

**Key insight:** Phase 8-10 已經建立了完整的基礎設施，Phase 11 的重點是完善細節和確保所有流程正確整合，而非重新實作。

## Common Pitfalls

### Pitfall 1: 綁定流程 user_id 不一致

**What goes wrong:** 綁定完成後，LINE 帳號綁定到錯誤的 WordPress 用戶。

**Why it happens:**
- State 中的 user_id 與當前登入用戶不一致（用戶在 OAuth 過程中登出又登入其他帳號）
- 從表單隱藏欄位取得 user_id 而非 state_data

**How to avoid:**
- 在 handle_link_submission() 中驗證 state_data['user_id'] === get_current_user_id()
- 若不一致則拒絕綁定並顯示錯誤
- 表單的 user_id 隱藏欄位只作為 UI 顯示參考，實際使用 state_data

**Warning signs:** 用戶反映「我綁定到別人的帳號」或綁定記錄顯示錯誤的 user_id

### Pitfall 2: State 過期但 Transient 還在

**What goes wrong:** State 應該已過期，但系統仍接受請求。

**Why it happens:**
- StateManager::verify_state() 雖然有時間檢查，但 Transient 可能因物件快取延遲
- 手動檢查 created_at 的時間戳記比對有誤

**How to avoid:**
- 現有實作已正確：Transient 自帶過期機制 + created_at 雙重檢查
- 確保 STATE_EXPIRY 常數一致（目前為 600 秒 = 10 分鐘）

**Warning signs:** 日誌顯示 State 驗證成功但 age 超過預期時間

### Pitfall 3: 綁定成功但用戶收不到通知

**What goes wrong:** 綁定完成後用戶沒看到成功訊息，不確定是否成功。

**Why it happens:**
- wp_safe_redirect() 後頁面已離開，WordPress admin_notices 不會顯示
- Transient 通知機制未正確實作

**How to avoid:**
- 現有實作使用 Transient 儲存通知訊息（buygo_line_notice_{user_id}）
- 需要在前台有 hook 讀取並顯示這個通知
- 或使用 query parameter（?line_linked=success）

**Warning signs:** 用戶詢問「我剛剛是不是綁定成功了？」

### Pitfall 4: Auto-link 誤判

**What goes wrong:** Email 已存在但用戶實際上是新用戶，或 Email 不存在但用戶想綁定現有帳號。

**Why it happens:**
- LINE 提供的 email 可能是用戶不常用的 email
- 用戶可能有多個 email 但 WordPress 只記錄一個

**How to avoid:**
- 現有 Auto-link 邏輯已正確：檢查 email_exists() → 若存在則綁定現有帳號
- 綁定前顯示確認訊息：「此 Email 已存在帳號，將自動綁定」
- 若 Email 對應的用戶已有 LINE 綁定，則拒絕並顯示錯誤

**Warning signs:** 用戶反映「我有兩個帳號」或「我找不到之前的帳號」

### Pitfall 5: 綁定流程與註冊流程混淆

**What goes wrong:** 已登入用戶發起綁定，但系統導向註冊流程。

**Why it happens:**
- handle_callback() 沒有正確檢查 state_data['user_id']
- 或 user_id 在 state 中儲存不正確

**How to avoid:**
- 在 handle_callback() 中：
  1. 先檢查 state_data['user_id'] 是否存在
  2. 若存在且 > 0，這是綁定流程
  3. 若不存在或為 0，才進入註冊/登入流程
- 現有 Login_Handler::handle_callback() 只檢查 LINE UID 是否已綁定，需要加入 state_data['user_id'] 檢查

**Warning signs:** 已登入用戶看到註冊表單

## Code Examples

### Example 1: handle_link_submission() 實作

```php
// Source: 基於現有 handle_register_submission() 改寫
// 在 Login_Handler 中新增方法

/**
 * 處理綁定表單提交
 *
 * @return void
 */
private function handle_link_submission(): void {
    // 1. Nonce 驗證
    if ( ! isset( $_POST['buygo_line_link_nonce'] ) ||
         ! wp_verify_nonce(
             sanitize_text_field( wp_unslash( $_POST['buygo_line_link_nonce'] ) ),
             'buygo_line_link_action'
         ) ) {
        wp_die( '安全驗證失敗', 'Error', array( 'response' => 403 ) );
    }

    // 2. 取得並驗證 state
    $state       = isset( $_POST['state'] ) ? sanitize_text_field( wp_unslash( $_POST['state'] ) ) : '';
    $profile_key = self::PROFILE_TRANSIENT_PREFIX . $state;
    $data        = get_transient( $profile_key );

    if ( $data === false ) {
        wp_die( '登入資料已過期，請重新嘗試', 'Error', array( 'response' => 400 ) );
    }

    $profile    = $data['profile'];
    $state_data = $data['state_data'];
    $line_uid   = $profile['userId'];

    // 3. 驗證用戶 ID 一致性（關鍵安全檢查）
    $expected_user_id = $state_data['user_id'] ?? 0;
    $current_user_id  = get_current_user_id();

    if ( $expected_user_id <= 0 || $expected_user_id !== $current_user_id ) {
        delete_transient( $profile_key );
        wp_die( '用戶身份驗證失敗，請重新登入後再試', 'Error', array( 'response' => 403 ) );
    }

    // 4. 檢查 LINE UID 是否已綁定其他用戶
    $existing_user_id = LineUserService::getUserByLineUid( $line_uid );
    if ( $existing_user_id && $existing_user_id !== $current_user_id ) {
        delete_transient( $profile_key );
        wp_die( '此 LINE 帳號已綁定其他用戶', 'Error', array( 'response' => 400 ) );
    }

    // 5. 檢查當前用戶是否已綁定其他 LINE
    $existing_line_uid = LineUserService::getLineUidByUserId( $current_user_id );
    if ( $existing_line_uid && $existing_line_uid !== $line_uid ) {
        delete_transient( $profile_key );
        wp_die( '您的帳號已綁定其他 LINE 帳號', 'Error', array( 'response' => 400 ) );
    }

    // 6. 執行綁定（is_registration = false）
    $link_result = LineUserService::linkUser( $current_user_id, $line_uid, false );
    if ( ! $link_result ) {
        delete_transient( $profile_key );
        wp_die( 'LINE 帳號綁定失敗', 'Error', array( 'response' => 500 ) );
    }

    // 7. 儲存 LINE 頭像
    if ( ! empty( $profile['pictureUrl'] ) ) {
        update_user_meta( $current_user_id, 'buygo_line_avatar_url', $profile['pictureUrl'] );
    }

    Logger::get_instance()->log(
        'info',
        array(
            'message'  => 'User linked LINE account',
            'user_id'  => $current_user_id,
            'line_uid' => $line_uid,
        )
    );

    // 8. 清除 Transient
    delete_transient( $profile_key );

    // 9. 觸發 hook
    do_action( 'buygo_line_after_link', $current_user_id, $line_uid, $profile );

    // 10. 設定成功通知
    set_transient(
        'buygo_line_notice_' . $current_user_id,
        array(
            'type'    => 'success',
            'message' => 'LINE 帳號綁定成功！',
        ),
        60
    );

    // 11. 導向
    $redirect_to = $state_data['redirect_url'] ?? home_url();
    wp_safe_redirect( $redirect_to );
    exit;
}
```

### Example 2: handle_callback() 補充綁定流程判斷

```php
// Source: 基於現有 Login_Handler::handle_callback() 擴展
// 在查詢 LINE UID 之後，檢查是否為綁定流程

// 3. 查詢是否已綁定用戶
$user_id = LineUserService::getUserByLineUid( $line_uid );

// 4. 檢查是否為綁定流程（state_data 包含有效 user_id）
$link_user_id = $state_data['user_id'] ?? 0;

if ( $link_user_id > 0 ) {
    // 這是綁定流程

    // 4a. 若 LINE UID 已綁定其他用戶，拒絕
    if ( $user_id && $user_id !== $link_user_id ) {
        Logger::get_instance()->log(
            'error',
            array(
                'message'        => 'Link flow: LINE UID already linked to another user',
                'link_user_id'   => $link_user_id,
                'existing_user'  => $user_id,
                'line_uid'       => $line_uid,
            )
        );
        wp_die( '此 LINE 帳號已綁定其他用戶', 'Error', array( 'response' => 400 ) );
    }

    // 4b. 若 LINE UID 已綁定同一用戶，直接登入
    if ( $user_id === $link_user_id ) {
        Logger::get_instance()->log(
            'info',
            array(
                'message' => 'Link flow: Already linked, logging in',
                'user_id' => $user_id,
            )
        );
        $this->perform_login( $user_id, $state_data );
        return;
    }

    // 4c. 儲存 profile 並拋出 FLOW_LINK 例外
    $profile_key = self::PROFILE_TRANSIENT_PREFIX . $state;
    set_transient(
        $profile_key,
        array(
            'profile'    => $profile,
            'state_data' => $state_data,
            'state'      => $state,
            'timestamp'  => time(),
        ),
        self::PROFILE_TRANSIENT_EXPIRY
    );

    throw new NSLContinuePageRenderException(
        NSLContinuePageRenderException::FLOW_LINK,
        array(
            'profile'     => $profile,
            'state_data'  => $state_data,
            'state'       => $state,
            'user_id'     => $link_user_id,
        )
    );
}

// 5. 既有邏輯：登入或註冊流程
if ( $user_id ) {
    $this->perform_login( $user_id, $state_data );
} else {
    // 註冊流程...
}
```

### Example 3: State 驗證加強（可選）

```php
// Source: StateManager::verify_state() 改進建議
// 現有實作已經足夠安全，這是額外加強

/**
 * 驗證 state（使用 hash_equals 進行常數時間比較）
 *
 * @param string $state 要驗證的 state
 * @return array|false 成功時返回儲存的資料，失敗時返回 false
 */
public function verify_state( string $state ) {
    // State 格式驗證（32 字元十六進位）
    if ( ! preg_match( '/^[a-f0-9]{32}$/i', $state ) ) {
        Logger::get_instance()->log(
            'warning',
            array(
                'message' => 'Invalid state format',
                'state'   => $state,
            )
        );
        return false;
    }

    // 從 Transient 讀取
    $data = get_transient( self::TRANSIENT_PREFIX . $state );

    // ... 其餘邏輯不變
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| REST API OAuth callback | 標準 WordPress URL (wp-login.php) | Phase 9 | 解決 HTML 原始碼顯示問題 |
| PHP Session 儲存 state | Transient API（三層 fallback） | Phase 9 | 更好的相容性 |
| strcmp() 比較敏感字串 | hash_equals() | PHP 5.6+ | 防止時序攻擊 |
| 直接建立用戶 | Register Flow Page + 表單 | Phase 10 | 允許用戶修改資訊 |
| 手動 CSRF 防護 | wp_nonce_field() / wp_verify_nonce() | WordPress 標準 | 更完整的安全性 |

**Deprecated/outdated:**
- 使用 PHP Session 儲存 OAuth state：REST API 環境不支援
- 直接用 strcmp() 比較 state：有時序攻擊風險
- 忽略 state 驗證：所有 OAuth 實作都必須驗證 state

## Open Questions

### 1. 綁定流程是否需要確認頁面？

**What we know:**
- 現有 render_fallback_link_confirmation() 已渲染確認表單
- 但沒有設定 buygo_line_link_flow_page option

**What's unclear:**
- 是否需要像 Register Flow Page 一樣提供獨立頁面設定？
- 或者 fallback 模式已經足夠？

**Recommendation:**
- Phase 11 使用現有 fallback 模式（在 wp-login.php 顯示確認表單）
- 若有需求，Phase 13（前台整合）再新增頁面設定選項

### 2. 通知訊息如何顯示？

**What we know:**
- 現有實作使用 Transient 儲存通知（buygo_line_notice_{user_id}）
- 需要前台 hook 讀取並顯示

**What's unclear:**
- 應該在哪個 hook 顯示？
- 是否支援前台和後台？

**Recommendation:**
- 新增 hook 在 `wp_footer` 和 `admin_notices` 讀取並顯示通知
- 顯示後立即刪除 Transient
- 或改用 query parameter（?line_linked=success）更簡單直接

### 3. 已綁定用戶重複發起綁定？

**What we know:**
- LineUserService::linkUser() 已處理「同一用戶同一 LINE」的情況（更新 link_date）
- 但 UX 上可能造成困惑

**What's unclear:**
- 是否應該在發起綁定前就檢查？
- 是否需要解除綁定再重新綁定的流程？

**Recommendation:**
- 前端按鈕在渲染前檢查 isUserLinked()
- 若已綁定，顯示「已綁定 LINE」而非綁定按鈕
- 提供「解除綁定」選項（Phase 13 前台整合處理）

## Sources

### Primary (HIGH confidence)
- [WordPress wp_set_auth_cookie() Documentation](https://developer.wordpress.org/reference/functions/wp_set_auth_cookie/) - 自動登入機制
- [WordPress Authentication Cookies Deep-Dive](https://snicco.io/blog/how-wordpress-uses-authentication-cookies-and-sessions) - Cookie 機制詳解
- 專案內部程式碼分析 - Login_Handler、StateManager、LineUserService

### Secondary (MEDIUM confidence)
- [LINE Login Integration Documentation](https://developers.line.biz/en/docs/line-login/integrate-line-login/) - OAuth 流程
- [WordPress OAuth Security Best Practices](https://belovdigital.agency/blog/implementing-oauth-2-0-authentication-in-wordpress/) - OAuth 安全實踐
- Phase 9、Phase 10 RESEARCH.md - 前置研究

### Tertiary (LOW confidence)
- WebSearch 關於 account linking patterns（需實測驗證）

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - 完全使用 WordPress Core API 和現有專案程式碼
- Architecture: HIGH - 基於 Phase 9-10 已驗證的架構擴展
- Pitfalls: HIGH - 基於程式碼審查和前置 Phase 經驗

**Research date:** 2026-01-29
**Valid until:** 60 天（WordPress Core API 穩定，現有架構已驗證）

---

## Additional Notes

### 現有程式碼狀態總結

**Login_Handler (已完成約 90%):**
- handle_login_init() - 完整
- handle_authorize() - 完整
- handle_callback() - 需補充綁定流程判斷
- perform_login() - 完整
- handle_register_submission() - 完整
- handle_auto_link() - 完整
- render_fallback_registration_form() - 完整
- render_fallback_link_confirmation() - 完整
- **缺少:** handle_link_submission()

**StateManager (已完成 100%):**
- generate_state() - 完整
- store_state() - 完整（已支援 user_id）
- verify_state() - 完整
- consume_state() - 完整

**LineUserService (已完成 100%):**
- getUserByLineUid() - 完整
- getLineUidByUserId() - 完整
- isUserLinked() - 完整
- linkUser() - 完整（支援 is_registration 參數）
- unlinkUser() - 完整

### Phase 11 實際工作範圍

基於上述分析，Phase 11 的實際工作為：

1. **新增 handle_link_submission() 方法** - 處理綁定表單提交
2. **修改 handle_callback()** - 補充綁定流程判斷邏輯
3. **新增 handle_login_init() 的 link action 檢查**
4. **（可選）新增通知顯示 hook**
5. **驗證所有流程的完整性**
