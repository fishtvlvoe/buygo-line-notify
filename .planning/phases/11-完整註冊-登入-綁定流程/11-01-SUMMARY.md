---
phase: 11-完整註冊-登入-綁定流程
plan: 01
subsystem: login-handler
tags: [LINE Login, account-binding, oauth-callback, user-flow]
requires: [Phase 10-03 (Register Flow Page + Auto-link)]
provides: [LINE account binding for logged-in users, FLOW_LINK exception handling]
affects: [Phase 11-02 (Login flow), Phase 12 (Profile Sync)]
tech-stack:
  added: []
  patterns: [Transient-based error messaging, user-friendly redirect error handling]
key-files:
  created: []
  modified:
    - includes/handlers/class-login-handler.php (handle_link_submission, redirect_with_error, link flow detection)
decisions:
  - decision: Use redirect_with_error() instead of wp_die() for binding errors
    rationale: Provides user-friendly error messages via transient + URL redirect pattern
    alternatives: [wp_die() (harsh), admin notices (requires page reload)]
  - decision: Link flow detection before login logic in handle_callback()
    rationale: Ensures binding confirmation flow has priority over auto-login
    alternatives: [After login check (would bypass binding), separate endpoint (complexity)]
  - decision: Smart transient cleanup strategy based on error type
    rationale: Security errors don't clear (prevent retry), user errors allow retry, unrecoverable errors clear
    alternatives: [Always clear (loses data), never clear (security risk)]
metrics:
  duration: 145s (~2.4 min)
  completed: 2026-01-29
---

# Phase 11 Plan 01: 已登入用戶綁定 LINE 帳號處理

**One-liner:** 實作 handle_link_submission() 綁定表單處理，支援 FLOW_LINK 流程與使用者友善錯誤訊息

## Objective

完成 Phase 11 的核心功能 - 讓已登入 WordPress 的用戶可以從綁定確認頁面完成 LINE 帳號綁定（FLOW-03）

**Target files:**
- includes/handlers/class-login-handler.php (新增 handle_link_submission, redirect_with_error, link flow detection)

## Execution Summary

**Tasks completed:** 2/2

| Task | Description | Commit | Files |
|------|-------------|--------|-------|
| 1 | 新增 handle_link_submission() 方法 | 58c5330 | class-login-handler.php |
| 2 | 修改 handle_callback() 補充綁定流程判斷 | 58c5330 | class-login-handler.php |

**Total commits:** 1
- 58c5330: feat(11-01): implement LINE account binding for logged-in users

## What Was Built

### Core Functionality

**1. handle_link_submission() 方法**（830-951 行）
- **Nonce 驗證**：buygo_line_link_nonce / buygo_line_link_action
- **State 驗證**：從 Transient 讀取 profile (buygo_line_profile_{state})
- **用戶 ID 一致性驗證**：state_data['user_id'] === get_current_user_id()
- **LINE UID 衝突檢查**：
  - 若 LINE UID 已綁定其他用戶 → 拒絕（line_already_linked）
  - 若當前用戶已綁定其他 LINE → 拒絕（user_already_linked）
- **執行綁定**：LineUserService::linkUser($user_id, $line_uid, false)
- **儲存 LINE 頭像**：update_user_meta('buygo_line_avatar_url')
- **觸發 hook**：do_action('buygo_line_after_link')
- **成功通知**：Transient (buygo_line_notice_{user_id})
- **導向**：redirect_url (apply login_redirect filter)

**2. redirect_with_error() Helper**（811-823 行）
- 取代 wp_die() 提供使用者友善的錯誤處理
- 錯誤訊息存到 Transient (buygo_line_link_error_{user_id})
- URL redirect with error code query arg (line_link_error)
- 1 分鐘 TTL (允許用戶查看錯誤後重試)

**3. handle_callback() 綁定流程判斷**（280-337 行）
- **順序關鍵**：link_user_id 檢查在 `if ($user_id)` 登入判斷之前
- **綁定流程檢測**：$link_user_id = $state_data['user_id'] ?? 0
- **三種情況處理**：
  - LINE UID 已綁定其他用戶 → wp_die() 錯誤
  - LINE UID 已綁定同一用戶 → 直接 perform_login()
  - 新綁定 → 拋出 FLOW_LINK 例外
- **FLOW_LINK 例外**：儲存 profile 到 Transient，攜帶 user_id
- **非綁定流程**：跳過判斷區塊，維持原有登入/註冊邏輯

**4. handle_login_init() 綁定 action 檢查**（99-102 行）
- 檢查 POST action === 'buygo_line_link'
- 呼叫 handle_link_submission()

### Error Handling Strategy

**Transient 清除策略：**
| 錯誤類型 | Transient 處理 | 原因 |
|---------|---------------|------|
| Nonce 驗證失敗 | 不清除 | 可能是 CSRF 攻擊，不允許重試 |
| 用戶 ID 不一致 | 清除 | 身份驗證問題，需重新登入 |
| LINE UID 已綁定其他用戶 | 清除 | 不可恢復錯誤 |
| 用戶已綁定其他 LINE | 清除 | 不可恢復錯誤 |
| 綁定失敗（資料庫錯誤） | 清除 | 不可恢復錯誤，避免數據不一致 |
| 成功綁定 | 清除 | 流程完成 |

**錯誤訊息：**
- `nonce_failed`: "安全驗證失敗，請重新操作"
- `user_mismatch`: "身份驗證失敗，請重新登入"
- `line_already_linked`: "此 LINE 帳號已綁定其他用戶，若需解除綁定請聯繫管理員"
- `user_already_linked`: "您的帳號已綁定其他 LINE 帳號，請先解除綁定"
- `link_failed`: "綁定失敗，請稍後再試"

## Deviations from Plan

None - 計畫執行完全按照規劃。

## Integration Points

**Depends on:**
- Phase 10-03: Register Flow Page + Auto-link mechanism
- Phase 9: StateManager (state 驗證)
- Phase 8: LineUserService (linkUser, getUserByLineUid, getLineUidByUserId)

**Provides for:**
- Phase 11-02: 完整 Login flow (已有 link flow detection 基礎)
- Phase 12: Profile Sync (buygo_line_after_link hook 整合點)
- Phase 13: Frontend Integration (錯誤訊息顯示機制)

**Key hooks:**
- `buygo_line_after_link`: 綁定成功後觸發（供其他外掛使用）
- `login_redirect`: 綁定成功後導向 filter

## Testing Notes

**Manual verification needed:**
1. **Binding confirmation page**: 已登入用戶發起 LINE Login（state 包含 user_id）
   - Expected: handle_callback() 拋出 FLOW_LINK 例外
   - Expected: 導向到 Link Flow Page 或 fallback confirmation
2. **Binding submission**: 點擊確認綁定按鈕
   - Expected: handle_link_submission() 執行綁定
   - Expected: Transient 清除，設定成功通知，導向 redirect_url
3. **LINE UID conflict**: LINE UID 已綁定其他用戶
   - Expected: redirect_with_error('line_already_linked')
4. **User already linked**: 當前用戶已綁定其他 LINE
   - Expected: redirect_with_error('user_already_linked')
5. **Already linked**: LINE UID 已綁定同一用戶
   - Expected: 直接 perform_login()
6. **Nonce failure**: 篡改 nonce
   - Expected: redirect_with_error('nonce_failed')，Transient 不清除

**Edge cases covered:**
- User ID mismatch (session hijacking attempt)
- State expiry (Transient 已過期)
- Database errors during linkUser()
- Concurrent binding attempts

## Known Limitations

1. **Link Flow Page 尚未實作**：目前只有 fallback confirmation (render_fallback_link_confirmation)
   - Phase 11-03 將實作專用 Link Flow Shortcode
2. **錯誤訊息顯示**：前台需要讀取 Transient 並顯示錯誤
   - Phase 13 將整合前台錯誤訊息顯示
3. **解除綁定功能**：錯誤訊息提到「請先解除綁定」，但功能尚未實作
   - Phase 14 後台管理將提供解除綁定功能

## Next Phase Readiness

**Phase 11-02 可以開始：**
- ✅ Link flow detection 已完成
- ✅ FLOW_LINK 例外處理架構已建立
- ⏳ 需要實作 Login flow (已登入用戶發起登入的處理)

**Blockers:** None

**Recommendations:**
1. Phase 11-02 實作時，參考本次的 link flow detection 模式
2. Phase 11-03 Link Flow Shortcode 實作時，參考 Phase 10 Register Flow Shortcode 模式
3. Phase 13 前台整合時，實作 Transient 錯誤訊息讀取和顯示機制
