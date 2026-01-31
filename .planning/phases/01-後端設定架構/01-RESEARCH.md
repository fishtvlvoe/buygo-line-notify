# Phase 1: 後端設定架構 - Research

**Researched:** 2026-02-01
**Domain:** WordPress Plugin Settings Management with REST API
**Confidence:** HIGH

## Summary

本次研究針對 buygo-line-notify 外掛的後端設定架構進行調查，目標是建立統一的設定管理系統，讓所有 LINE 整合功能的設定都能透過 REST API 進行儲存和讀取。

經過分析現有程式碼和 WordPress 官方文件，**推薦使用現有的 Options API 搭配強化的 SettingsService 類別，並建立專用的 REST API 端點**。這種方法具有以下優勢：

1. **延續現有架構**：現有 SettingsService 已經實作了加密、向後相容等功能，只需擴展而非重寫
2. **符合 WordPress 標準**：使用 Options API 儲存設定值，符合外掛開發最佳實踐
3. **REST API 整合**：建立專用端點讓前端 SPA 可以透過 API 操作設定

**Primary recommendation:** 擴展現有 SettingsService，加入完整的設定 Schema 定義和 REST API 端點，使用 WordPress Options API 儲存設定值。

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress Options API | 6.0+ | 設定值儲存 | WordPress 內建，支援自動快取，適合 key-value 設定 |
| WordPress REST API | 6.0+ | 設定值 CRUD | WordPress 內建，支援驗證、清理、權限控制 |
| PHP openssl | 8.0+ | 敏感資料加密 | 現有架構已使用，向後相容 |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| wp_nonce | core | CSRF 保護 | 所有表單提交和 AJAX 請求 |
| sanitize_* 函式 | core | 資料清理 | 所有使用者輸入 |
| WP_Error | core | 錯誤處理 | API 回應錯誤訊息 |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Options API | Custom Table | Options API 更簡單，custom table 適合大量結構化資料 |
| Single Option Key | Multiple Option Keys | 單一 key (陣列) 效能更好，多個 key 更容易管理獨立設定 |
| REST API | Admin AJAX | REST API 更標準化，AJAX 需要更多手動處理 |

**推薦方案：** 使用 **群組化的 Options** - 將相關設定分組儲存（如 `buygo_line_button_settings`、`buygo_line_sync_settings`），平衡效能和可維護性。

## Architecture Patterns

### Recommended Project Structure
```
includes/
├── services/
│   └── class-settings-service.php   # 強化的設定服務（已存在，需擴展）
├── api/
│   └── class-settings-api.php       # 新增：設定 REST API 端點
├── schemas/
│   └── class-settings-schema.php    # 新增：設定驗證 Schema
└── admin/
    └── class-settings-page.php      # 現有（未來可簡化為純前端）
```

### Pattern 1: Grouped Options Storage
**What:** 將相關設定儲存為 JSON 陣列在單一 option key
**When to use:** 設定項目有邏輯分組且經常一起讀取
**Example:**
```php
// Source: WordPress Options API Best Practices
// 設定群組定義
const OPTION_GROUPS = [
    'messaging_api' => [
        'channel_access_token' => ['type' => 'string', 'encrypted' => true],
        'channel_secret' => ['type' => 'string', 'encrypted' => true],
    ],
    'login' => [
        'channel_id' => ['type' => 'string', 'encrypted' => true],
        'channel_secret' => ['type' => 'string', 'encrypted' => true],
        'default_redirect_url' => ['type' => 'url', 'default' => ''],
    ],
    'button' => [
        'login_position' => ['type' => 'enum', 'values' => ['before', 'after', 'hidden']],
        'register_position' => ['type' => 'enum', 'values' => ['before', 'after', 'hidden']],
        'login_text' => ['type' => 'string', 'default' => '使用 LINE 登入'],
        'register_text' => ['type' => 'string', 'default' => '使用 LINE 註冊'],
        'bind_text' => ['type' => 'string', 'default' => '綁定 LINE 帳號'],
        'unbind_text' => ['type' => 'string', 'default' => '解除 LINE 綁定'],
        'style' => ['type' => 'enum', 'values' => ['official', 'minimal', 'custom']],
    ],
    'email' => [
        'capture_enabled' => ['type' => 'boolean', 'default' => true],
        'required' => ['type' => 'boolean', 'default' => false],
        'source' => ['type' => 'enum', 'values' => ['line_profile', 'user_input']],
    ],
    'integrations' => [
        'fluentcart_enabled' => ['type' => 'boolean', 'default' => false],
        'woocommerce_enabled' => ['type' => 'boolean', 'default' => false],
        'other_login_enabled' => ['type' => 'boolean', 'default' => false],
    ],
    'sync' => [
        'on_login' => ['type' => 'boolean', 'default' => false],
        'fields' => ['type' => 'array', 'default' => ['display_name', 'avatar', 'email']],
        'conflict_strategy' => ['type' => 'enum', 'values' => ['line_priority', 'wordpress_priority', 'manual']],
    ],
    'liff' => [
        'id' => ['type' => 'string', 'default' => ''],
        'endpoint_url' => ['type' => 'url', 'default' => ''],
    ],
];
```

### Pattern 2: REST API Controller Pattern
**What:** 繼承 WP_REST_Controller 建立設定端點
**When to use:** 需要標準化的 REST API 端點
**Example:**
```php
// Source: https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/
class Settings_API extends \WP_REST_Controller {
    protected $namespace = 'buygo-line-notify/v1';
    protected $rest_base = 'settings';

    public function register_routes() {
        // GET /wp-json/buygo-line-notify/v1/settings
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_settings'],
                'permission_callback' => [$this, 'get_settings_permissions_check'],
            ],
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_settings'],
                'permission_callback' => [$this, 'update_settings_permissions_check'],
                'args' => $this->get_endpoint_args_for_item_schema(\WP_REST_Server::EDITABLE),
            ],
        ]);

        // GET /wp-json/buygo-line-notify/v1/settings/{group}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<group>[a-z_]+)', [
            [
                'methods' => \WP_REST_Server::READABLE,
                'callback' => [$this, 'get_settings_group'],
                'permission_callback' => [$this, 'get_settings_permissions_check'],
                'args' => [
                    'group' => [
                        'required' => true,
                        'validate_callback' => [$this, 'validate_group'],
                    ],
                ],
            ],
            [
                'methods' => \WP_REST_Server::EDITABLE,
                'callback' => [$this, 'update_settings_group'],
                'permission_callback' => [$this, 'update_settings_permissions_check'],
            ],
        ]);
    }

    public function get_settings_permissions_check($request) {
        return current_user_can('manage_options');
    }

    public function update_settings_permissions_check($request) {
        return current_user_can('manage_options');
    }
}
```

### Pattern 3: Settings Schema with Validation
**What:** 定義設定 Schema 用於驗證和預設值
**When to use:** 需要統一管理設定結構、驗證規則和預設值
**Example:**
```php
// Source: WordPress REST API Handbook
class Settings_Schema {
    public static function get_schema(): array {
        return [
            'button' => [
                'login_position' => [
                    'type' => 'string',
                    'enum' => ['before', 'after', 'hidden'],
                    'default' => 'before',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'login_text' => [
                    'type' => 'string',
                    'default' => '使用 LINE 登入',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function($value) {
                        return is_string($value) && mb_strlen($value) <= 50;
                    },
                ],
                'style' => [
                    'type' => 'string',
                    'enum' => ['official', 'minimal', 'custom'],
                    'default' => 'official',
                ],
            ],
            // ... other groups
        ];
    }

    public static function validate(string $group, array $data): array|\WP_Error {
        $schema = self::get_schema()[$group] ?? null;
        if (!$schema) {
            return new \WP_Error('invalid_group', '無效的設定群組', ['status' => 400]);
        }

        $errors = [];
        $validated = [];

        foreach ($schema as $key => $rules) {
            $value = $data[$key] ?? $rules['default'] ?? null;

            // Type validation
            if (isset($rules['enum']) && !in_array($value, $rules['enum'], true)) {
                $errors[$key] = sprintf('值必須是 %s 之一', implode(', ', $rules['enum']));
                continue;
            }

            // Custom validation
            if (isset($rules['validate_callback']) && !$rules['validate_callback']($value)) {
                $errors[$key] = '驗證失敗';
                continue;
            }

            // Sanitization
            if (isset($rules['sanitize_callback'])) {
                $value = call_user_func($rules['sanitize_callback'], $value);
            }

            $validated[$key] = $value;
        }

        if (!empty($errors)) {
            return new \WP_Error('validation_failed', '設定驗證失敗', [
                'status' => 400,
                'errors' => $errors,
            ]);
        }

        return $validated;
    }
}
```

### Anti-Patterns to Avoid
- **硬編碼設定值**：所有設定都應從 SettingsService 讀取，不要在程式碼中寫死
- **直接使用 $_POST**：務必透過 REST API 或 AJAX 處理，確保驗證和清理
- **跳過權限檢查**：所有設定端點必須檢查 `manage_options` capability
- **未驗證即儲存**：所有輸入必須先驗證再儲存，使用 Schema 確保一致性
- **回傳敏感資料**：API 回應不應包含解密後的 token/secret，只回傳遮蔽版本

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| 資料加密 | 自製加密演算法 | 現有 SettingsService::encrypt() | 已實作 AES-128-ECB，向後相容 |
| 權限檢查 | 自製權限系統 | current_user_can('manage_options') | WordPress 內建，可靠 |
| 資料清理 | 正則表達式過濾 | sanitize_* 系列函式 | WordPress 維護，處理邊界情況 |
| Nonce 驗證 | 自製 token | wp_verify_nonce() | WordPress 內建，時效性保護 |
| JSON Schema 驗證 | 手動驗證每個欄位 | rest_validate_request_arg() | REST API 內建驗證 |

**Key insight:** WordPress 已經提供了完整的安全基礎設施，自製解決方案容易有漏洞且難以維護。

## Common Pitfalls

### Pitfall 1: 忘記 Permission Callback
**What goes wrong:** REST API 端點可被任何人存取
**Why it happens:** WordPress 5.5 之後 permission_callback 是必填，但開發者可能使用舊範例
**How to avoid:** 總是明確定義 permission_callback，公開端點使用 `__return_true`
**Warning signs:** 未登入用戶可以看到設定頁面資料

### Pitfall 2: 加密資料驗證問題
**What goes wrong:** 嘗試驗證已加密的資料格式
**Why it happens:** 忘記加密後的資料是 base64 字串
**How to avoid:** 在加密前驗證，或在解密後驗證
**Warning signs:** 驗證規則對加密資料失敗

### Pitfall 3: Options 快取問題
**What goes wrong:** 更新設定後前端顯示舊值
**Why it happens:** WordPress Options API 有物件快取
**How to avoid:** 使用 `wp_cache_delete()` 或等待快取過期
**Warning signs:** 需要刷新頁面才能看到更新

### Pitfall 4: API 回應包含敏感資料
**What goes wrong:** Token、Secret 等敏感資料暴露在 API 回應
**Why it happens:** 直接序列化整個設定陣列
**How to avoid:** API 回應時遮蔽敏感欄位（如只顯示最後 4 字元）
**Warning signs:** 開發者工具可以看到完整 token

### Pitfall 5: 預設值不一致
**What goes wrong:** 不同地方使用不同的預設值
**Why it happens:** 預設值分散在多處程式碼
**How to avoid:** 在 Schema 中集中定義所有預設值
**Warning signs:** 新安裝的外掛行為與文件不符

## Code Examples

### 完整的設定讀取（含預設值）
```php
// Source: 基於現有 SettingsService 模式擴展
class SettingsService {
    private static array $defaults = [
        'button' => [
            'login_position' => 'before',
            'register_position' => 'after',
            'login_text' => '使用 LINE 登入',
            'register_text' => '使用 LINE 註冊',
            'bind_text' => '綁定 LINE 帳號',
            'unbind_text' => '解除 LINE 綁定',
            'style' => 'official',
        ],
        'email' => [
            'capture_enabled' => true,
            'required' => false,
            'source' => 'line_profile',
        ],
        'integrations' => [
            'fluentcart_enabled' => false,
            'woocommerce_enabled' => false,
            'other_login_enabled' => false,
        ],
        'sync' => [
            'on_login' => false,
            'fields' => ['display_name', 'avatar', 'email'],
            'conflict_strategy' => 'line_priority',
        ],
    ];

    public static function get_group(string $group): array {
        $option_key = "buygo_line_{$group}";
        $stored = get_option($option_key, []);
        $defaults = self::$defaults[$group] ?? [];

        // 合併預設值和儲存的值
        return wp_parse_args($stored, $defaults);
    }

    public static function set_group(string $group, array $data): bool {
        $option_key = "buygo_line_{$group}";

        // 處理加密欄位
        if (isset(self::$encrypted_groups[$group])) {
            foreach (self::$encrypted_groups[$group] as $field) {
                if (!empty($data[$field])) {
                    $data[$field] = self::encrypt($data[$field]);
                }
            }
        }

        return update_option($option_key, $data);
    }
}
```

### REST API 設定端點回應格式
```php
// Source: WordPress REST API Best Practices
public function get_settings(\WP_REST_Request $request): \WP_REST_Response {
    $settings = [];

    foreach (self::GROUPS as $group) {
        $group_data = SettingsService::get_group($group);

        // 遮蔽敏感欄位
        if (isset(self::SENSITIVE_FIELDS[$group])) {
            foreach (self::SENSITIVE_FIELDS[$group] as $field) {
                if (!empty($group_data[$field])) {
                    $group_data[$field] = $this->mask_value($group_data[$field]);
                    $group_data[$field . '_set'] = true;
                }
            }
        }

        $settings[$group] = $group_data;
    }

    return new \WP_REST_Response([
        'success' => true,
        'data' => $settings,
    ], 200);
}

private function mask_value(string $value): string {
    $length = strlen($value);
    if ($length <= 8) {
        return str_repeat('*', $length);
    }
    return str_repeat('*', $length - 4) . substr($value, -4);
}
```

### 設定更新驗證錯誤回應
```php
// Source: WordPress REST API Error Handling
public function update_settings(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
    $group = $request->get_param('group');
    $data = $request->get_json_params();

    // 驗證群組
    if (!in_array($group, self::GROUPS, true)) {
        return new \WP_Error(
            'invalid_settings_group',
            '無效的設定群組',
            ['status' => 400]
        );
    }

    // 驗證資料
    $validated = Settings_Schema::validate($group, $data);

    if (is_wp_error($validated)) {
        return $validated;
    }

    // 儲存設定
    $result = SettingsService::set_group($group, $validated);

    if (!$result) {
        return new \WP_Error(
            'settings_update_failed',
            '設定儲存失敗',
            ['status' => 500]
        );
    }

    return new \WP_REST_Response([
        'success' => true,
        'message' => '設定已更新',
        'data' => SettingsService::get_group($group),
    ], 200);
}
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Settings API + add_settings_section | REST API + SPA 前端 | WordPress 5.0+ | 更靈活的前端架構 |
| 每個設定獨立 option key | 群組化 option 儲存 | 效能最佳化 | 減少資料庫查詢 |
| 表單 POST 提交 | REST API CRUD | WordPress 4.7+ | 更好的 UX 和錯誤處理 |
| 無 permission_callback | 必填 permission_callback | WordPress 5.5 | 安全性強化 |

**Deprecated/outdated:**
- Settings API 的 `add_settings_section()` / `add_settings_field()` 仍可用，但對於 SPA 前端架構不太適合
- 直接使用 `$_POST` 處理設定已不推薦，應透過 REST API

## Data Structure for BACKEND Requirements

### BACKEND-01: 統一設定管理 Service
```php
// Option Keys 結構
buygo_line_messaging_api = {
    'channel_access_token': 'encrypted_value',
    'channel_secret': 'encrypted_value'
}

buygo_line_login = {
    'channel_id': 'encrypted_value',
    'channel_secret': 'encrypted_value',
    'default_redirect_url': 'https://...'
}
```

### BACKEND-02: Email 擷取設定
```php
buygo_line_email = {
    'capture_enabled': true,    // 是否擷取 Email
    'required': false,          // 是否必填
    'source': 'line_profile'    // 'line_profile' | 'user_input'
}
```

### BACKEND-03: 按鈕位置設定
```php
buygo_line_button = {
    'login_position': 'before',     // 'before' | 'after' | 'hidden'
    'register_position': 'after',   // 'before' | 'after' | 'hidden'
    'shortcode_mode': false         // 使用 shortcode 自訂位置
}
```

### BACKEND-04: 按鈕文字自訂
```php
buygo_line_button = {
    // ... 位置設定
    'login_text': '使用 LINE 登入',
    'register_text': '使用 LINE 註冊',
    'bind_text': '綁定 LINE 帳號',
    'unbind_text': '解除 LINE 綁定'
}
```

### BACKEND-05: 按鈕樣式設定
```php
buygo_line_button = {
    // ... 其他設定
    'style': 'official',            // 'official' | 'minimal' | 'custom'
    'custom_class': ''              // 自訂 CSS class
}
```

### BACKEND-06: 外掛整合開關
```php
buygo_line_integrations = {
    'fluentcart_enabled': true,
    'woocommerce_enabled': false,
    'other_login_enabled': false
}
```

### BACKEND-07: Profile Sync 衝突策略
```php
buygo_line_sync = {
    'on_login': true,
    'fields': ['display_name', 'avatar', 'email'],
    'conflict_strategy': 'line_priority'  // 'line_priority' | 'wordpress_priority' | 'manual'
}
```

### BACKEND-08: LIFF 設定欄位（預留）
```php
buygo_line_liff = {
    'id': '',
    'endpoint_url': ''
}
```

### BACKEND-09: 驗證規則摘要
| 欄位類型 | 驗證規則 | 清理函式 |
|---------|---------|---------|
| enum | in_array() 檢查 | sanitize_text_field |
| boolean | is_bool() 或轉換 | rest_sanitize_boolean |
| string | mb_strlen() 長度限制 | sanitize_text_field |
| url | wp_http_validate_url() | esc_url_raw |
| encrypted | 先驗證再加密 | 自訂加密邏輯 |

### BACKEND-10: REST API 端點設計
| Endpoint | Method | Purpose |
|----------|--------|---------|
| /settings | GET | 取得所有設定（敏感欄位遮蔽） |
| /settings | POST | 批次更新設定 |
| /settings/{group} | GET | 取得特定群組設定 |
| /settings/{group} | POST | 更新特定群組設定 |

## Open Questions

### 1. 現有設定遷移策略
- **What we know:** 現有設定使用 `buygo_line_{key}` 格式單獨儲存
- **What's unclear:** 是否需要遷移到群組化格式，或維持向後相容
- **Recommendation:** 在 SettingsService 中維持向後相容讀取，新設定使用群組化格式

### 2. 前端狀態管理
- **What we know:** 研究範圍是後端架構，前端將在 Phase 2
- **What's unclear:** 前端如何處理即時預覽和暫存狀態
- **Recommendation:** API 設計時預留 `draft` 欄位支援預覽功能

## Sources

### Primary (HIGH confidence)
- [WordPress REST API Handbook - Adding Custom Endpoints](https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/) - REST API 端點建立最佳實踐
- [WordPress Options API](https://developer.wordpress.org/plugins/settings/options-api/) - Options API 使用模式
- [WordPress Sanitizing Data](https://developer.wordpress.org/apis/security/sanitizing/) - 資料清理函式參考
- 現有 buygo-line-notify 程式碼庫 - SettingsService、Login_API 實作參考

### Secondary (MEDIUM confidence)
- [WordPress Settings API vs Options API](https://wpmayor.com/settings-api-vs-options-api/) - API 選擇比較
- [Securing Custom WordPress API Endpoints](https://wp-rocket.me/blog/wordpress-api-endpoints/) - 安全性最佳實踐

### Tertiary (LOW confidence)
- Nextend Social Login 設定架構 - 無法取得詳細技術文件，僅供介面參考

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - 基於 WordPress 官方文件和現有程式碼
- Architecture: HIGH - 延續現有架構模式，有充分參考
- Pitfalls: MEDIUM - 基於開發經驗和社群文章
- Data structure: HIGH - 根據 UI-DESIGN-SPEC.md 需求明確定義

**Research date:** 2026-02-01
**Valid until:** 2026-03-01 (30 days - WordPress 生態系統穩定)
