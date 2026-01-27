# Phase 2: Webhook 系統 - Research

**Researched:** 2026-01-28
**Domain:** WordPress REST API, LINE Messaging API, Webhook Processing
**Confidence:** HIGH

## Summary

本研究調查了實作 LINE Messaging API Webhook 處理系統的最佳實踐，涵蓋簽名驗證、事件去重、背景處理和 WordPress Hooks 整合。研究基於 buygo-plus-one-dev 現有實作、LINE 官方文件和 2026 年最新的 WordPress REST API 安全最佳實踐。

重點發現：
1. **簽名驗證**採用 HMAC-SHA256，使用 hash_equals() 防止時序攻擊
2. **事件去重**使用 webhookEventId + WordPress Transient（60 秒有效期）
3. **背景處理**優先使用 fastcgi_finish_request()，備援使用 wp_schedule_single_event()
4. **Verify Event** 的 replyToken 為 32 個零（00000000000000000000000000000000）
5. **Hooks 機制**使用 WordPress action hooks 讓其他外掛註冊事件處理器

**Primary recommendation:** 採用 buygo-plus-one-dev 現有架構，保持 API endpoint、簽名驗證和背景處理邏輯不變，僅遷移基礎設施到新外掛，業務邏輯透過 Hooks 保留在原外掛。

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress REST API | 內建 | Webhook endpoint 註冊 | WordPress 標準 API，支援權限控制和路由 |
| LINE Messaging API | 最新 | Webhook 事件來源 | LINE 官方 API，提供 webhookEventId 和簽名驗證 |
| WordPress Transients | 內建 | 事件去重快取 | 輕量級快取機制，支援自動過期 |
| OpenSSL | PHP 內建 | HMAC-SHA256 簽名驗證 | 業界標準加密函式庫 |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| WordPress Cron | 內建 | 背景處理備援 | 非 PHP-FPM 環境或無法使用 fastcgi_finish_request() |
| WordPress Hooks | 內建 | 事件通知機制 | 讓其他外掛註冊並處理 Webhook 事件 |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Transients | Custom DB Table | Transients 更輕量但無法保證持久化；自訂表可追蹤歷史但增加複雜度 |
| WordPress Cron | Action Scheduler | Action Scheduler 更強大但需額外依賴；WP Cron 內建但依賴流量觸發 |
| fastcgi_finish_request | Async HTTP Request | Async request 相容性好但開銷大；fastcgi 效能佳但僅限 PHP-FPM |

**Installation:**
```bash
# 無需安裝，全部使用 WordPress 和 PHP 內建功能
```

## Architecture Patterns

### Recommended Project Structure
```
includes/
├── api/
│   └── class-line-webhook-api.php    # REST endpoint 註冊與路由
├── services/
│   └── class-webhook-handler.php      # 事件處理邏輯（透過 Hooks 通知）
└── class-plugin.php                   # 註冊 REST routes
```

### Pattern 1: REST API Endpoint 註冊

**What:** 使用 WordPress REST API 註冊 Webhook endpoint
**When to use:** 所有需要接收外部 HTTP 請求的場景
**Example:**
```php
// Source: buygo-plus-one-dev/includes/api/class-line-webhook-api.php (verified)
public function register_routes() {
    register_rest_route(
        'buygo-line-notify/v1',  // 命名空間
        '/webhook',              // 路由
        array(
            'methods'             => 'POST',
            'callback'            => array($this, 'handle_webhook'),
            'permission_callback' => '__return_true',  // 不驗證 WordPress 權限，改用簽名驗證
        )
    );
}
```

**重要：** `permission_callback` 設為 `__return_true` 因為 LINE 不會發送 WordPress nonce，改用 x-line-signature header 驗證。

### Pattern 2: HMAC-SHA256 簽名驗證

**What:** 驗證 Webhook 請求來自 LINE 官方伺服器
**When to use:** 所有 Webhook endpoint（防止偽造請求）
**Example:**
```php
// Source: buygo-plus-one-dev/includes/api/class-line-webhook-api.php (verified)
private function verify_signature($request) {
    // 注意：使用小寫 header key（WordPress 標準）
    $signature = $request->get_header('x-line-signature');
    $body = $request->get_body();
    $channel_secret = SettingsService::get('line_channel_secret', '');

    if (empty($signature) || empty($channel_secret)) {
        return false;
    }

    // 計算簽名
    $hash = hash_hmac('sha256', $body, $channel_secret, true);
    $computed_sig = base64_encode($hash);

    // 使用 hash_equals 防止時序攻擊
    return hash_equals($signature, $computed_sig);
}
```

**來源：** [Webhook Security Best Practices](https://hookdeck.com/webhooks/guides/webhooks-security-checklist), [WordPress REST API](https://developer.wordpress.org/rest-api/)

### Pattern 3: Webhook Event 去重機制

**What:** 使用 webhookEventId 防止重複處理相同事件
**When to use:** 所有 Webhook 事件處理（LINE 可能重試）
**Example:**
```php
// Source: buygo-plus-one-dev/includes/services/class-line-webhook-handler.php (verified)
public function process_events($events) {
    foreach ($events as $event) {
        $event_id = $event['webhookEventId'] ?? '';

        if ($event_id) {
            $cache_key = 'buygo_line_event_' . $event_id;

            // 檢查是否已處理
            if (get_transient($cache_key)) {
                continue;  // 跳過已處理的事件
            }

            // 標記為已處理（60 秒有效期）
            set_transient($cache_key, true, 60);
        }

        $this->handle_event($event);
    }
}
```

**去重有效期：** 60 秒（參考 LINE 重試策略，官方文件未明確說明但現有實作驗證有效）

**來源：** [LINE Developers - Receive messages (webhook)](https://developers.line.biz/en/docs/messaging-api/receiving-messages/)

### Pattern 4: 背景處理（雙重策略）

**What:** 立即返回 200，在背景處理事件（避免 LINE 重試）
**When to use:** 處理時間可能超過 5 秒的 Webhook
**Example:**
```php
// Source: buygo-plus-one-dev/includes/api/class-line-webhook-api.php (verified)
public function handle_webhook($request) {
    // 驗證簽名
    if (!$this->verify_signature($request)) {
        return new \WP_Error('invalid_signature', 'Invalid signature', ['status' => 401]);
    }

    $data = json_decode($request->get_body(), true);

    // 策略 1: fastcgi_finish_request (PHP-FPM)
    if (function_exists('fastcgi_finish_request')) {
        add_action('shutdown', function() use ($data) {
            fastcgi_finish_request();  // 關閉連線，返回 200
            $this->webhook_handler->process_events($data['events'], false);
        });
    }
    // 策略 2: WordPress Cron (備援)
    else {
        wp_schedule_single_event(time(), 'buygo_process_line_webhook', [$data['events']]);
    }

    // 立即返回 200
    return rest_ensure_response(['success' => true]);
}
```

**注意：** fastcgi_finish_request() 會占用 PHP-FPM worker，不適合長時間處理。若需要處理超過 30 秒的任務，考慮使用 Action Scheduler。

**來源：** [PHP fastcgi_finish_request Manual](https://www.php.net/manual/en/function.fastcgi-finish-request.php), [Background Processing in WordPress](https://deliciousbrains.com/background-processing-wordpress/)

### Pattern 5: WordPress Hooks 整合

**What:** 使用 WordPress action hooks 讓其他外掛註冊事件處理器
**When to use:** 基礎外掛提供 Webhook 接收，業務外掛處理具體事件
**Example:**
```php
// 在 WebhookHandler 中觸發 action
private function handle_event($event) {
    $event_type = $event['type'] ?? '';

    // 觸發通用 hook
    do_action('buygo_line_notify/webhook/event', $event);

    // 觸發類型特定 hook
    switch ($event_type) {
        case 'message':
            do_action('buygo_line_notify/webhook/message', $event);
            break;
        case 'follow':
            do_action('buygo_line_notify/webhook/follow', $event);
            break;
        case 'unfollow':
            do_action('buygo_line_notify/webhook/unfollow', $event);
            break;
    }
}
```

**其他外掛註冊處理器：**
```php
// 在 buygo-plus-one-dev 中
add_action('buygo_line_notify/webhook/message', function($event) {
    // 處理圖片上傳、商品建立等業務邏輯
}, 10, 1);
```

### Pattern 6: Verify Event 處理

**What:** 處理 LINE Developers Console 的「驗證」按鈕請求
**When to use:** 所有 Webhook endpoint（必須返回 200）
**Example:**
```php
// Source: buygo-plus-one-dev/includes/api/class-line-webhook-api.php (verified)
foreach ($data['events'] as $event) {
    $reply_token = $event['replyToken'] ?? '';

    // LINE Verify Event 的 replyToken 固定為 32 個 0
    if ($reply_token === '00000000000000000000000000000000') {
        return rest_ensure_response(['success' => true]);
    }
}
```

**重要：** Verify Event 不應觸發業務邏輯，僅用於驗證 Webhook URL 設定正確。

### Anti-Patterns to Avoid

- **❌ 在 Webhook endpoint 直接處理業務邏輯** - 應該使用背景處理，避免 timeout
- **❌ 使用 X-Line-Signature（大寫）** - WordPress REST API 統一轉為小寫，使用 `x-line-signature`
- **❌ permission_callback 使用簽名驗證** - 應設為 `__return_true`，在 callback 內驗證簽名
- **❌ 不檢查 Verify Event** - 會導致 LINE Developers Console 驗證失敗
- **❌ 使用 echo 或 print** - 使用 WordPress REST API 的 `rest_ensure_response()`

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Webhook 重試機制 | 自訂重試邏輯 | 依賴 LINE 官方重試 | LINE 會自動重試失敗的 Webhook（最多 3 次） |
| 事件佇列系統 | 自訂佇列 | WordPress Transient + Cron | 輕量級，無需額外依賴 |
| 簽名驗證演算法 | 自訂加密函式 | PHP openssl_* 函式 | 經過驗證，防止常見攻擊 |
| HTTP Header 解析 | 直接讀取 $_SERVER | WP_REST_Request->get_header() | 自動處理大小寫和前綴 |

**Key insight:** LINE Messaging API 已內建完整的重試和錯誤處理機制，Webhook endpoint 只需專注於快速驗證和背景處理，不要重複實作已有的功能。

## Common Pitfalls

### Pitfall 1: Header 大小寫問題

**What goes wrong:** 使用 `X-Line-Signature` 或 `HTTP_X_LINE_SIGNATURE` 導致無法讀取簽名
**Why it happens:** WordPress REST API 統一將 header 轉為小寫，但 PHP $_SERVER 保留原始大小寫
**How to avoid:**
```php
// ✅ 正確：使用小寫
$signature = $request->get_header('x-line-signature');

// ❌ 錯誤：大寫或混合
$signature = $request->get_header('X-Line-Signature');
$signature = $_SERVER['HTTP_X_LINE_SIGNATURE'];
```
**Warning signs:** 簽名驗證總是失敗，即使 Channel Secret 正確

**來源：** buygo-plus-one-dev 實際踩坑經驗（CLAUDE.md 有記錄）

### Pitfall 2: 去重 Transient 過期時間過短

**What goes wrong:** 事件去重失效，相同事件被處理多次
**Why it happens:** Transient 過期太快，LINE 重試時快取已失效
**How to avoid:**
- 使用至少 60 秒的過期時間（與 LINE 重試間隔匹配）
- 根據 Webhook provider 重試策略調整：若重試最多 72 小時，快取應保留至少 72 小時
**Warning signs:** 日誌中出現重複的 webhookEventId

**來源：** [Webhook Deduplication Checklist](https://latenode.com/blog/webhook-deduplication-checklist-for-developers)

### Pitfall 3: fastcgi_finish_request 後占用 FPM Worker

**What goes wrong:** PHP-FPM workers 被長時間占用，導致新請求等待
**Why it happens:** fastcgi_finish_request() 關閉連線但 PHP 繼續執行，worker 未釋放
**How to avoid:**
- 背景處理時間控制在 30 秒內
- 超過 30 秒的任務使用 WordPress Cron 或 Action Scheduler
- 監控 FPM worker 使用率（pm.max_children）
**Warning signs:** 網站其他頁面載入變慢，FPM 日誌顯示 "pool seems busy"

**來源：** [PHP Workers and WordPress Performance](https://gridpane.com/blog/php-workers-and-wordpress-performance/), [Background Processing in PHP](https://github.com/Crowdstar/background-processing)

### Pitfall 4: 未處理 Verify Event

**What goes wrong:** LINE Developers Console 驗證失敗，顯示「Webhook URL 無法使用」
**Why it happens:** Verify Event 的 replyToken 是特殊值（32 個 0），會觸發業務邏輯導致錯誤
**How to avoid:**
```php
// 在處理事件前先檢查
if ($reply_token === '00000000000000000000000000000000') {
    // Verify Event - 直接返回 200，不處理
    return rest_ensure_response(['success' => true]);
}
```
**Warning signs:** LINE Console 驗證按鈕顯示紅色錯誤

### Pitfall 5: 忘記 ignore_user_abort

**What goes wrong:** 背景處理時，若客戶端中斷連線，PHP 腳本會提前終止
**Why it happens:** 預設情況下，客戶端斷線會觸發 PHP 腳本中止
**How to avoid:**
```php
public function process_events($events, $return_response = true) {
    ignore_user_abort(true);  // 防止客戶端斷線終止腳本
    set_time_limit(0);        // 允許長時間執行

    // ... 處理事件
}
```
**Warning signs:** 背景處理不完整，日誌顯示處理中斷

## Code Examples

### Example 1: 完整的 Webhook API Class

```php
// Source: 基於 buygo-plus-one-dev/includes/api/class-line-webhook-api.php 優化
namespace BuygoLineNotify\Api;

class LineWebhookApi {
    private $webhook_handler;

    public function __construct() {
        // WebhookHandler 將透過 Hooks 通知其他外掛
        $this->webhook_handler = new \BuygoLineNotify\Services\WebhookHandler();
    }

    public function register_routes() {
        register_rest_route(
            'buygo-line-notify/v1',
            '/webhook',
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'handle_webhook'],
                'permission_callback' => '__return_true',
            ]
        );
    }

    public function handle_webhook($request) {
        // 1. 驗證簽名
        if (!$this->verify_signature($request)) {
            return new \WP_Error('invalid_signature', 'Invalid signature', ['status' => 401]);
        }

        $body = $request->get_body();
        $data = json_decode($body, true);

        // 2. 檢查資料有效性
        if (!isset($data['events']) || !is_array($data['events'])) {
            return rest_ensure_response(['success' => false]);
        }

        // 3. 處理 Verify Event
        foreach ($data['events'] as $event) {
            $reply_token = $event['replyToken'] ?? '';
            if ($reply_token === '00000000000000000000000000000000') {
                return rest_ensure_response(['success' => true]);
            }
        }

        // 4. 背景處理
        if (function_exists('fastcgi_finish_request')) {
            $handler = $this->webhook_handler;
            add_action('shutdown', function() use ($data, $handler) {
                fastcgi_finish_request();
                $handler->process_events($data['events'], false);
            });
        } else {
            wp_schedule_single_event(time(), 'buygo_process_line_webhook', [$data['events']]);
        }

        // 5. 立即返回 200
        return rest_ensure_response(['success' => true]);
    }

    private function verify_signature($request) {
        $signature = $request->get_header('x-line-signature');

        if (empty($signature)) {
            return false;
        }

        $channel_secret = \BuygoLineNotify\Services\SettingsService::get('channel_secret', '');

        if (empty($channel_secret)) {
            // 開發環境可選擇跳過驗證
            if (defined('WP_DEBUG') && WP_DEBUG === true) {
                return true;
            }
            return false;
        }

        $body = $request->get_body();
        $hash = hash_hmac('sha256', $body, $channel_secret, true);
        $computed_sig = base64_encode($hash);

        return hash_equals($signature, $computed_sig);
    }
}
```

### Example 2: Webhook Handler with Hooks

```php
// Source: 新設計，基於現有實作 + Hooks 機制
namespace BuygoLineNotify\Services;

class WebhookHandler {
    private $logger;

    public function __construct() {
        $this->logger = Logger::get_instance();
    }

    public function process_events($events, $return_response = true) {
        ignore_user_abort(true);
        set_time_limit(0);

        foreach ($events as $event) {
            // 去重檢查
            $event_id = $event['webhookEventId'] ?? '';
            if ($event_id) {
                $cache_key = 'buygo_line_event_' . $event_id;
                if (get_transient($cache_key)) {
                    continue;
                }
                set_transient($cache_key, true, 60);
            }

            // 處理事件
            $this->handle_event($event);
        }

        if ($return_response) {
            return rest_ensure_response(['success' => true]);
        }
        return null;
    }

    private function handle_event($event) {
        $event_type = $event['type'] ?? '';

        // 記錄事件
        $this->logger->log('webhook_event_received', [
            'type' => $event_type,
            'webhookEventId' => $event['webhookEventId'] ?? '',
        ]);

        // 觸發通用 hook（讓其他外掛處理）
        do_action('buygo_line_notify/webhook/event', $event);

        // 觸發類型特定 hook
        switch ($event_type) {
            case 'message':
                do_action('buygo_line_notify/webhook/message', $event);
                break;
            case 'follow':
                do_action('buygo_line_notify/webhook/follow', $event);
                break;
            case 'unfollow':
                do_action('buygo_line_notify/webhook/unfollow', $event);
                break;
            case 'postback':
                do_action('buygo_line_notify/webhook/postback', $event);
                break;
        }
    }
}
```

### Example 3: 其他外掛註冊 Hook 處理器

```php
// 在 buygo-plus-one-dev 中註冊處理器
add_action('buygo_line_notify/webhook/message', function($event) {
    $message_type = $event['message']['type'] ?? '';
    $line_uid = $event['source']['userId'] ?? '';

    // 處理圖片訊息
    if ($message_type === 'image') {
        // 使用 buygo-line-notify 的 ImageUploader
        $image_uploader = \BuygoLineNotify\BuygoLineNotify::image_uploader();
        $attachment_id = $image_uploader->download_and_upload($event['message']['id'], $user->ID);

        // 發送回覆
        $messaging = \BuygoLineNotify\BuygoLineNotify::messaging();
        $messaging->send_reply($event['replyToken'], '圖片已收到！');
    }

    // 處理文字訊息
    if ($message_type === 'text') {
        // 商品資料解析和建立
        // ...
    }
}, 10, 1);
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| 使用 WP_Query 查詢事件歷史 | 使用 Transient 快取去重 | 2024+ | 減少資料庫查詢，提升效能 |
| 同步處理 Webhook | 背景處理（fastcgi_finish_request） | 2023+ | 避免 timeout，提升可靠性 |
| 手動解析 $_SERVER headers | 使用 WP_REST_Request API | WordPress 4.7+ | 自動處理大小寫和編碼 |
| 單一外掛處理所有邏輯 | 基礎外掛 + Hooks 擴展 | 2025+ | 模組化，易於維護 |

**Deprecated/outdated:**
- **直接讀取 $_SERVER['HTTP_X_LINE_SIGNATURE']**: 應使用 `$request->get_header('x-line-signature')`
- **在 permission_callback 驗證簽名**: 應設為 `__return_true`，在 callback 內驗證
- **使用 file_get_contents('php://input')**: 應使用 `$request->get_body()`

## Open Questions

1. **Transient 快取持久化**
   - What we know: WordPress Transient 不保證持久化，可能被 object cache 提前清除
   - What's unclear: 在高流量環境下，去重機制是否足夠可靠
   - Recommendation: 監控重複事件率，若超過 1% 考慮使用自訂資料表

2. **fastcgi_finish_request 的 timeout 處理**
   - What we know: fastcgi_finish_request() 後 PHP 繼續執行，但 nginx/Apache 可能有 timeout
   - What's unclear: 不同主機環境的 timeout 設定差異
   - Recommendation: 記錄處理時間，若超過 30 秒改用 WordPress Cron

3. **LINE 重試策略細節**
   - What we know: LINE 會重試失敗的 Webhook，但官方文件未明確說明間隔和次數
   - What's unclear: 重試間隔是固定或指數退避？最多重試幾次？
   - Recommendation: 保守估計，Transient 保留 60 秒（足以覆蓋 3 次重試）

## Sources

### Primary (HIGH confidence)
- [LINE Developers - Receive messages (webhook)](https://developers.line.biz/en/docs/messaging-api/receiving-messages/) - Webhook 事件結構和 webhookEventId
- [LINE Developers - Messaging API Reference](https://developers.line.biz/en/reference/messaging-api/) - API 規格和錯誤碼
- [WordPress REST API Handbook](https://developer.wordpress.org/rest-api/) - REST API 最佳實踐
- buygo-plus-one-dev/includes/api/class-line-webhook-api.php - 實際運行的實作（已驗證）

### Secondary (MEDIUM confidence)
- [Webhook Security Best Practices](https://hookdeck.com/webhooks/guides/webhooks-security-checklist) - 簽名驗證和安全檢查清單
- [Webhook Signature Verification](https://apidog.com/blog/webhook-signature-verification/) - HMAC 簽名驗證詳解
- [Background Processing in WordPress](https://deliciousbrains.com/background-processing-wordpress/) - 背景處理策略比較
- [WordPress Transients Documentation](https://developer.wordpress.org/apis/transients/) - Transient API 使用指南

### Tertiary (LOW confidence)
- [PHP Workers and WordPress Performance](https://gridpane.com/blog/php-workers-and-wordpress-performance/) - PHP-FPM worker 調校（需根據實際環境驗證）
- [Webhook Deduplication Checklist](https://latenode.com/blog/webhook-deduplication-checklist-for-developers) - 去重策略建議（需驗證適用性）

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH - 所有組件為 WordPress 和 LINE 官方標準
- Architecture: HIGH - 基於已驗證運行的 buygo-plus-one-dev 實作
- Pitfalls: HIGH - 來自實際踩坑經驗（CLAUDE.md 有記錄）

**Research date:** 2026-01-28
**Valid until:** 2026-04-28 (90 days - WordPress 和 LINE API 穩定)

## Next Steps for Planning

1. **複用現有架構** - buygo-plus-one-dev 的 Webhook 處理邏輯已驗證有效，規劃時保持一致
2. **重點關注 Hooks** - 確保 Hook 命名和參數設計良好，方便其他外掛整合
3. **測試 Verify Event** - 規劃時包含 LINE Console 驗證測試步驟
4. **背景處理驗證** - 確認測試環境是否支援 fastcgi_finish_request()
5. **日誌記錄** - 規劃完整的日誌記錄（簽名驗證、去重、Hook 觸發），方便除錯
