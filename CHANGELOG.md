# Changelog

## 2026-01-29 (Session 4)

### 修復 (fix)

#### 登入導向問題修復

**問題 1：權限錯誤**
- subscriber 用戶登入後嘗試訪問後台頁面顯示「必須具備更高的權限」錯誤
- 修正：加入權限檢查，無 edit_posts 權限時自動導向首頁

**問題 2：預設跳轉 URL 功能未實作**
- 後台設定頁面有「預設登入後跳轉 URL」欄位但無對應邏輯
- 新增：`SettingsService::get_default_redirect_url()` 方法
- 整合：`Login_Handler::perform_login()` 導向邏輯優化
- 儲存：`SettingsPage` 處理 `default_redirect_url` 欄位

**問題 3：Fatal Error**
- 錯誤：`Class 'BuygoLineNotify\Handlers\Services\SettingsService' not found`
- 修正：新增 `use BuygoLineNotify\Services\SettingsService;`

**問題 4：後台無法顯示設定值**
- `SettingsService::get_all()` 的 `$keys` 陣列缺少 `default_redirect_url`
- 修正：在 `$keys` 陣列中新增該欄位

**導向優先順序（最終版）**：
1. 後台設定的「預設登入後跳轉 URL」（最高優先）
2. OAuth 開始時的頁面（檢查權限）
3. 權限檢查：後台頁面 && 無權限 → 首頁
4. 套用 `login_redirect` filter

**Commits**：
- `62e7260` - fix(settings): add default_redirect_url to get_all() keys
- `72d1b86` - fix(login): add missing SettingsService import
- `092d1bd` - fix(login): implement default redirect URL and admin access check

**變更檔案**：
- `includes/handlers/class-login-handler.php` - 導向邏輯 + namespace import
- `includes/services/class-settings-service.php` - get_default_redirect_url() + get_all()
- `includes/admin/class-settings-page.php` - 儲存 default_redirect_url

---

## 2026-01-29 (Session 3)

### 功能 (feat)

#### Phase 12: Profile Sync 與 Avatar 整合

**Wave 1 - 核心服務建立**：

1. **ProfileSyncService (Plan 12-01)**
   - 實作 LINE profile 同步到 WordPress 用戶的核心邏輯
   - 支援三種觸發場景：register（強制同步）、login（可選）、link（可選）
   - 實作三種衝突處理策略：
     - `line_priority` - LINE profile 優先覆蓋
     - `wordpress_priority` - 保留 WordPress 現有資料（空白欄位除外）
     - `manual` - 記錄衝突但不自動更新
   - 同步日誌記錄（最多保留 10 筆）
   - 衝突日誌記錄（manual 策略使用）

2. **AvatarService (Plan 12-02)**
   - 整合 WordPress `get_avatar_url` filter hook
   - 已綁定 LINE 的用戶顯示 LINE 頭像
   - 7 天頭像快取機制（避免阻塞頁面）
   - 支援多種參數類型（ID、email、WP_User、WP_Comment、WP_Post）

**Wave 2 - 流程整合與後台 UI**：

3. **ProfileSyncService 整合 (Plan 12-03)**
   - `UserService::create_user_from_line()` - 註冊時呼叫 syncProfile('register')
   - `Login_Handler::perform_login()` - 登入時呼叫 syncProfile('login')
   - `Login_Handler::handle_link_submission()` - 綁定時呼叫 syncProfile('link')

4. **Profile Sync 後台設定 UI (Plan 12-04)**
   - sync_on_login checkbox - 控制登入時是否同步
   - conflict_strategy radio buttons - 選擇衝突處理策略
   - 清除頭像快取按鈕 - AJAX 清除所有用戶快取
   - 表單驗證和儲存邏輯

**Wave 3 - 整合驗證**（進行中）：

5. **Plan 12-05** - 人工驗證三種衝突策略運作

**變更檔案**：
- `includes/services/class-profile-sync-service.php` - 新增（304 行）
- `includes/services/class-avatar-service.php` - 新增（150 行）
- `includes/services/class-settings-service.php` - 擴展支援 sync_on_login 和 conflict_strategy
- `includes/services/class-user-service.php` - 整合 ProfileSyncService
- `includes/handlers/class-login-handler.php` - 整合 ProfileSyncService
- `includes/admin/views/settings-page.php` - 新增 Profile Sync 設定區塊
- `includes/admin/class-settings-page.php` - 新增 AJAX handler
- `includes/class-plugin.php` - 載入 ProfileSyncService 和 AvatarService

**相依需求**：
- SYNC-01: 註冊時同步 Profile ✅
- SYNC-02: 登入時可選同步 ✅
- SYNC-03: 綁定時可選同步 ✅
- SYNC-04: 衝突處理策略 ✅（驗證中）
- SYNC-05: 同步日誌記錄 ✅
- AVATAR-01: get_avatar_url filter hook ✅
- AVATAR-02: 頭像快取機制 ✅
- AVATAR-03: 快取清除功能 ✅

**Commits**（Wave 1-2）：
- `ed7d0bc` - feat(12-01): extend SettingsService and create ProfileSyncService
- `39dfcce` - feat(12-01): load ProfileSyncService in Plugin with correct order
- `df19c6c` - docs(12-01): complete ProfileSyncService 核心服務類別 plan
- `3143a4c` - feat(12-02): 建立 AvatarService 類別
- `932eef5` - feat(12-02): 整合 AvatarService 到 Plugin
- `a0d8391` - docs(12-02): complete AvatarService 實作 + get_avatar_url filter hook plan
- `f672490` - feat(12-03): integrate ProfileSyncService into UserService
- `7718a44` - feat(12-03): integrate ProfileSyncService into Login_Handler
- `9f402b7` - docs(12-03): complete ProfileSyncService 整合到 UserService 和 Login_Handler plan
- `7e7b458` - feat(12-04): add Profile Sync settings UI to admin page
- `223bf5b` - feat(12-04): add AJAX handler for clearing avatar cache
- `67b8efe` - docs(12-04): complete Profile Sync 後台設定 UI plan

---

## 2026-01-29 (Session 2)

### 安全性 (security)

#### Phase 15-06: Username 生成安全修正

**⚠️ 重大安全問題**：
- 原本直接使用 `line_` + LINE UID 作為 username
- LINE UID 會在 WordPress 用戶列表、個人檔案、評論等處**公開顯示**
- 暴露 UID 可能導致**隱私洩漏**或被用於**社交工程攻擊**

**解決方案**（參考 Nextend Social Login）：
1. ✅ **優先使用 displayName** - 用戶名稱清理後作為 username（例如「Fish 老魚」→ `fish`）
2. ✅ **使用 email 前綴** - 如果不是假 email（例如「john@example.com」→ `john`）
3. ✅ **Fallback 隨機 hash** - 無可用資訊時使用 `line_user_a3f8d2e1`（隨機 8 字元）
4. ✅ **處理重複** - 自動加上數字後綴 `_1`, `_2`, `_3`...
5. ✅ **絕不暴露 LINE UID** - UID 僅儲存在 user_meta 和 bindings 表，不會公開顯示

**變更檔案**：
- `includes/services/class-user-service.php`
  - 新增 `generate_username()` - 智慧生成 username
  - 新增 `sanitize_username()` - 清理和驗證 username
  - 修改 `create_user_from_line()` - 使用新的生成邏輯

**安全性提升**：
- 🔒 LINE UID 完全不會出現在公開可見的地方
- 👤 Username 更友善、易讀、易記
- ✅ 符合 WordPress 和社交登入最佳實踐

**Commits**：
- `0686265` - security(15-06): 修正 username 生成邏輯，不再暴露 LINE UID

---

### 功能 (feat)

#### Phase 15-05: WordPress 登入導向機制與前台登入按鈕整合

**功能新增**：
1. **WordPress 登入導向支援** - 支援 `login_redirect` filter，與第三方登入導向外掛完全相容
2. **前台登入按鈕整合** - 自動在各種登入頁面顯示 LINE 登入按鈕
   - Fluent Community 登入頁面
   - Ajax Login and Registration Modal Popup Pro
   - WordPress 原生登入頁面 (wp-login.php)

**實作細節**：
- LoginButtonService 使用 hooks 整合，不需要手動配置
- 按鈕樣式自動適應不同的登入頁面環境
- 使用 JavaScript fetch API 取得 authorize_url 並導向
- 支援自訂按鈕文字（透過 `buygo_line_notify/login_button/text` filter）
- 支援自訂按鈕樣式（透過 `buygo_line_notify/login_button/classes` filter）

**變更檔案**：
- `includes/api/class-login-api.php` - 三處登入成功後加入 `login_redirect` filter
- `includes/services/class-login-button-service.php` - 新增登入按鈕服務
- `includes/class-plugin.php` - 註冊登入按鈕 hooks

**相容性**：
- ✅ 與 Peter's Login Redirect / LoginWP 相容
- ✅ 與 WP Force Login 相容
- ✅ 與 Theme My Login 相容
- ✅ 與任何使用 `login_redirect` filter 的外掛相容

**Commits**：
- `0e0566c` - feat(15-05): 支援 WordPress 登入導向機制與前台登入按鈕整合

---

### 修正 (fix)

#### Phase 15-04: LINE Login 設定頁面按鈕修正

**問題**：
- 設定頁面的「使用 LINE 登入測試」按鈕直接連結到 REST API endpoint
- API 返回 JSON 而不是重導向，導致顯示亂碼頁面
- 無法正確跳轉到 LINE 授權頁面

**解決方案**：
- 將 `<a>` 連結改為 `<button>` 按鈕
- 加入 JavaScript fetch 呼叫 API 取得 authorize_url
- 取得 URL 後自動導向到 LINE 授權頁面

**變更檔案**：
- `includes/admin/views/settings-page.php` - 改用 JavaScript 處理按鈕點擊

**測試結果**：✅ 成功
- 完整 OAuth 2.0 流程驗證通過
- State 儲存與驗證正常（Transient API）
- Token 交換成功
- LINE Profile 取得成功（U823e48d899eb99be6fb49d53609048d9 "Fish 老魚"）
- 新用戶建立成功（user_id 21，subscriber 角色）
- 用戶登入成功並設定 auth cookie

**Commits**：
- `b4d976d` - fix(15-04): 修正設定頁面按鈕實作，改用 JavaScript fetch 取得 authorize_url

---

## 2026-01-29 (Session 1)

### 修正 (fix)

#### Phase 15-04: LINE Login 系統架構重構

**問題**：
- Logger 靜態方法呼叫錯誤：`Non-static method Logger::log() cannot be called statically`
- StateManager 使用 PHP Session 在 REST API 環境下不可靠
- session_start() 在 init hook 可能不執行，導致 OAuth callback 失敗

**解決方案**：
1. **重構 StateManager**：移除三層 fallback（Session → Transient → Option），改用純 Transient API
   - 參考 Nextend Social Login 外掛架構
   - 完全支援 REST API 環境
   - 符合 WordPress 最佳實踐

2. **修復 Logger 呼叫**：修正 Login_API 中 8 處 Logger 靜態呼叫
   - `Logger::log('ERROR', 'message', [])` → `Logger::get_instance()->log('error', ['message' => '...'])`

3. **移除 Session 依賴**：從 Plugin.php 移除 session_start() 呼叫

**變更檔案**：
- `includes/services/class-state-manager.php` - 重構為純 Transient API
- `includes/class-plugin.php` - 移除 session_start()
- `includes/api/class-login-api.php` - 修復 8 處 Logger 呼叫
- `includes/services/class-login-service.php` - 修復 8 處 Logger 呼叫（前次提交）

**Commits**：
- `a9e9b5b` - fix(15-04): 重構 StateManager 移除 Session 依賴，改用純 Transient API
- `f4322e1` - fix(15-04): correct authorize URL generation using add_query_arg
- `991d2dc` - fix(15-03): correct Logger method calls to use get_instance() and proper parameter format

---

## 2026-01-28

### 功能 (feat)

#### Phase 15-03: Login_API + Plugin 整合
- 建立 REST API endpoints（/login/authorize, /login/callback, /login/bind）
- 整合 LoginService 和 UserService
- 實作完整 OAuth 2.0 callback 流程

#### Phase 15-02: UserService + LineUserService 擴展
- 建立 UserService（用戶建立和綁定邏輯）
- 擴展 LineUserService 查詢方法
- 實作混合儲存策略（user_meta + bindings 表）

#### Phase 15-01: StateManager + LoginService
- 建立 StateManager（OAuth state 管理）
- 建立 LoginService（OAuth 核心流程）
- 實作 LINE Login v2.1 API 整合
