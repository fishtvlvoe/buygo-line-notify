# Nextend Social Login 逆向工程分析

## 執行日期
2026-01-29

## 目的
理解 Nextend Social Login (NSL) 外掛的 LINE 登入完整流程,特別是:
1. callback/redirect 導引頁機制
2. 資料收集流程(email 等)
3. WordPress 使用者註冊流程
4. LINE profile 資料同步(名稱、email、大頭貼)

---

## 核心機制發現

### 1. **NSLContinuePageRenderException 模式**

**關鍵檔案**: `includes/exceptions.php`, `includes/provider.php:251-256`

```php
class NSLContinuePageRenderException extends Exception {
}
```

**運作原理**:
- OAuth callback 完成後,不是直接 redirect,而是**拋出一個特殊 Exception**
- 這個 Exception **不是錯誤**,而是訊號,告訴系統「繼續正常頁面渲染流程」
- 讓 WordPress 正常載入頁面,再由 shortcode 在頁面中顯示註冊表單

```php
// provider.php:251-256
try {
    $this->doAuthenticate();
} catch (NSLContinuePageRenderException $e) {
    // 這不是錯誤,允許頁面繼續正常顯示流程
    // 用於 Theme My Login 等功能,覆蓋 shortcode 並顯示我們的 email 請求表單
} catch (Exception $e) {
    $this->onError($e);
}
```

---

### 2. **Register Flow Page (註冊流程頁面)**

**設定位置**: `admin/templates/settings/general.php:83`

**說明**:
- 管理員需要建立一個 WordPress 頁面
- 在頁面中插入 shortcode: `[nextend_social_login_register_flow]`
- 在 NSL 設定中選擇這個頁面作為「Register flow page」

**重要特性**:
- 這個頁面**只能在 social login/registration 進行時訪問**
- 平常直接訪問這個頁面會看不到內容(因為沒有 OAuth 資料)

---

### 3. **Shortcode 註冊機制**

**關鍵檔案**: `includes/userData.php:100-105`

```php
if ($this->isCustomRegisterFlow) {
    add_shortcode('nextend_social_login_register_flow', array(
        $this,
        'customRegisterFlowShortcode'
    ));
    throw new NSLContinuePageRenderException('CUSTOM_REGISTER_FLOW');
}
```

**流程**:
1. OAuth callback 完成,取得使用者資料
2. 判斷需要額外輸入(例如 email)
3. **動態註冊** shortcode
4. **拋出 NSLContinuePageRenderException**
5. WordPress 正常載入「Register Flow Page」
6. Shortcode 被執行,渲染註冊表單
7. 表單顯示已取得的資料,要求使用者補充缺少的資料

---

### 4. **完整註冊流程**

**關鍵檔案**: `includes/user.php:234-432`

#### **階段 1: OAuth Callback 完成**
- LINE 授權完成,redirect 回 WordPress
- `liveConnectGetUserProfile()` 被呼叫
- 取得 LINE profile: `id`, `name`, `email`, `picture`

#### **階段 2: 判斷使用者狀態**
```php
// user.php:54-58
$user_id = $this->provider->getUserIDByProviderIdentifier($this->getAuthUserData('id'));
if ($user_id !== null && !get_user_by('id', $user_id)) {
    $this->provider->removeConnectionByUserID($user_id);
    $user_id = null;
}
```

- 檢查 `wp_social_users` 表中是否已有此 LINE ID
- 若有 → 執行登入 `login($user_id)`
- 若無 → 執行註冊準備 `prepareRegister()`

#### **階段 3: 註冊準備 (prepareRegister)**
```php
// user.php:94-188
protected function prepareRegister() {
    // 1. 取得 email
    $email = $this->getAuthUserData('email');

    // 2. 檢查 email 是否已註冊
    $user_id = email_exists($email);

    // 3. 若 email 已存在 → autoLink (自動連結)
    if ($user_id !== false) {
        if ($this->autoLink($user_id, $providerUserID)) {
            $this->login($user_id);
        }
    }

    // 4. 若 email 不存在 → 真正註冊
    else {
        $this->register($providerID, $email);
    }
}
```

#### **階段 4: 資料驗證與補充 (userData.php)**
- **如果缺少必要資料**(例如 email),會呼叫 `displayForm()`
- 拋出 `NSLContinuePageRenderException`
- WordPress 載入 Register Flow Page
- Shortcode 顯示表單,要求使用者輸入

```php
// userData.php:165-182
public function customRegisterFlowShortcode() {
    // 顯示錯誤訊息(如果有)
    if (is_wp_error($errors)) {
        foreach ($errors->get_error_messages() as $error) {
            $html[] = '<div class="error">' . $error . '</div>';
        }
    }

    // 渲染註冊表單
    return $this->render_registration_form();
}
```

#### **階段 5: 建立 WordPress 使用者**
```php
// user.php:369-418
$user_data = array(
    'user_login' => wp_slash($userData['username']),
    'user_email' => wp_slash($userData['email']),
    'user_pass'  => $userData['password']
);

if (NextendSocialLogin::$settings->get('store_name') == 1) {
    $name = $this->getAuthUserData('name');
    if (!empty($name)) {
        $user_data['display_name'] = $name;
    }

    $first_name = $this->getAuthUserData('first_name');
    if (!empty($first_name)) {
        $user_data['first_name'] = $first_name;
    }

    $last_name = $this->getAuthUserData('last_name');
    if (!empty($last_name)) {
        $user_data['last_name'] = $last_name;
    }
}

$error = wp_insert_user($user_data);
```

#### **階段 6: 連結 Social Account**
```php
// user.php:473
$this->provider->linkUserToProviderIdentifier($user_id, $this->getAuthUserData('id'), true);
```

- 在 `wp_social_users` 表中建立記錄:
  - `ID`: WordPress user ID
  - `type`: provider 類型 (例如 'line')
  - `identifier`: LINE user ID
  - `register_date`: 註冊日期
  - `link_date`: 連結日期

---

### 5. **Profile 同步機制**

**關鍵檔案**: `includes/user.php:60`, `includes/provider.php:995-996`

```php
// user.php:60
$this->addProfileSyncActions();

// 會觸發以下 actions:
do_action('nsl_' . $this->provider->getId() . '_register_new_user', $user_id, $this->provider);
```

**同步時機**:
- `sync_profile/register`: 註冊時
- `sync_profile/login`: 登入時
- `sync_profile/link`: 連結帳號時

**可同步欄位** (`sync_fields`):
- 各 provider 自訂
- 通常包含: name, email, profile picture URL
- 儲存到 `user_meta` 表中

---

### 6. **大頭貼處理**

**關鍵檔案**: `includes/provider.php:1254-1260`

```php
protected function needUpdateAvatar($user_id) {
    return apply_filters('nsl_avatar_store', NextendSocialLogin::$settings->get('avatar_store'), $user_id, $this);
}

protected function updateAvatar($user_id, $url) {
    do_action('nsl_update_avatar', $this, $user_id, $url);
}
```

**大頭貼儲存方式**:
1. **選項 1**: 儲存 URL 到 user_meta
   - Meta key: `{provider}_profile_picture` (例如 `line_profile_picture`)
   - 停用外掛後,URL 還在,但 WordPress 不會使用

2. **選項 2**: 下載圖片到 WordPress media library
   - 透過 `nsl_update_avatar` action hook
   - 設定為 WordPress user avatar
   - 停用外掛後,avatar 會消失(因為 hook 不再運作)

---

## 我們的外掛缺少什麼?

### ❌ **問題 1: 沒有 Register Flow Page 機制**

**現況**:
- 我們的 callback 直接建立使用者,沒有中間頁面
- 如果缺少 email,無法要求使用者補充

**NSL 做法**:
- 拋出 `NSLContinuePageRenderException`
- 載入帶有 shortcode 的頁面
- 在頁面中顯示表單

---

### ❌ **問題 2: 沒有資料驗證與補充流程**

**現況**:
- 直接用 LINE 提供的資料建立使用者
- 如果 LINE 沒提供 email,註冊會失敗

**NSL 做法**:
- 檢查必要欄位
- 缺少欄位時顯示表單
- 讓使用者補充資料

---

### ❌ **問題 3: Callback 頁面顯示 HTML 原始碼**

**原因**:
- REST API callback 使用 `?>` 輸出 HTML
- REST API 預期回傳 JSON,不是 HTML

**NSL 做法**:
- 不使用 REST API callback
- 使用標準 WordPress URL (例如 `wp-login.php?loginSocial=line`)
- 可以正常輸出 HTML 或 redirect

---

### ❌ **問題 4: 沒有 wp_social_users 表**

**現況**:
- 可能使用 user_meta 儲存 LINE user ID
- 查詢效率較低

**NSL 做法**:
- 建立專用表 `wp_social_users`
- 儲存 user ID, provider type, identifier, dates
- 快速查詢與管理

---

### ❌ **問題 5: Profile 同步未完整實作**

**現況**:
- 不確定是否在每次登入時更新 profile
- 大頭貼儲存方式不明

**NSL 做法**:
- 提供完整的 sync_profile 設定
- 可選擇何時同步: register / login / link
- 提供 sync_fields 機制,可同步任意欄位

---

## 解決方案建議

### **方案 A: 完全模仿 NSL 架構** (推薦)

#### **1. 建立專用資料表**
```sql
CREATE TABLE {wp_prefix}_buygo_line_users (
    ID bigint(20) UNSIGNED NOT NULL,
    identifier varchar(255) NOT NULL,
    register_date datetime NOT NULL,
    link_date datetime NOT NULL,
    login_date datetime DEFAULT NULL,
    PRIMARY KEY (ID),
    UNIQUE KEY identifier (identifier)
);
```

#### **2. 改用標準 WordPress URL 而非 REST API**

**現在**:
```
/wp-json/buygo-line-notify/v1/login/authorize
/wp-json/buygo-line-notify/v1/login/callback
```

**改為**:
```
/wp-login.php?loginSocial=buygo-line&action=authorize
/wp-login.php?loginSocial=buygo-line&action=callback
```

- 掛載到 `login_init` hook
- 可以正常輸出 HTML 或 redirect
- 不會出現 HTML 原始碼問題

#### **3. 實作 Register Flow Page**

**步驟**:
1. 建立設定選項,讓管理員選擇一個頁面
2. 在該頁面插入 shortcode: `[buygo_line_register_flow]`
3. OAuth callback 時:
   - 如果缺少必要資料 → 將資料存入 transient
   - 拋出特殊 Exception 或使用 flag
   - Redirect 到 Register Flow Page
   - Shortcode 讀取 transient,顯示表單
4. 表單提交後:
   - 驗證資料
   - 建立 WordPress 使用者
   - 在 `buygo_line_users` 表建立連結
   - 登入使用者
   - Redirect 到最終目的地

#### **4. 實作完整的 Profile 同步**

```php
class ProfileSyncService {
    public static function sync_on_register($user_id, $line_data) {
        if (SettingsService::get('sync_profile_register')) {
            self::update_user_profile($user_id, $line_data);
        }
    }

    public static function sync_on_login($user_id, $line_data) {
        if (SettingsService::get('sync_profile_login')) {
            self::update_user_profile($user_id, $line_data);
        }
    }

    private static function update_user_profile($user_id, $line_data) {
        // 更新 display_name, first_name, last_name
        wp_update_user([
            'ID' => $user_id,
            'display_name' => $line_data['displayName'] ?? '',
        ]);

        // 更新 user_meta
        update_user_meta($user_id, 'buygo_line_display_name', $line_data['displayName'] ?? '');
        update_user_meta($user_id, 'buygo_line_user_id', $line_data['userId'] ?? '');

        // 更新大頭貼
        if (!empty($line_data['pictureUrl'])) {
            self::update_avatar($user_id, $line_data['pictureUrl']);
        }
    }

    private static function update_avatar($user_id, $picture_url) {
        // 選項 1: 只儲存 URL
        update_user_meta($user_id, 'buygo_line_profile_picture', $picture_url);

        // 選項 2: 下載到 media library (進階)
        // require_once(ABSPATH . 'wp-admin/includes/media.php');
        // require_once(ABSPATH . 'wp-admin/includes/file.php');
        // require_once(ABSPATH . 'wp-admin/includes/image.php');
        // $attachment_id = media_sideload_image($picture_url, 0, null, 'id');
        // update_user_meta($user_id, 'wp_user_avatar', $attachment_id);
    }
}
```

#### **5. 實作 Avatar Filter**

```php
add_filter('get_avatar_url', function($url, $id_or_email, $args) {
    $user = false;
    if (is_numeric($id_or_email)) {
        $user = get_user_by('id', $id_or_email);
    } elseif (is_string($id_or_email)) {
        $user = get_user_by('email', $id_or_email);
    } elseif ($id_or_email instanceof WP_User) {
        $user = $id_or_email;
    }

    if ($user) {
        $line_avatar = get_user_meta($user->ID, 'buygo_line_profile_picture', true);
        if (!empty($line_avatar)) {
            return $line_avatar;
        }
    }

    return $url;
}, 10, 3);
```

---

### **方案 B: 最小改動方案** (快速修復)

如果不想大改架構,只修復目前的問題:

#### **1. 修復 Callback HTML 顯示問題**
- 已在 `LoginAPI::handle_redirect()` 修復
- 但仍建議改用標準 WordPress URL

#### **2. 改善註冊流程**
```php
// 在 callback 中
if (empty($email)) {
    // 產生隨機 email
    $email = 'line_' . $line_user_id . '@buygo-temp.local';
    // 設定 meta 標記需要更新 email
    update_user_meta($new_user_id, 'buygo_line_needs_email', 1);
}
```

#### **3. 新增 Profile 完善頁面**
- 登入後檢查 `buygo_line_needs_email` meta
- 如果為 1,redirect 到 profile 編輯頁面
- 要求使用者填寫真實 email

---

## 結論

**Nextend Social Login 的核心優勢**:
1. ✅ 使用 `NSLContinuePageRenderException` 模式,巧妙地在 OAuth callback 後顯示自訂頁面
2. ✅ Register Flow Page + Shortcode,提供彈性的資料收集流程
3. ✅ 專用資料表,快速管理 social account 連結
4. ✅ 完整的 Profile 同步機制,支援多種時機與欄位
5. ✅ Avatar 整合,停用外掛不會破壞使用者體驗(如果下載到 media library)

**我們的外掛需要改進**:
1. ❌ Callback 使用 REST API 導致 HTML 顯示問題 → 改用標準 WordPress URL
2. ❌ 缺少 Register Flow Page 機制 → 實作 shortcode + custom page
3. ❌ 沒有資料驗證與補充流程 → 實作表單顯示與提交處理
4. ❌ Profile 同步不完整 → 實作完整的 sync 機制
5. ❌ Avatar 整合缺少 → 實作 avatar filter

**建議採用方案 A**,完全重構成 NSL 架構,可獲得最佳使用者體驗和可維護性。
