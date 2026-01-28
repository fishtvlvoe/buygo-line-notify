# Changelog

## 2026-01-29 (Session 2)

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
