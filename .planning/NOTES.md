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
- [ ] 案例 2：新用戶註冊流程（Register Flow Page） - **待重測**（修正 shortcode 問題後）
- [ ] 案例 3：新用戶註冊流程（Fallback 模式） - **待測試**
- [ ] 案例 4：Auto-link（Email 已存在） - **待測試**

**下一步：**
請重新測試案例 2，應該會看到完整的註冊表單了！

