# LIFF 與 FluentCart 整合分析

**建立日期**: 2026-01-29
**問題**: LIFF 登入無法取得 email，導致無法與 FluentCart Customer 資料對應

---

## 問題陳述

### 用戶反映的問題

> 「當使用者在 LINE 內部瀏覽器造訪我們的網站，並點擊『LINE 登入』按鈕時，之前有很多人反應會卡在裡面，沒辦法直接登入。這似乎是我們使用原生登入時產生的 Bug，所以才想用另外的方式解決。」

**原生 OAuth 在 LINE 瀏覽器的問題：**
1. OAuth redirect 可能被 LINE 瀏覽器攔截
2. Cookie 在 LINE 環境中不穩定
3. Session 可能失效

**LIFF 作為解決方案：**
- LIFF SDK 可在 LINE 瀏覽器中直接取得用戶資訊
- 無需 OAuth redirect
- 登入體驗更流暢

### 核心技術衝突

#### FluentCart Customer 資料結構

```php
// wp_fct_customers 資料表結構
{
  id: Integer (Primary Key),
  user_id: Integer (nullable),  // WordPress user ID，可以是 null
  email: String (required),      // ⚠️ 必填欄位，用於辨識客戶
  first_name: String,
  last_name: String,
  status: String,
  // ... 其他欄位
}
```

**關鍵發現：**
- `email` 是 FluentCart Customer 的**核心識別欄位**（必填）
- `user_id` 是 nullable，表示 Customer **可以不綁定 WordPress 用戶**
- FluentCart 透過 `email` 來辨識和查詢客戶

#### LIFF SDK 權限限制

**預設可取得的資料（無需用戶同意）：**
```javascript
liff.init().then(() => {
  const profile = await liff.getProfile();
  // profile.userId        ✅ 可取得（LINE UID）
  // profile.displayName   ✅ 可取得
  // profile.pictureUrl    ✅ 可取得
  // profile.statusMessage ✅ 可取得
});
```

**需要額外 scope 的資料：**
```javascript
// 需要 email scope 且用戶必須同意
liff.init({ liffId: '...', scope: ['profile', 'openid', 'email'] });
const idToken = liff.getIDToken();
const decodedToken = jwt_decode(idToken);
// decodedToken.email  ⚠️ 需要用戶同意，且很多用戶會拒絕
```

**實務問題：**
1. 許多用戶會拒絕提供 email 權限
2. 如果用戶拒絕，LIFF 登入會失敗
3. 即使取得 email，也可能是假 email（例如 LINE 產生的 noreply email）

---

## 資料對應策略分析

### 方案 1：強制要求 email scope（不建議）

**實作方式：**
```javascript
// LIFF 初始化時要求 email scope
liff.init({
  liffId: 'YOUR_LIFF_ID',
  scope: ['profile', 'openid', 'email']
}).then(() => {
  const idToken = liff.getIDToken();
  const decodedToken = jwt_decode(idToken);

  if (!decodedToken.email) {
    // 用戶拒絕提供 email，登入失敗
    alert('必須提供 email 才能登入');
    return;
  }

  // 使用 email 建立或查詢 FluentCart Customer
});
```

**優點：**
- 邏輯簡單
- 完全符合 FluentCart 的資料結構

**缺點：**
- ❌ **用戶體驗差**：用戶可能拒絕提供 email，導致登入失敗
- ❌ **轉換率低**：增加額外的權限請求步驟，影響轉換率
- ❌ **違背 LIFF 的初衷**：LIFF 應該提供「無痛」的登入體驗

**結論：不推薦此方案**

---

### 方案 2：延遲綁定 + 假 email 策略（推薦）

**核心概念：**
1. LIFF 登入時**先不綁定 FluentCart Customer**
2. 建立 WordPress 用戶（使用 LINE UID 生成假 email）
3. 當用戶第一次結帳或需要 email 時，才要求提供真實 email
4. 提供真實 email 後，自動建立或綁定 FluentCart Customer

**實作流程：**

#### Phase 1: LIFF 登入（無需 email）

```javascript
// LIFF 登入
liff.init({ liffId: 'YOUR_LIFF_ID' }).then(async () => {
  const profile = await liff.getProfile();

  // 傳送 LINE profile 到後端
  fetch('/wp-json/buygo-line-notify/v1/liff/login', {
    method: 'POST',
    body: JSON.stringify({
      line_uid: profile.userId,
      display_name: profile.displayName,
      picture_url: profile.pictureUrl
    })
  });
});
```

#### Phase 2: 後端建立 WordPress 用戶（假 email）

```php
// includes/api/class-liff-api.php
public function liff_login(WP_REST_Request $request) {
    $line_uid = $request->get_param('line_uid');
    $display_name = $request->get_param('display_name');
    $picture_url = $request->get_param('picture_url');

    // 檢查是否已有用戶綁定此 LINE UID
    $user_id = $this->user_service->get_user_by_line_uid($line_uid);

    if (!$user_id) {
        // 建立新的 WordPress 用戶（使用假 email）
        $fake_email = $line_uid . '@line.local';  // 例如：U823e48d8@line.local

        $user_id = $this->user_service->create_user_from_line([
            'userId' => $line_uid,
            'displayName' => $display_name,
            'pictureUrl' => $picture_url,
            'email' => $fake_email  // 假 email
        ]);

        // ⚠️ 重要：此時**不建立** FluentCart Customer
        // 標記此用戶需要提供真實 email
        update_user_meta($user_id, 'buygo_line_needs_real_email', true);
    }

    // 設定 WordPress Auth Cookie
    wp_set_auth_cookie($user_id, true);

    return rest_ensure_response([
        'success' => true,
        'user_id' => $user_id,
        'needs_email' => get_user_meta($user_id, 'buygo_line_needs_real_email', true)
    ]);
}
```

#### Phase 3: 結帳時要求真實 email

```php
// includes/services/class-fluentcart-integration-service.php
class FluentCartIntegrationService {

    /**
     * FluentCart 結帳流程 Hook
     */
    public function register_hooks() {
        // 在結帳頁面檢查是否需要 email
        add_action('fluent_cart/before_checkout_form', [$this, 'check_customer_email'], 10);

        // 訂單建立前確保有 FluentCart Customer
        add_filter('fluent_cart/before_order_create', [$this, 'ensure_customer_exists'], 10, 2);
    }

    /**
     * 檢查當前用戶是否需要提供 email
     */
    public function check_customer_email() {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        $needs_email = get_user_meta($user_id, 'buygo_line_needs_real_email', true);

        if ($needs_email) {
            // 顯示「請提供 email」提示
            echo '<div class="buygo-email-notice">';
            echo '<p>為了完成結帳，請提供您的 Email 地址</p>';
            echo '<input type="email" name="customer_email" required />';
            echo '</div>';
        }
    }

    /**
     * 訂單建立前確保有 FluentCart Customer
     */
    public function ensure_customer_exists($order_data, $request) {
        if (!is_user_logged_in()) {
            return $order_data;
        }

        $user_id = get_current_user_id();
        $needs_email = get_user_meta($user_id, 'buygo_line_needs_real_email', true);

        if ($needs_email) {
            // 取得用戶提供的真實 email
            $real_email = $request->get_param('customer_email');

            if (!$real_email) {
                throw new \Exception('請提供 Email 地址');
            }

            // 更新 WordPress 用戶的 email
            wp_update_user([
                'ID' => $user_id,
                'user_email' => $real_email
            ]);

            // 建立或取得 FluentCart Customer
            $customer = $this->get_or_create_fluentcart_customer($user_id, $real_email);

            // 標記已完成 email 設定
            delete_user_meta($user_id, 'buygo_line_needs_real_email');

            // 更新 order_data 的 customer_id
            $order_data['customer_id'] = $customer->id;
        }

        return $order_data;
    }

    /**
     * 取得或建立 FluentCart Customer
     */
    private function get_or_create_fluentcart_customer($user_id, $email) {
        // 檢查是否已有 Customer
        $customer = \FluentCart\App\Models\Customer::where('email', $email)->first();

        if ($customer) {
            // 綁定 WordPress user_id（如果尚未綁定）
            if (!$customer->user_id) {
                $customer->user_id = $user_id;
                $customer->save();
            }
            return $customer;
        }

        // 建立新的 FluentCart Customer
        $user = get_user_by('id', $user_id);
        $line_profile = $this->get_line_profile($user_id);

        $customer = \FluentCart\App\Models\Customer::create([
            'user_id' => $user_id,
            'email' => $email,
            'first_name' => $line_profile['first_name'] ?? $user->first_name,
            'last_name' => $line_profile['last_name'] ?? $user->last_name,
            'status' => 'active'
        ]);

        return $customer;
    }
}
```

**優點：**
- ✅ **用戶體驗佳**：LIFF 登入無需額外權限，流程順暢
- ✅ **轉換率高**：不會因為權限請求而嚇跑用戶
- ✅ **資料完整性**：最終還是會取得真實 email
- ✅ **彈性高**：用戶可以先瀏覽、加入購物車，結帳時才提供 email

**缺點：**
- ⚠️ 增加一點實作複雜度
- ⚠️ 需要在結帳流程中加入 email 收集步驟

**結論：推薦此方案**

---

### 方案 3：完全不使用 LIFF（回到原始 OAuth）

**實作方式：**
- 繼續使用原生 LINE Login OAuth 流程
- 不實作 LIFF
- 專注於優化 OAuth 流程的穩定性

**優點：**
- ✅ 可以取得 email（OAuth 流程預設包含 email scope）
- ✅ 符合 FluentCart 資料結構
- ✅ 不需要延遲綁定邏輯

**缺點：**
- ❌ **用戶反映的問題無法解決**：LINE 瀏覽器中登入仍會卡住
- ❌ **用戶體驗差**：需要多次 redirect
- ❌ **技術風險**：OAuth callback 在 LINE 環境中不穩定

**結論：不推薦此方案（問題根源未解決）**

---

## 推薦方案：延遲綁定 + 假 email 策略

### 實作階段規劃

#### Phase 4: LIFF 整合（修改後的需求）

**修改後的 Success Criteria：**
1. LIFF 頁面已建立且包含 LIFF SDK，可在 LINE 內建瀏覽器中載入
2. 系統可偵測 LINE 瀏覽器（User-Agent 判斷）並自動導向 LIFF 頁面
3. 用戶在 LINE 瀏覽器中可透過 LIFF SDK 自動登入（**無需提供 email**）
4. WordPress Auth Cookie 在 LINE 環境中正常運作（用戶保持登入狀態）
5. 登入完成後用戶會被導回原始頁面（保留 returnUrl 參數）
6. ✨ **新增**：用戶標記為「需要提供 email」（`buygo_line_needs_real_email` meta）
7. ✨ **新增**：LIFF 登入時**不建立** FluentCart Customer

#### Phase 4.1: FluentCart 延遲綁定（新增階段）

**Goal**: 在用戶首次結帳時收集 email 並建立 FluentCart Customer

**Requirements**:
- **FLUENTCART-01**: 結帳頁面偵測「需要 email」狀態並顯示 email 輸入欄位
- **FLUENTCART-02**: 訂單建立前驗證 email 並建立 FluentCart Customer
- **FLUENTCART-03**: 更新 WordPress 用戶的 email 為真實 email
- **FLUENTCART-04**: 綁定 WordPress user_id 到 FluentCart Customer
- **FLUENTCART-05**: 清除「需要 email」標記

**Success Criteria**:
1. 透過 LIFF 登入的用戶在結帳頁面會看到「請提供 Email」提示
2. 用戶無法在未提供 email 的情況下完成結帳
3. 提供 email 後，系統自動建立 FluentCart Customer（如果 email 不存在）
4. 如果 email 已存在於 FluentCart，自動綁定 WordPress user_id
5. 用戶下次登入時不再需要提供 email

---

## 技術實作細節

### 資料流程圖

```
┌─────────────────────────────────────────────────────────────┐
│ 用戶在 LINE 瀏覽器中點擊「登入」                              │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ 1. 偵測 LINE 瀏覽器 → 導向 LIFF 頁面                          │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ 2. LIFF SDK 取得 profile (userId, displayName, pictureUrl)  │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│ 3. 後端檢查 LINE UID 是否已綁定                               │
└─────────────────────────────────────────────────────────────┘
         │                                    │
         │ 已綁定                              │ 未綁定
         ▼                                    ▼
┌─────────────────────┐          ┌──────────────────────────┐
│ 4a. 登入現有用戶      │          │ 4b. 建立新 WP 用戶        │
│                     │          │   - 假 email            │
│                     │          │   - LINE profile        │
│                     │          │   - 標記需要真實 email   │
└─────────────────────┘          └──────────────────────────┘
         │                                    │
         └─────────────┬──────────────────────┘
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 5. 設定 WordPress Auth Cookie                                │
└─────────────────────────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 6. 用戶瀏覽網站、加入購物車（無需 FluentCart Customer）        │
└─────────────────────────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 7. 用戶進入結帳頁面                                           │
└─────────────────────────────────────────────────────────────┘
                       │
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 8. 檢查「需要 email」標記                                     │
└─────────────────────────────────────────────────────────────┘
         │                                    │
         │ 已有真實 email                      │ 需要真實 email
         ▼                                    ▼
┌─────────────────────┐          ┌──────────────────────────┐
│ 9a. 直接使用現有     │          │ 9b. 顯示 email 輸入欄位   │
│    FluentCart        │          │                         │
│    Customer          │          │                         │
└─────────────────────┘          └──────────────────────────┘
         │                                    │
         │                                    ▼
         │                       ┌──────────────────────────┐
         │                       │ 10. 用戶提供真實 email    │
         │                       └──────────────────────────┘
         │                                    │
         │                                    ▼
         │                       ┌──────────────────────────┐
         │                       │ 11. 更新 WP 用戶 email    │
         │                       │     建立 FluentCart       │
         │                       │     Customer             │
         │                       │     清除「需要 email」標記 │
         │                       └──────────────────────────┘
         │                                    │
         └─────────────┬──────────────────────┘
                       ▼
┌─────────────────────────────────────────────────────────────┐
│ 12. 完成結帳（使用 FluentCart Customer）                      │
└─────────────────────────────────────────────────────────────┘
```

### 資料儲存結構

#### WordPress 用戶（透過 LIFF 登入）

```php
// wp_users
{
  ID: 123,
  user_login: 'fish',  // 從 displayName 生成
  user_email: 'U823e48d8@line.local',  // 假 email（初次登入）
  // 或
  user_email: 'real@example.com',  // 真實 email（提供後）
}

// wp_usermeta
{
  user_id: 123,
  meta_key: 'buygo_line_uid',
  meta_value: 'U823e48d899eb99be6fb49d53609048d9'
}
{
  user_id: 123,
  meta_key: 'buygo_line_needs_real_email',
  meta_value: true  // 初次登入時設定，提供真實 email 後刪除
}
```

#### LINE 綁定表

```php
// wp_buygo_line_bindings
{
  id: 1,
  user_id: 123,
  line_uid: 'U823e48d899eb99be6fb49d53609048d9',
  display_name: 'Fish 老魚',
  picture_url: 'https://...',
  bound_at: '2026-01-29 10:00:00'
}
```

#### FluentCart Customer（延遲建立）

```php
// wp_fct_customers
// ⚠️ 初次 LIFF 登入時**不建立**
// ✅ 用戶提供真實 email 後才建立

{
  id: 456,
  user_id: 123,  // 綁定 WordPress 用戶
  email: 'real@example.com',  // 真實 email
  first_name: 'Fish',
  last_name: '老魚',
  status: 'active'
}
```

---

## 風險與緩解措施

### 風險 1：用戶拒絕提供 email

**風險描述：**
用戶在結帳頁面仍然可能拒絕提供 email，導致無法完成結帳。

**緩解措施：**
1. **UI/UX 優化**：清楚說明為何需要 email（訂單通知、會員權益等）
2. **社交證明**：顯示「已有 X 位用戶透過 LINE 登入並購物」
3. **信任標記**：強調「我們不會將您的 email 用於行銷」
4. **替代方案**：如果真的拒絕，提供「訪客結帳」選項（不綁定 LINE）

### 風險 2：email 重複（已有其他帳號使用相同 email）

**風險描述：**
用戶提供的 email 可能已被其他 WordPress 帳號或 FluentCart Customer 使用。

**緩解措施：**
1. **檢查 WordPress 用戶**：如果 email 已被其他 WP 用戶使用，提示「此 email 已註冊，請登入後綁定 LINE」
2. **檢查 FluentCart Customer**：如果 email 已有 Customer，檢查是否已綁定 user_id
   - 如果未綁定：直接綁定當前 user_id
   - 如果已綁定其他 user_id：提示錯誤，要求使用其他 email 或合併帳號（管理員操作）

### 風險 3：用戶先透過 OAuth 登入，後來在 LINE 瀏覽器中又登入

**風險描述：**
同一個用戶可能在不同環境中使用不同登入方式，導致建立多個帳號。

**緩解措施：**
1. **LINE UID 唯一性檢查**：所有登入流程（OAuth 和 LIFF）都先檢查 LINE UID 是否已綁定
2. **如果已綁定**：直接登入現有帳號，不建立新帳號
3. **Email 比對**：如果 LIFF 登入時用戶提供的 email 與現有 OAuth 帳號的 email 相同，自動合併

---

## 實作優先順序

### 立即實作（Phase 4）
1. ✅ LIFF 頁面和 SDK 整合
2. ✅ LINE 瀏覽器偵測
3. ✅ LIFF 登入流程（建立 WordPress 用戶 + 假 email）
4. ✅ 標記「需要真實 email」

### 接續實作（Phase 4.1）
5. ✅ FluentCart 結帳頁面 email 收集
6. ✅ 訂單建立前驗證並建立 Customer
7. ✅ Email 重複檢查和錯誤處理
8. ✅ 合併帳號機制（如需要）

### 後續優化（Phase 5+）
9. 🔄 UI/UX 優化（信任標記、社交證明）
10. 🔄 管理員工具（手動合併帳號）
11. 🔄 通知系統整合（email 確認通知）

---

## 結論

**推薦方案：延遲綁定 + 假 email 策略**

這個方案在「用戶體驗」和「資料完整性」之間取得最佳平衡：

1. **用戶體驗**：LIFF 登入流程順暢，無需額外權限請求
2. **資料完整性**：最終仍會取得真實 email 並建立 FluentCart Customer
3. **技術可行性**：不需要改變 FluentCart 核心資料結構
4. **業務價值**：提高轉換率（不會在登入階段嚇跑用戶）

**下一步行動：**
1. 更新 ROADMAP.md，修改 Phase 4 的 Success Criteria
2. 新增 Phase 4.1（FluentCart 延遲綁定）
3. 開始實作 Phase 4（LIFF 整合）

---

*分析完成: 2026-01-29*
