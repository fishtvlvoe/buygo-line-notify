# Requirements: v0.2 Milestone - LINE Login 完整重構

**Defined:** 2026-01-29
**Core Value:** 採用 Nextend Social Login 標準架構，建立符合 WordPress 生態系統標準的 LINE Login 系統

**基於：** `.planning/NEXTEND-SOCIAL-LOGIN-ANALYSIS.md` 完整逆向工程分析

---

## v0.2 Requirements

### 核心資料架構 (ARCH)

- [ ] **ARCH-01**: 建立 wp_buygo_line_users 專用資料表
  - 欄位：ID (bigint), type (varchar, 固定為 'line'), identifier (LINE UID, varchar), user_id (bigint, FK to wp_users), register_date (datetime), link_date (datetime)
  - 索引：UNIQUE KEY (identifier), KEY (user_id), KEY (type)
  - 對應 Nextend 的 wp_social_users 表結構

- [ ] **ARCH-02**: 遷移資料庫 Migration 機制
  - 檢查舊的 wp_buygo_line_bindings 表是否存在
  - 若存在：遷移資料到 wp_buygo_line_users（register_date = created_at, link_date = updated_at）
  - 遷移後保留舊表（不刪除，避免資料遺失）
  - 記錄遷移狀態到 wp_options（buygo_line_migration_status）

- [ ] **ARCH-03**: 查詢 API 實作
  - `getUserByLineUid($lineUid)` - 根據 LINE UID 查詢 WordPress 用戶
  - `getLineUidByUserId($userId)` - 根據 WordPress User ID 查詢 LINE UID
  - `isUserLinked($userId)` - 檢查用戶是否已綁定 LINE
  - `linkUser($userId, $lineUid)` - 建立用戶與 LINE 的綁定關係
  - `unlinkUser($userId)` - 解除綁定（軟刪除，保留歷史）

### 標準 WordPress URL 機制 (URL)

- [ ] **URL-01**: 實作登入入口 handler
  - Hook: `login_init` (檢查 `$_GET['loginSocial']` === 'buygo-line')
  - 動作：建立 State → 儲存到持久化系統 → 導向 LINE OAuth URL
  - OAuth URL 參數：response_type=code, client_id, redirect_uri, state, scope (profile openid email), bot_prompt=aggressive

- [ ] **URL-02**: 實作 OAuth callback handler
  - 相同入口：`login_init` (檢查 `$_GET['loginSocial']` === 'buygo-line' AND `$_GET['code']` 存在)
  - 驗證 State（從持久化系統取出比對）
  - 用 code 換取 access_token (LINE Token API)
  - 用 access_token 取得 profile (LINE Profile API)
  - 拋出 NSLContinuePageRenderException（讓頁面繼續渲染）

- [ ] **URL-03**: 取代現有 REST API 架構
  - 移除 `includes/api/class-line-login-api.php` 的 REST endpoint
  - 保留 LoginService 的核心邏輯（OAuth token exchange、profile fetch）
  - 重構 LoginService 方法名稱（與 Nextend 對齊）

- [ ] **URL-04**: 整合到 WordPress login_url
  - Filter: `login_url` - 若檢測到 LINE 登入按鈕，附加 `?loginSocial=buygo-line`
  - Filter: `logout_url` - 登出後清除 LINE 相關 Session

### NSLContinuePageRenderException 模式 (NSL)

- [ ] **NSL-01**: 實作例外類別
  - 檔案：`includes/exceptions/class-nsl-continue-page-render-exception.php`
  - 繼承自 PHP Exception
  - 用途：標記「這不是錯誤，讓頁面繼續渲染」的特殊狀態

- [ ] **NSL-02**: OAuth callback 拋出例外
  - 在 URL-02 完成 profile 取得後，拋出 NSLContinuePageRenderException
  - 例外訊息：'REGISTER_FLOW' 或 'LOGIN_FLOW' 或 'LINK_FLOW'（根據情境）
  - 將 LINE profile 資料儲存到持久化系統（供 shortcode 使用）

- [ ] **NSL-03**: 動態註冊 shortcode
  - 在拋出例外前：`add_shortcode('buygo_line_register_flow', [handler])`
  - Shortcode handler 從持久化系統取得 LINE profile
  - 渲染註冊表單或登入訊息或綁定確認

- [ ] **NSL-04**: 例外捕捉與處理
  - Hook: `login_init` - 用 try-catch 包裹 OAuth 流程
  - Catch NSLContinuePageRenderException: 不做任何事（讓 WordPress 繼續）
  - Catch 其他 Exception: 顯示錯誤訊息、記錄日誌

### Register Flow Page 系統 (RFP)

- [ ] **RFP-01**: 後台設定頁面選擇器
  - 設定選項：`buygo_line_register_flow_page`
  - UI：WordPress 頁面下拉選單（wp_dropdown_pages）
  - 預設值：建議建立「LINE 註冊」頁面，內容為 `[buygo_line_register_flow]`
  - 驗證：檢查該頁面是否包含 shortcode（警告但不強制）

- [ ] **RFP-02**: 實作 shortcode
  - Shortcode 名稱：`[buygo_line_register_flow]`
  - 動態註冊：只在 OAuth callback 時註冊（NSL-03）
  - 功能：顯示「正在處理 LINE 登入...」（若未完成 OAuth）或渲染表單（若已完成）

- [ ] **RFP-03**: 渲染註冊表單
  - 從持久化系統讀取 LINE profile (displayName, pictureUrl, email)
  - 顯示 LINE 頭像和名稱（唯讀）
  - 表單欄位：WordPress 用戶名（預填 LINE displayName，可修改）、Email（預填 LINE email，可修改）
  - 隱藏欄位：State（驗證用）、LINE UID
  - 提交按鈕：「完成註冊」

- [ ] **RFP-04**: 表單提交處理
  - Endpoint: POST to `wp-login.php?loginSocial=buygo-line&action=register`
  - 驗證 State（防 CSRF）
  - 驗證用戶名和 Email 格式
  - 檢查 Email 是否已存在（若存在執行 auto-link，見 FLOW-02）
  - 建立 WordPress 用戶 (wp_insert_user)
  - 寫入 wp_buygo_line_users (register_date = now)
  - 執行 Profile Sync (SYNC-01)
  - 自動登入 (wp_set_auth_cookie)

- [ ] **RFP-05**: 註冊成功後導向
  - 從持久化系統讀取 returnUrl（原始頁面）
  - 若無 returnUrl：導向 WordPress 預設首頁或我的帳號頁面
  - 清除持久化系統中的 LINE 資料（State、profile）
  - 顯示歡迎訊息（WordPress admin notice）

### 完整註冊/登入/綁定流程 (FLOW)

- [ ] **FLOW-01**: 新用戶註冊流程
  1. 用戶點擊「LINE 登入」按鈕
  2. URL-01：建立 State → 導向 LINE OAuth
  3. 用戶在 LINE 授權
  4. URL-02：code → token → profile
  5. 檢查 identifier 是否已存在於 wp_buygo_line_users
  6. 若不存在：拋出 NSLContinuePageRenderException → 導向 Register Flow Page
  7. RFP-03/04：用戶填寫表單 → 建立 WP 用戶 → 寫入 wp_buygo_line_users → 登入

- [ ] **FLOW-02**: Auto-link 機制（Email 已存在）
  - 情境：LINE profile 的 email 已存在於 WordPress
  - 檢查時機：RFP-04 表單提交時
  - 動作：不建立新用戶，直接關聯現有用戶
  - 寫入 wp_buygo_line_users (link_date = now, register_date = NULL)
  - 自動登入該現有用戶
  - 顯示訊息：「已將 LINE 帳號綁定到您的現有帳號」

- [ ] **FLOW-03**: 已登入用戶綁定流程
  1. 用戶已登入 WordPress
  2. 在「我的帳號」頁面點擊「綁定 LINE」按鈕
  3. URL-01：建立 State（含 logged_in_user_id）→ 導向 LINE OAuth
  4. URL-02：取得 LINE profile
  5. 檢查 identifier 是否已綁定其他帳號
  6. 若未綁定：寫入 wp_buygo_line_users (link_date = now) → 執行 SYNC-03
  7. 若已綁定：顯示錯誤「此 LINE 帳號已綁定其他用戶」

- [ ] **FLOW-04**: 已有用戶登入流程
  - 情境：identifier 已存在於 wp_buygo_line_users
  - URL-02 檢測到後：直接讀取對應的 user_id
  - 自動登入 (wp_set_auth_cookie)
  - 執行 Profile Sync (SYNC-02，若 admin 設定啟用)
  - 導回原始頁面

- [ ] **FLOW-05**: State 驗證機制
  - State 生成：32 字元隨機字串 (wp_generate_password(32, false))
  - 儲存位置：持久化系統（Session → Transient → Option）
  - 有效期：10 分鐘
  - 驗證方式：hash_equals（防時序攻擊）
  - 驗證失敗：拒絕請求，記錄日誌

### Profile Sync 系統 (SYNC)

- [ ] **SYNC-01**: 註冊時同步 Profile
  - 時機：FLOW-01 建立 WP 用戶時
  - 同步欄位：
    - display_name ← LINE displayName
    - user_email ← LINE email (若 LINE 有提供)
    - 頭像 ← LINE pictureUrl (寫入 user_meta: buygo_line_avatar_url)
  - 記錄同步日誌到 wp_options (json 格式)

- [ ] **SYNC-02**: 登入時更新 Profile
  - 時機：FLOW-04 登入時
  - 設定選項：`buygo_line_sync_on_login` (admin 可啟用/停用)
  - 預設：停用（避免覆蓋用戶手動修改的資料）
  - 若啟用：更新 display_name、user_email、頭像（根據 SYNC-04 的衝突策略）

- [ ] **SYNC-03**: 綁定時同步 Profile
  - 時機：FLOW-03 綁定時
  - 動作：與 SYNC-01 相同
  - 差異：若 WP 用戶已有 display_name/email，依照 SYNC-04 策略決定是否覆蓋

- [ ] **SYNC-04**: 衝突處理策略
  - 設定選項：`buygo_line_conflict_strategy`
  - 選項 1：「LINE 優先」（預設）- LINE profile 覆蓋 WordPress 資料
  - 選項 2：「WordPress 優先」- 保留 WordPress 現有資料，只寫入空白欄位
  - 選項 3：「手動處理」- 不自動同步，記錄差異讓 admin 決定
  - UI：後台設定頁面單選鈕

- [ ] **SYNC-05**: 同步日誌記錄
  - 記錄到 wp_options：`buygo_line_sync_log_{user_id}`
  - 格式：JSON array，每次同步新增一筆
  - 內容：timestamp, action (register/login/link), changed_fields, old_values, new_values
  - 用途：除錯、審計、衝突追蹤
  - 上限：每個用戶保留最近 10 筆（自動清理）

### Avatar 整合 (AVATAR)

- [ ] **AVATAR-01**: 實作 get_avatar_url filter
  - Hook: `get_avatar_url` (WordPress core filter)
  - 優先級：10
  - 參數：$url (原始頭像 URL), $id_or_email (用戶 ID 或 email), $args
  - 邏輯：檢查用戶是否綁定 LINE → 若是則返回 LINE pictureUrl

- [ ] **AVATAR-02**: 查詢綁定狀態
  - 從 $id_or_email 解析出 user_id
  - 呼叫 ARCH-03 的 `isUserLinked($userId)`
  - 若已綁定：讀取 user_meta `buygo_line_avatar_url`
  - 若無快取：查詢 wp_buygo_line_users 取得 LINE UID → 呼叫 LINE API 取得最新 pictureUrl

- [ ] **AVATAR-03**: 返回 LINE pictureUrl
  - 優先使用快取的 `buygo_line_avatar_url`
  - 若快取過期（7 天）：重新呼叫 LINE Profile API 更新
  - 若 LINE API 失敗：fallback 到 WordPress 預設頭像
  - 記錄錯誤日誌（API 失敗時）

- [ ] **AVATAR-04**: Avatar URL 快取機制
  - 快取位置：user_meta `buygo_line_avatar_url`
  - 快取時間：7 天 (user_meta `buygo_line_avatar_updated`)
  - 更新時機：SYNC-01/02/03 Profile Sync 時
  - 清除時機：用戶解除綁定時、手動清除快取時

- [ ] **AVATAR-05**: 快取失效處理
  - 檢查 `buygo_line_avatar_updated` 是否超過 7 天
  - 若超過：非同步更新（避免阻塞頁面渲染）
  - 使用 WordPress Transient API 防止重複更新（5 分鐘內不重複請求）
  - 後台提供「清除頭像快取」按鈕（批次清除所有用戶）

### 持久化儲存系統整合 (STORAGE)

- [ ] **STORAGE-04**: 整合現有 StateManager 到新流程
  - 現有實作：Phase 15 的 `includes/services/class-state-manager.php`
  - 已有三層 fallback：Session → Transient → Option
  - 已有 State 驗證：32 字元隨機 + hash_equals + 10 分鐘有效期
  - 整合點：URL-01 儲存 State、URL-02 取出並驗證
  - 新增欄位：`returnUrl` (原始頁面)、`logged_in_user_id` (綁定流程用)、`line_profile` (OAuth 完成後儲存)

### 前台整合 (FRONTEND)

- [ ] **FRONTEND-01**: 登入/註冊頁面按鈕
  - 位置：`wp-login.php` (WordPress 預設登入頁面)
  - Hook: `login_form` (在表單底部新增按鈕)
  - 按鈕文字：「使用 LINE 登入」
  - 按鈕 URL：`wp-login.php?loginSocial=buygo-line&returnUrl={current_url}`
  - 樣式：LINE 官方綠色 (#00B900)、LINE logo

- [ ] **FRONTEND-02**: 我的帳號頁面綁定按鈕
  - 位置：WooCommerce / WordPress 我的帳號頁面
  - Hook: `woocommerce_account_dashboard` 或 `show_user_profile`
  - 條件：僅在用戶「未綁定 LINE」時顯示（呼叫 ARCH-03 `isUserLinked`）
  - 按鈕文字：「綁定 LINE 帳號」
  - 已綁定時顯示：LINE 頭像 + 名稱 + 「解除綁定」按鈕

- [ ] **FRONTEND-03**: Shortcode `[buygo_line_login]`
  - 用途：可放在任何頁面/文章/小工具
  - 功能：顯示 LINE 登入按鈕（若未登入）或綁定按鈕（若已登入但未綁定）
  - 屬性：
    - `button_text` - 自訂按鈕文字（預設「LINE 登入」）
    - `button_color` - 自訂按鈕顏色（預設 #00B900）
    - `button_size` - 按鈕大小 (small/medium/large)
    - `redirect_to` - 登入後導向的 URL（預設當前頁面）

- [ ] **FRONTEND-04**: 按鈕樣式系統
  - 按鈕 class：`buygo-line-login-button`
  - 響應式設計：手機版全寬、桌面版固定寬度
  - LINE 官方 logo：SVG 內嵌或 CSS background-image
  - Hover 效果：顏色變深、cursor pointer
  - Loading 狀態：顯示 spinner（OAuth 進行中）
  - 錯誤狀態：紅色邊框 + 錯誤訊息

- [ ] **FRONTEND-05**: 登入/綁定成功導向
  - 讀取 StateManager 中的 `returnUrl`
  - 若無 returnUrl：
    - 新註冊用戶：導向「歡迎頁面」或首頁
    - 已有用戶登入：導向「我的帳號」頁面
    - 綁定成功：停留在當前頁面 + 顯示成功訊息
  - 清除 StateManager 中的所有 LINE 相關資料
  - 使用 WordPress admin_notices 顯示訊息

### 後台管理 (BACKEND)

- [ ] **BACKEND-01**: LINE Login 設定區塊
  - 位置：現有設定頁面（Phase 1 已建立）
  - 新增欄位：
    - LINE Login Channel ID (text input)
    - LINE Login Channel Secret (password input, 加密儲存)
    - Redirect URI (唯讀, 顯示 `site_url/wp-login.php?loginSocial=buygo-line`)
    - 「複製」按鈕（複製 Redirect URI 到剪貼簿）
  - 驗證：檢查 Channel ID/Secret 格式

- [ ] **BACKEND-02**: Register Flow Page 設定
  - 欄位：頁面選擇器 (wp_dropdown_pages)
  - Label：「LINE 註冊流程頁面」
  - Description：「選擇一個包含 [buygo_line_register_flow] shortcode 的頁面」
  - 驗證：檢查該頁面內容是否包含 shortcode（警告提示）
  - 快速建立按鈕：「自動建立註冊頁面」（AJAX 建立頁面 + 插入 shortcode）

- [ ] **BACKEND-03**: Profile Sync 設定
  - 區塊標題：「Profile 同步設定」
  - 選項 1：`buygo_line_sync_on_login` (checkbox)
    - Label：「登入時更新 Profile」
    - Description：「從 LINE 同步最新的名稱、Email、頭像（可能覆蓋用戶手動修改的資料）」
  - 選項 2：`buygo_line_conflict_strategy` (radio)
    - Label：「衝突處理策略」
    - 選項：LINE 優先 / WordPress 優先 / 手動處理
  - 選項 3：清除頭像快取按鈕（批次清除所有用戶）

- [ ] **BACKEND-04**: 用戶列表顯示綁定狀態
  - 位置：WordPress 後台「用戶」列表
  - Hook: `manage_users_columns` + `manage_users_custom_column`
  - 新增欄位：「LINE 綁定」
  - 顯示內容：
    - 已綁定：✓ LINE 頭像縮圖 + LINE 名稱
    - 未綁定：— (空白)
  - 可排序：按綁定狀態排序
  - 可篩選：「已綁定 LINE」/「未綁定 LINE」

- [ ] **BACKEND-05**: 除錯工具
  - 子頁面：「除錯工具」(debug-tools)
  - 功能 1：State 驗證日誌
    - 顯示最近 50 筆 State 驗證記錄
    - 欄位：timestamp, state_value, result (success/fail), ip_address, user_agent
    - 用途：偵測 CSRF 攻擊、State 過期問題
  - 功能 2：OAuth 流程追蹤
    - 顯示最近 50 筆 OAuth 流程
    - 欄位：timestamp, step (authorize/callback/register/login/link), user_id, line_uid, result, error_message
    - 用途：偵測 OAuth 失敗原因
  - 功能 3：清除快取按鈕
    - 清除所有 StateManager Transients
    - 清除所有 Avatar 快取
    - 清除 OAuth 流程日誌（保留最近 100 筆）

### 測試與文件 (TEST & DOC)

- [ ] **TEST-01**: 註冊流程單元測試
  - 測試案例 1：新用戶註冊（LINE email 不存在於 WP）
  - 測試案例 2：Auto-link（LINE email 已存在）
  - 測試案例 3：註冊時 Profile Sync
  - 測試案例 4：State 驗證失敗處理
  - 測試案例 5：LINE API 失敗 fallback

- [ ] **TEST-02**: 綁定流程單元測試
  - 測試案例 1：已登入用戶綁定成功
  - 測試案例 2：LINE UID 已綁定其他帳號（失敗）
  - 測試案例 3：綁定時 Profile Sync
  - 測試案例 4：解除綁定（軟刪除）

- [ ] **TEST-03**: Profile Sync 測試
  - 測試案例 1：LINE 優先策略（覆蓋 WP 資料）
  - 測試案例 2：WordPress 優先策略（保留 WP 資料）
  - 測試案例 3：衝突處理（記錄差異）
  - 測試案例 4：同步日誌記錄正確性

- [ ] **TEST-04**: Avatar 整合測試
  - 測試案例 1：get_avatar_url hook 返回 LINE 頭像
  - 測試案例 2：快取機制（7 天內不重複請求）
  - 測試案例 3：快取過期自動更新
  - 測試案例 4：LINE API 失敗 fallback

- [ ] **DOC-01**: 使用文件
  - 章節 1：安裝與設定
    - 在 LINE Developers 建立 Login Channel
    - 設定 Redirect URI
    - 在 WordPress 後台填寫 Channel ID/Secret
    - 建立 Register Flow Page
  - 章節 2：Shortcode 使用
    - `[buygo_line_login]` 參數說明
    - 常見使用場景（登入頁、結帳頁、側邊欄）
  - 章節 3：FAQ
    - 如何處理 LINE 瀏覽器問題？
    - 為何需要 Register Flow Page？
    - Profile Sync 衝突如何處理？

- [ ] **DOC-02**: 架構文件
  - 章節 1：為何採用 Nextend 架構
    - NSLContinuePageRenderException 原理
    - 與 REST API 架構的差異
    - 優勢分析
  - 章節 2：資料表結構
    - wp_buygo_line_users 欄位說明
    - 與 wp_social_users 的對應關係
    - 遷移機制說明
  - 章節 3：OAuth 流程圖
    - 註冊流程
    - 登入流程
    - 綁定流程
    - Auto-link 流程

- [ ] **DOC-03**: API 文件
  - 查詢 API（ARCH-03）
    - `getUserByLineUid($lineUid)` - 參數、返回值、範例
    - `getLineUidByUserId($userId)` - 參數、返回值、範例
    - `isUserLinked($userId)` - 參數、返回值、範例
    - `linkUser($userId, $lineUid)` - 參數、返回值、範例
    - `unlinkUser($userId)` - 參數、返回值、範例
  - Hooks 列表
    - `buygo_line_before_register` - 註冊前 hook
    - `buygo_line_after_register` - 註冊後 hook
    - `buygo_line_before_link` - 綁定前 hook
    - `buygo_line_after_link` - 綁定後 hook
    - `buygo_line_profile_sync` - Profile Sync 時 hook
  - 範例程式碼
    - 如何在其他外掛檢查用戶是否綁定 LINE
    - 如何在註冊後發送歡迎訊息
    - 如何自訂 Profile Sync 行為

---

## Out of Scope (v0.2)

### LIFF 整合（延後到 v0.3）

- **LIFF-01**: 實作 LIFF 頁面（使用 LIFF SDK）
- **LIFF-02**: 偵測 LINE 瀏覽器並自動導向 LIFF 頁面
- **LIFF-03**: LIFF 自動登入（無需 OAuth redirect）
- **LIFF-04**: 設定 WordPress Auth Cookie 在 LINE 環境中正常運作
- **LIFF-05**: 登入後導回原始頁面

**理由**: Nextend 架構（NSLContinuePageRenderException + 三層儲存）已完美處理 LINE 瀏覽器問題，先驗證標準流程是否足夠。

### 通用通知系統（延後到 v0.3）

- **NOTIFY-01**: 提供 API 讓其他外掛發送 LINE 通知
- **NOTIFY-02**: 自動偵測用戶是否綁定 LINE
- **NOTIFY-03**: WooCommerce 整合（訂單狀態通知）
- **NOTIFY-04**: FluentCart 整合（訂單狀態通知）
- **NOTIFY-05**: 提供 Hooks 讓其他外掛註冊通知觸發器

**理由**: 需要先完成 LINE Login 和用戶綁定，確保有足夠的綁定用戶基數。

---

## Traceability

需求對應到 Phase 的映射將在 ROADMAP.md 建立後填入。

---

*Requirements defined: 2026-01-29*
*Based on: NEXTEND-SOCIAL-LOGIN-ANALYSIS.md*
*Total requirements: 50 (10 categories)*
