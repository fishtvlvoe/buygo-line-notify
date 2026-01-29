# Phase 10 執行記錄

## 使用者指示（2026-01-29）

使用者要求：
> 「請在我不在的時候完成所有程式碼，
>  如果遇到不確定的地方，用最合理的假設繼續，
>  不要用 AskUserQuestion，
>  把所有疑問記錄在 NOTES.md，
>  等我回來再一起解決」

## 執行決策記錄

### Phase 10: Register Flow Page 系統

**執行時間:** 2026-01-29

**Planning 完成狀態:**
- Research: ✅ Completed (10-RESEARCH.md)
- Plans: ✅ 3 plans created and verified
  - 10-01: RegisterFlowShortcode + Transient 儲存 + 動態 shortcode 註冊
  - 10-02: 表單提交處理 + Auto-link 機制
  - 10-03: 後台設定頁面 + Checkpoint 驗證
- Verification: ✅ Passed (3 iterations, all issues resolved)

**現在開始執行 Phase 10...**

---

## 疑問與假設記錄

### Phase 10 Wave 2 執行完成（2026-01-29）

**執行狀態：**
- Plan 10-01: ✅ Complete（Wave 1）
- Plan 10-02: ✅ Complete（Wave 2）
- Plan 10-03: ⏸️ Checkpoint（Wave 2，Task 1 完成，Task 2 等待用戶驗證）

**程式碼完成項目：**

1. **Plan 10-02 - 表單提交處理 + Auto-link 機制**
   - ✅ handle_register_submission() 方法（276+ lines）
   - ✅ handle_auto_link() 方法
   - ✅ 智能 Transient 清除策略（7 個清除點）
   - ✅ 修正 LineUserService.linkUser() format array bug
   - Commits: 14bf450, 31daafb, e3daedd

2. **Plan 10-03 Task 1 - 後台設定頁面**
   - ✅ Register Flow Page 選擇器（wp_dropdown_pages）
   - ✅ Shortcode 檢查邏輯
   - ✅ AJAX 快速建立頁面按鈕
   - ✅ ajax_create_register_page() handler
   - ✅ Callback URL 更新為標準 WordPress URL
   - Commit: 08e1668

**Checkpoint 等待用戶驗證：**

Plan 10-03 Task 2 是 `type: checkpoint:human-verify` 的 blocking gate，需要用戶進行以下測試：

1. 測試案例 1：後台設定頁面（快速建立頁面功能）
2. 測試案例 2：新用戶註冊流程（使用 Register Flow Page）
3. 測試案例 3：新用戶註冊流程（fallback 到 wp-login.php）
4. 測試案例 4：Auto-link（Email 已存在）

**測試前提：**
- LINE Developers Console Callback URL 需更新為：`https://test.buygo.me/wp-login.php?loginSocial=buygo-line`

**假設與決策：**

1. **假設：Transient 10 分鐘 TTL 足夠** - 用戶完成 LINE 授權後有 10 分鐘填寫表單，這對大部分用戶應該足夠
2. **假設：用戶名衝突時自動加數字後綴** - 比顯示錯誤訊息更友善
3. **假設：Auto-link 優先於建立新帳號** - 當 Email 已存在時，綁定現有帳號比建立重複帳號更合理
4. **假設：Callback URL 不需要在程式碼中驗證** - WordPress 本身會處理 URL 路由，我們只需要確保設定頁面顯示正確的 URL 供管理員複製

**無疑問項目（使用合理假設完成）：**

無。所有實作都遵循 Nextend 架構和 Phase 10 RESEARCH.md 的指引。

---

## 用戶測試回報與問題修正（2026-01-29）

### 問題 2: Shortcode 未正確渲染

**現象：**
- 用戶成功解除 LINE 綁定並重新測試
- LINE 授權完成後正確導向「LINE 註冊」頁面（URL: `https://test.buygo.me/line-註冊/?state=...`）
- 但頁面只顯示 `[buygo_line_register_flow]` 文字，沒有渲染成表單

**原因：**
Shortcode 只在 `Login_Handler::register_shortcode_dynamically()` 中註冊（動態註冊），當重定向到 Register Flow Page 時是新的頁面請求，shortcode 註冊已經消失。

**解決方法：**
在 `Plugin::onInit()` 中靜態註冊 shortcode，確保每個頁面請求都可以渲染 shortcode。

**修正內容：**
- `includes/class-plugin.php` 新增 `register_shortcodes()` 方法
- 在 `onInit()` 中呼叫 `register_shortcodes()`
- Shortcode 從 URL `state` 參數讀取 Transient（`RegisterFlowShortcode::render()` 已正確實作）
- Commit: 337b57c

**測試狀態：**
- [ ] 案例 1：後台設定頁面 - **待測試**
- [x] 案例 2：新用戶註冊流程（Register Flow Page） - **✅ 通過**（用戶已完成註冊）
- [ ] 案例 3：新用戶註冊流程（Fallback 模式） - **待測試**
- [ ] 案例 4：Auto-link（Email 已存在） - **待測試**

---

## 用戶問題與決策（2026-01-29 晚間）

### 問題 3: 註冊成功但 UI 風格不一致

**現象：**
- 用戶成功完成註冊流程
- 但表單 UI 設計與 BuyGo Plus One 風格不一致

**決策：**
- 用戶表示此項目**非緊急**，可以之後再做
- 需記得：未來要統一 UI 風格（Phase 13 前台整合時處理）

### 問題 4: 登入後跳轉頁面設定

**用戶疑問：**
1. 登入後應該跳轉到指定頁面，如何設定？
2. 是在後台設定嗎？
3. 是否由其他外掛控制？
4. 是否會採用其他系統的跳轉規則？
5. 目前都直接導向首頁

**技術說明：**

**redirect_url 來源機制：**
1. 從 LINE 登入按鈕點擊時取得（`wp_get_referer()`）
2. 儲存在 state 中（`StateManager::store_state()`）
3. 完成登入後取回使用

**WordPress 標準機制：**
```php
// 程式碼已實作 (Login_Handler:345, 628, 730)
$redirect_to = $state_data['redirect_url'] ?? home_url();
$redirect_to = apply_filters('login_redirect', $redirect_to, '', $user);
```

**login_redirect filter 會自動：**
- ✅ 尊重其他外掛的跳轉規則（WooCommerce, BuddyPress 等）
- ✅ 尊重 WordPress 核心的跳轉邏輯
- ✅ 允許主題自訂跳轉行為

**決策：**
- Phase 10 範圍：Register Flow Page 系統（新用戶註冊）
- Phase 11 範圍：完整註冊/登入/綁定流程（包含現有用戶登入）
- Phase 13 範圍：前台整合（包含登入按鈕、跳轉邏輯、後台跳轉設定）
- **Phase 10 維持現狀**，不新增後台跳轉設定
- **Phase 13 時再實作**後台設定選項和完整跳轉優先級邏輯

**測試時的解法：**
- 方法 1：在前台頁面測試（推薦）- 會自動回到該頁面
- 方法 2：手動指定 redirect_url 參數（測試用）

---

## Phase 10-03 Task 2 Checkpoint 驗證

**目前進度：**
- ✅ Task 1 完成：後台設定頁面整合
- ✅ Task 2 完成：用戶驗證測試全部通過

**最終測試結果：**
1. ✅ 後台設定頁面（快速建立頁面功能）- **通過**（AJAX 功能正常，黃色警告不影響使用）
2. ✅ 新用戶註冊流程（Register Flow Page）- **通過**
3. ✅ 新用戶註冊流程（Fallback 模式）- **通過**
4. ✅ Auto-link（Email 已存在）- **通過**

**測試結果分析：**

### 案例 1：快速建立頁面
- ✅ AJAX 功能正常
- ✅ 頁面建立成功
- ⚠️ 黃色警告為非阻擋性提醒，不影響核心功能

### 案例 2：Register Flow Page
- ✅ Shortcode 正確渲染
- ✅ 表單功能正常
- ✅ 註冊成功

### 案例 3：Fallback 模式
- ✅ wp-login.php 表單正確顯示
- ✅ 註冊流程完整
- ✅ 成功建立帳號並登入
- **測試過程遇到的問題：**
  - 問題：刪除 WordPress 帳號後，LINE 綁定記錄未同步刪除
  - 原因：wp_buygo_line_users 資料表中仍存在綁定記錄
  - 解決：執行 `TRUNCATE TABLE wp_buygo_line_users` 清空資料表
  - 未來改進：可在刪除 WordPress 用戶時自動清除對應的 LINE 綁定記錄

### 案例 4：Auto-link
- ✅ 成功偵測 Email 已存在
- ✅ 自動綁定 LINE UID 到現有帳號
- ✅ 直接登入（未顯示註冊表單）

---

## Phase 10 完成總結（2026-01-29）

**執行狀態：✅ Phase 10 完全完成**

- Plan 10-01: ✅ Complete
- Plan 10-02: ✅ Complete
- Plan 10-03: ✅ Complete

**所有測試案例通過：**
- 後台設定頁面 ✅
- Register Flow Page 註冊流程 ✅
- Fallback 模式註冊流程 ✅
- Auto-link 自動綁定 ✅

**Phase 10 功能清單：**
1. ✅ RegisterFlowShortcode 實作
2. ✅ Transient 儲存與讀取機制
3. ✅ 表單提交處理與驗證
4. ✅ Auto-link 自動綁定邏輯
5. ✅ 後台設定頁面整合
6. ✅ AJAX 快速建立頁面功能
7. ✅ Fallback 模式支援

**未來改進建議：**
1. UI 風格統一（Phase 13 前台整合時處理）
2. 登入後跳轉頁面後台設定（Phase 13）
3. 刪除 WordPress 用戶時自動清除 LINE 綁定記錄（資料一致性）

